<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Application;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\Genero;
use DigiSecure\Ficha\Domain\Enum\TipoFicha;
use DigiSecure\Ficha\Domain\ValueObject\Cedula;
use DigiSecure\Ficha\Infrastructure\CroquisStorage;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Caso de uso: Crear una nueva ficha de guardia.
 *
 * Orquesta:
 *  1. Validación de datos de entrada
 *  2. Validación de cédula (algoritmo Ecuador)
 *  3. Cifrado de datos sensibles
 *  4. Generación de correlativo
 *  5. Detección de reingreso (mismo cedula_hash en el tenant)
 *  6. Persistencia en transacción
 *  7. Auditoría
 */
final class CrearFichaService
{
    public function __construct(
        private readonly FichaRepository $fichaRepo,
        private readonly PdoConnection $db,
        private readonly Encryption $encryption,
        private readonly AuditLogger $audit,
        private readonly CroquisStorage $croquisStorage,
    ) {
    }

    /**
     * @param array<string, mixed> $input  Datos del formulario
     * @param string $usuarioId  ID del usuario que crea la ficha
     * @param string $ip  IP del request
     * @return array{id: string, correlativo: string}
     * @throws DomainException Si la validación falla
     */
    public function execute(array $input, string $usuarioId, string $ip): array
    {
        // 1. Validar campos obligatorios mínimos
        $this->validateRequired($input);

        // 2. Validar cédula
        $cedula = Cedula::fromString((string)($input['cedula'] ?? ''));

        // 3. Determinar tipo de ficha
        $tipo = TipoFicha::from((string)($input['tipo_ficha'] ?? 'INGRESO'));

        // 4. Verificar si hay fichas previas con la misma cédula (reingreso)
        $fichasPrevias = $this->fichaRepo->findByCedulaHash($cedula->hash());
        if ($tipo === TipoFicha::REINGRESO && $fichasPrevias === []) {
            throw new DomainException('Para tipo REINGRESO debe existir al menos una ficha previa con la misma cédula.');
        }

        // 5. Cifrar datos sensibles
        $cedulaCifrada = $this->encryption->encrypt($cedula->valor);
        $cuentaCifrada = null;
        if (!empty($input['cuenta_bancaria'])) {
            $cuentaCifrada = $this->encryption->encrypt((string)$input['cuenta_bancaria']);
        }

        // 6. Generar correlativo
        $correlativo = $this->fichaRepo->nextCorrelativo();

        // 7. Persistir en transacción
        $croquisArchivoId = null;
        $croquisImagenData = trim((string) ($input['croquis_imagen_data'] ?? ''));
        if ($croquisImagenData !== '') {
            $croquisArchivoId = $this->croquisStorage->storeDataUrl($croquisImagenData);
        } elseif (((string) ($input['croquis_mapa_estatico'] ?? '')) === '1'
            && isset($input['croquis_latitud'], $input['croquis_longitud'])
            && is_numeric($input['croquis_latitud'])
            && is_numeric($input['croquis_longitud'])
        ) {
            $croquisArchivoId = $this->croquisStorage->storeStaticMap(
                (float) $input['croquis_latitud'],
                (float) $input['croquis_longitud'],
                isset($input['croquis_zoom']) && is_numeric($input['croquis_zoom']) ? (int) $input['croquis_zoom'] : 16,
            );
        }

        // Foto del empleado (data URL); se almacena con el mismo storage cifrado.
        $fotoArchivoId = null;
        $fotoImagenData = trim((string) ($input['foto_imagen_data'] ?? ''));
        if ($fotoImagenData !== '') {
            $fotoArchivoId = $this->croquisStorage->storeDataUrl($fotoImagenData);
        }

        try {
            $fichaId = $this->db->transactional(function () use (
                $input,
                $cedula,
                $cedulaCifrada,
                $cuentaCifrada,
                $correlativo,
                $tipo,
                $fichasPrevias,
                $usuarioId,
                $croquisArchivoId,
                $fotoArchivoId,
            ): string {
                $genero = Genero::from((string)($input['genero'] ?? 'MASCULINO'));

                $data = [
                'correlativo' => $correlativo,
                'tipo_ficha' => $tipo->value,
                'estado' => EstadoFicha::BORRADOR->value,
                'fecha_emision' => date('Y-m-d'),
                'nombres' => trim((string)($input['nombres'] ?? '')),
                'apellidos' => trim((string)($input['apellidos'] ?? '')),
                'cedula_hash' => $cedula->hash(),
                'cedula_cifrada' => $cedulaCifrada,
                'genero' => $genero->value,
                'fecha_nacimiento' => (string)($input['fecha_nacimiento'] ?? ''),
                'lugar_nacimiento_ciudad' => $input['lugar_nacimiento_ciudad'] ?? null,
                'lugar_nacimiento_provincia' => $input['lugar_nacimiento_provincia'] ?? null,
                'tipo_sangre' => !empty($input['tipo_sangre']) ? $input['tipo_sangre'] : null,
                'estatura' => !empty($input['estatura']) ? (float)$input['estatura'] : null,
                'celular' => $input['celular'] ?? null,
                'email' => !empty($input['email']) ? trim((string)$input['email']) : null,
                'banco' => $input['banco'] ?? null,
                'tipo_cuenta' => !empty($input['tipo_cuenta']) ? $input['tipo_cuenta'] : null,
                'cuenta_bancaria_cifrada' => $cuentaCifrada,
                'domicilio_ciudad' => $input['domicilio_ciudad'] ?? null,
                'domicilio_tipo_vivienda' => !empty($input['domicilio_tipo_vivienda']) ? $input['domicilio_tipo_vivienda'] : null,
                'domicilio_calle' => $input['domicilio_calle'] ?? null,
                'domicilio_numero' => $input['domicilio_numero'] ?? null,
                'domicilio_barrio' => $input['domicilio_barrio'] ?? null,
                'domicilio_referencia' => $input['domicilio_referencia'] ?? null,
                'domicilio_telefono' => $input['domicilio_telefono'] ?? null,
                'croquis_latitud' => (isset($input['croquis_latitud']) && is_numeric($input['croquis_latitud'])) ? (float) $input['croquis_latitud'] : null,
                'croquis_longitud' => (isset($input['croquis_longitud']) && is_numeric($input['croquis_longitud'])) ? (float) $input['croquis_longitud'] : null,
                'croquis_archivo_id' => $croquisArchivoId,
                'foto_archivo_id' => $fotoArchivoId,
                'contacto_emergencia_nombre' => $input['contacto_emergencia_nombre'] ?? null,
                'contacto_emergencia_telefono' => $input['contacto_emergencia_telefono'] ?? null,
                'instruccion_primaria' => $input['instruccion_primaria'] ?? null,
                'instruccion_secundaria' => $input['instruccion_secundaria'] ?? null,
                'instruccion_superior' => $input['instruccion_superior'] ?? null,
                'fecha_ultimo_reentrenamiento' => !empty($input['fecha_ultimo_reentrenamiento']) ? $input['fecha_ultimo_reentrenamiento'] : null,
                'estado_civil' => !empty($input['estado_civil']) ? $input['estado_civil'] : null,
                'ficha_anterior_id' => $fichasPrevias[0]['id'] ?? null,
                'created_by' => $usuarioId,
                'updated_by' => $usuarioId,
            ];

                $fichaId = $this->fichaRepo->create($data);

                // Guardar relaciones 1:N si vienen en el input
                if (!empty($input['trabajos_anteriores']) && is_array($input['trabajos_anteriores'])) {
                    $this->fichaRepo->saveTrabajosAnteriores($fichaId, $input['trabajos_anteriores']);
                }

                if (!empty($input['hijos_menores']) && is_array($input['hijos_menores'])) {
                    $this->fichaRepo->saveHijosMenores($fichaId, $input['hijos_menores']);
                }

                if (!empty($input['referencias']) && is_array($input['referencias'])) {
                    $this->fichaRepo->saveReferencias($fichaId, $input['referencias']);
                }

                if (!empty($input['cursos']) && is_array($input['cursos'])) {
                    $this->fichaRepo->saveCursos($fichaId, $input['cursos']);
                }

                return $fichaId;
            });
        } catch (\Throwable $e) {
            if ($croquisArchivoId !== null) {
                $this->croquisStorage->delete($croquisArchivoId);
            }
            if ($fotoArchivoId !== null) {
                $this->croquisStorage->delete($fotoArchivoId);
            }
            throw $e;
        }

        // 8. Auditoría
        $this->audit->log(
            'ficha.crear',
            $usuarioId,
            'ficha',
            $fichaId,
            detalles: ['correlativo' => $correlativo, 'tipo' => $tipo->value],
            ip: $ip,
        );

        return ['id' => $fichaId, 'correlativo' => $correlativo];
    }

    private function validateRequired(array $input): void
    {
        $required = ['nombres', 'apellidos', 'cedula', 'genero', 'fecha_nacimiento'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($input[$field]) || trim((string)$input[$field]) === '') {
                $missing[] = $field;
            }
        }
        if ($missing !== []) {
            throw new DomainException('Campos obligatorios faltantes: ' . implode(', ', $missing));
        }
    }
}
