<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Application;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\Genero;
use DigiSecure\Ficha\Domain\ValueObject\Cedula;
use DigiSecure\Ficha\Infrastructure\CroquisStorage;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Crypto\BlindIndex;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Caso de uso: Actualizar una ficha existente.
 *
 * Solo permite editar fichas en estado BORRADOR o EN_REVISION.
 * Una ficha COMPLETA o ANULADA no puede modificarse.
 */
final class ActualizarFichaService
{
    public function __construct(
        private readonly FichaRepository $fichaRepo,
        private readonly PdoConnection $db,
        private readonly Encryption $encryption,
        private readonly BlindIndex $blindIndex,
        private readonly NormalizarFamiliaresFicha $familiares,
        private readonly AuditLogger $audit,
        private readonly CroquisStorage $croquisStorage,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @throws DomainException
     */
    public function execute(string $fichaId, array $input, string $usuarioId, string $ip): void
    {
        $ficha = $this->fichaRepo->findFichaById($fichaId);
        if ($ficha === null) {
            throw new DomainException('Ficha no encontrada.');
        }

        $estado = EstadoFicha::from($ficha['estado']);
        if (!in_array($estado, [EstadoFicha::BORRADOR, EstadoFicha::EN_REVISION], true)) {
            throw new DomainException("No se puede editar una ficha con estado '{$estado->label()}'.");
        }

        // Validar cédula si se envía
        $data = [];
        if (!empty($input['cedula'])) {
            $cedula = Cedula::fromString((string)$input['cedula']);
            $data['cedula_hash'] = $this->blindIndex->cedula($cedula->valor);
            $data['cedula_cifrada'] = $this->encryption->encrypt($cedula->valor);
        }

        // Cifrar cuenta bancaria si cambia
        if (array_key_exists('cuenta_bancaria', $input)) {
            $cuentaBancaria = trim((string) $input['cuenta_bancaria']);
            $conservarCuentaAnterior = $cuentaBancaria === ''
                && (string) ($input['cuenta_bancaria_conservar'] ?? '') === '1';

            if (!$conservarCuentaAnterior) {
                $data['cuenta_bancaria_cifrada'] = $cuentaBancaria !== ''
                    ? $this->encryption->encrypt($cuentaBancaria)
                    : null;
            }
        }

        // Validar género si se envía
        if (!empty($input['genero'])) {
            Genero::from((string)$input['genero']);
        }

        $actualizarFamiliares = $this->familiares->contieneCamposFamiliares($input);
        $familiares = $actualizarFamiliares
            ? $this->familiares->normalizar($input, (string) ($input['estado_civil'] ?? $ficha['estado_civil'] ?? ''))
            : [];

        // Mapear campos directos
        $directFields = [
            'nombres', 'apellidos', 'genero', 'fecha_nacimiento',
            'lugar_nacimiento_ciudad', 'lugar_nacimiento_provincia',
            'tipo_sangre', 'estatura', 'celular', 'email',
            'banco', 'tipo_cuenta',
            'domicilio_ciudad', 'domicilio_tipo_vivienda', 'domicilio_calle',
            'domicilio_numero', 'domicilio_barrio', 'domicilio_referencia',
            'domicilio_telefono', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono',
            'instruccion_primaria', 'instruccion_secundaria', 'instruccion_superior',
            'fecha_ultimo_reentrenamiento', 'estado_civil', 'fecha_revision',
        ];

        foreach ($directFields as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                $data[$field] = ($value !== '' && $value !== null) ? $value : null;
            }
        }

        foreach (['croquis_latitud', 'croquis_longitud'] as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                $data[$field] = ($value !== '' && $value !== null && is_numeric($value)) ? (float) $value : null;
            }
        }

        $croquisActualId = (string) ($ficha['croquis_archivo_id'] ?? '');
        $croquisImagenData = trim((string) ($input['croquis_imagen_data'] ?? ''));
        $capturarMapaEstatico = ((string) ($input['croquis_mapa_estatico'] ?? '')) === '1';
        $eliminarCroquis = ((string) ($input['croquis_eliminar_imagen'] ?? '')) === '1';
        $nuevoCroquisId = null;
        $borrarCroquisActual = false;

