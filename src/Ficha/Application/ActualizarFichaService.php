<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Application;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\Genero;
use DigiSecure\Ficha\Domain\ValueObject\Cedula;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
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
        private readonly AuditLogger $audit,
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
            $data['cedula_hash'] = $cedula->hash();
            $data['cedula_cifrada'] = $this->encryption->encrypt($cedula->valor);
        }

        // Cifrar cuenta bancaria si cambia
        if (array_key_exists('cuenta_bancaria', $input)) {
            $data['cuenta_bancaria_cifrada'] = !empty($input['cuenta_bancaria'])
                ? $this->encryption->encrypt((string)$input['cuenta_bancaria'])
                : null;
        }

        // Validar género si se envía
        if (!empty($input['genero'])) {
            Genero::from((string)$input['genero']);
        }

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

        // Trim strings
        foreach (['nombres', 'apellidos', 'email'] as $f) {
            if (isset($data[$f]) && is_string($data[$f])) {
                $data[$f] = trim($data[$f]);
            }
        }

        $data['updated_by'] = $usuarioId;

        $this->db->transactional(function () use ($fichaId, $data, $input): void {
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
            if (isset($input['referencias']) && is_array($input['referencias'])) {
                $this->fichaRepo->saveReferencias($fichaId, $input['referencias']);
            }
            if (isset($input['cursos']) && is_array($input['cursos'])) {
                $this->fichaRepo->saveCursos($fichaId, $input['cursos']);
            }
        });

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