        if ($croquisImagenData !== '') {
            $nuevoCroquisId = $this->croquisStorage->storeDataUrl($croquisImagenData);
            $data['croquis_archivo_id'] = $nuevoCroquisId;
            $borrarCroquisActual = $croquisActualId !== '';
        } elseif ($capturarMapaEstatico
            && isset($input['croquis_latitud'], $input['croquis_longitud'])
            && is_numeric($input['croquis_latitud'])
            && is_numeric($input['croquis_longitud'])
        ) {
            $nuevoCroquisId = $this->croquisStorage->storeStaticMap(
                (float) $input['croquis_latitud'],
                (float) $input['croquis_longitud'],
                isset($input['croquis_zoom']) && is_numeric($input['croquis_zoom']) ? (int) $input['croquis_zoom'] : 16,
            );
            $data['croquis_archivo_id'] = $nuevoCroquisId;
            $borrarCroquisActual = $croquisActualId !== '';
        } elseif ($eliminarCroquis && $croquisActualId !== '') {
            $data['croquis_archivo_id'] = null;
            $borrarCroquisActual = true;
        }

        // Foto del empleado: reemplazo (nueva imagen) o eliminación.
        $fotoActualId = (string) ($ficha['foto_archivo_id'] ?? '');
        $fotoImagenData = trim((string) ($input['foto_imagen_data'] ?? ''));
        $eliminarFoto = ((string) ($input['foto_eliminar'] ?? '')) === '1';
        $nuevoFotoId = null;
        $borrarFotoActual = false;
        if ($fotoImagenData !== '') {
            $nuevoFotoId = $this->croquisStorage->storeDataUrl($fotoImagenData);
            $data['foto_archivo_id'] = $nuevoFotoId;
            $borrarFotoActual = $fotoActualId !== '';
        } elseif ($eliminarFoto && $fotoActualId !== '') {
            $data['foto_archivo_id'] = null;
            $borrarFotoActual = true;
        }

        // Trim strings
        foreach (['nombres', 'apellidos', 'email'] as $f) {
            if (isset($data[$f]) && is_string($data[$f])) {
                $data[$f] = trim($data[$f]);
            }
        }

        $data['updated_by'] = $usuarioId;

        try {
            $this->db->transactional(function () use ($fichaId, $data, $input, $actualizarFamiliares, $familiares): void {
                if ($data !== []) {
                    $this->fichaRepo->update($fichaId, $data);
                }

                // Relaciones 1:N
                if (isset($input['trabajos_anteriores']) && is_array($input['trabajos_anteriores'])) {
                    $this->fichaRepo->saveTrabajosAnteriores($fichaId, $input['trabajos_anteriores']);
                }
                if (isset($input['hijos_menores']) && is_array($input['hijos_menores'])) {
                    $this->fichaRepo->saveHijosMenores($fichaId, $input['hijos_menores']);
                }
                if ($actualizarFamiliares) {
                    $this->fichaRepo->saveFamiliares($fichaId, $familiares);
                }
                if (isset($input['referencias']) && is_array($input['referencias'])) {
                    $this->fichaRepo->saveReferencias($fichaId, $input['referencias']);
                }
                if (isset($input['cursos']) && is_array($input['cursos'])) {
                    $this->fichaRepo->saveCursos($fichaId, $input['cursos']);
                }
            });
        } catch (\Throwable $e) {
            if ($nuevoCroquisId !== null) {
                $this->croquisStorage->delete($nuevoCroquisId);
            }
            if ($nuevoFotoId !== null) {
                $this->croquisStorage->delete($nuevoFotoId);
            }
            throw $e;
        }

        if ($borrarCroquisActual) {
            $this->croquisStorage->delete($croquisActualId);
        }
        if ($borrarFotoActual) {
            $this->croquisStorage->delete($fotoActualId);
        }

        $this->audit->log(
            'ficha.actualizar',
            $usuarioId,
            'ficha',
            $fichaId,
            detalles: ['campos_modificados' => array_keys($data)],
            ip: $ip,
        );
    }
}
