<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Application;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\EstadoCivil;
use DigiSecure\Ficha\Domain\Enum\Genero;
use DigiSecure\Ficha\Domain\Enum\TipoFicha;
use DigiSecure\Ficha\Domain\Enum\TipoSangre;
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
        $input = $this->normalizeInput($input);

        // 1. Validar campos obligatorios mínimos
        $this->validateRequired($input);

        // 2. Validar cédula
        $cedula = Cedula::fromString($this->scalarString($input['cedula'] ?? ''));

        // 3. Determinar tipo de ficha
        $tipo = $this->tipoFicha($this->scalarString($input['tipo_ficha'] ?? 'INGRESO'));
        $genero = $this->genero($this->scalarString($input['genero'] ?? ''));
        $fechaNacimiento = $this->dateRequired($this->scalarString($input['fecha_nacimiento'] ?? ''), 'Fecha de nacimiento');
        $fechaReentrenamiento = $this->dateOptional(
            $this->scalarString($input['fecha_ultimo_reentrenamiento'] ?? ''),
            'Fecha de ultimo reentrenamiento',
        );
        $tipoSangre = $this->tipoSangreOptional($this->scalarString($input['tipo_sangre'] ?? ''));
        $estadoCivil = $this->estadoCivilOptional($this->scalarString($input['estado_civil'] ?? ''));
        $tipoCuenta = $this->allowedOptional(
            $this->scalarString($input['tipo_cuenta'] ?? ''),
            ['AHORROS', 'CORRIENTE'],
            'Tipo de cuenta',
        );
        $tipoVivienda = $this->allowedOptional(
            $this->scalarString($input['domicilio_tipo_vivienda'] ?? ''),
            ['PROPIA', 'ARRENDADA', 'FAMILIAR'],
            'Tipo de vivienda',
        );
        $email = $this->optionalString($input, 'email');
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('El email no tiene un formato valido.');
        }
        $estatura = $this->decimalOptional($this->scalarString($input['estatura'] ?? ''), 'Estatura', 1.0, 2.5);

        // 4. Verificar si hay fichas previas con la misma cédula (reingreso)
        $fichasPrevias = $this->fichaRepo->findByCedulaHash($cedula->hash());
        if ($tipo === TipoFicha::REINGRESO && $fichasPrevias === []) {
            throw new DomainException('Para tipo REINGRESO debe existir al menos una ficha previa con la misma cédula.');
        }

        // 5. Cifrar datos sensibles
        $cedulaCifrada = $this->encryption->encrypt($cedula->valor);
        $cuentaCifrada = null;
        $cuentaBancaria = $this->optionalString($input, 'cuenta_bancaria');
        if ($cuentaBancaria !== null) {
            $cuentaCifrada = $this->encryption->encrypt($cuentaBancaria);
        }

        // 6. Generar correlativo
        $correlativo = $this->fichaRepo->nextCorrelativo();

        // 7. Persistir en transacción
        $croquisArchivoId = null;
        $croquisImagenData = $this->scalarString($input['croquis_imagen_data'] ?? '');
        if ($croquisImagenData !== '') {
            $croquisArchivoId = $this->croquisStorage->storeDataUrl($croquisImagenData);
        } elseif ($this->scalarString($input['croquis_mapa_estatico'] ?? '') === '1'
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
        $fotoImagenData = $this->scalarString($input['foto_imagen_data'] ?? '');
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
                $genero,
                $fechaNacimiento,
                $fechaReentrenamiento,
                $tipoSangre,
                $estadoCivil,
                $tipoCuenta,
                $tipoVivienda,
                $email,
                $estatura,
                $fichasPrevias,
                $usuarioId,
                $croquisArchivoId,
                $fotoArchivoId,
            ): string {
                $data = [
                'correlativo' => $correlativo,
                'tipo_ficha' => $tipo->value,
                'estado' => EstadoFicha::BORRADOR->value,
                'fecha_emision' => date('Y-m-d'),
                'nombres' => $this->requiredString($input, 'nombres'),
                'apellidos' => $this->requiredString($input, 'apellidos'),
                'cedula_hash' => $cedula->hash(),
                'cedula_cifrada' => $cedulaCifrada,
                'genero' => $genero->value,
                'fecha_nacimiento' => $fechaNacimiento,
                'lugar_nacimiento_ciudad' => $this->optionalString($input, 'lugar_nacimiento_ciudad'),
                'lugar_nacimiento_provincia' => $this->optionalString($input, 'lugar_nacimiento_provincia'),
                'tipo_sangre' => $tipoSangre,
                'estatura' => $estatura,
                'celular' => $this->optionalString($input, 'celular'),
                'email' => $email,
                'banco' => $this->optionalString($input, 'banco'),
                'tipo_cuenta' => $tipoCuenta,
                'cuenta_bancaria_cifrada' => $cuentaCifrada,
                'domicilio_ciudad' => $this->optionalString($input, 'domicilio_ciudad'),
                'domicilio_tipo_vivienda' => $tipoVivienda,
                'domicilio_calle' => $this->optionalString($input, 'domicilio_calle'),
                'domicilio_numero' => $this->optionalString($input, 'domicilio_numero'),
                'domicilio_barrio' => $this->optionalString($input, 'domicilio_barrio'),
                'domicilio_referencia' => $this->optionalString($input, 'domicilio_referencia'),
                'domicilio_telefono' => $this->optionalString($input, 'domicilio_telefono'),
                'croquis_latitud' => (isset($input['croquis_latitud']) && is_numeric($input['croquis_latitud'])) ? (float) $input['croquis_latitud'] : null,
                'croquis_longitud' => (isset($input['croquis_longitud']) && is_numeric($input['croquis_longitud'])) ? (float) $input['croquis_longitud'] : null,
                'croquis_archivo_id' => $croquisArchivoId,
                'foto_archivo_id' => $fotoArchivoId,
                'contacto_emergencia_nombre' => $this->optionalString($input, 'contacto_emergencia_nombre'),
                'contacto_emergencia_telefono' => $this->optionalString($input, 'contacto_emergencia_telefono'),
                'instruccion_primaria' => $this->optionalString($input, 'instruccion_primaria'),
                'instruccion_secundaria' => $this->optionalString($input, 'instruccion_secundaria'),
                'instruccion_superior' => $this->optionalString($input, 'instruccion_superior'),
                'fecha_ultimo_reentrenamiento' => $fechaReentrenamiento,
                'estado_civil' => $estadoCivil,
                'ficha_anterior_id' => $fichasPrevias[0]['id'] ?? null,
                'created_by' => $usuarioId,
                'updated_by' => $usuarioId,
            ];

                $fichaId = $this->fichaRepo->create($data);

                // Guardar relaciones 1:N si vienen en el input
                if (!empty($input['trabajos_anteriores']) && is_array($input['trabajos_anteriores'])) {
                    try {
                        $this->fichaRepo->saveTrabajosAnteriores($fichaId, $input['trabajos_anteriores']);
                    } catch (\Throwable $e) {
                        error_log('[DIGISECURE][FICHA] Ficha creada pero trabajos anteriores fallo: ' . $e->getMessage());
                    }
                }

                if (!empty($input['hijos_menores']) && is_array($input['hijos_menores'])) {
                    try {
                        $this->fichaRepo->saveHijosMenores($fichaId, $input['hijos_menores']);
                    } catch (\Throwable $e) {
                        error_log('[DIGISECURE][FICHA] Ficha creada pero hijos menores fallo: ' . $e->getMessage());
                    }
                }

                if (!empty($input['referencias']) && is_array($input['referencias'])) {
                    try {
                        $this->fichaRepo->saveReferencias($fichaId, $input['referencias']);
                    } catch (\Throwable $e) {
                        error_log('[DIGISECURE][FICHA] Ficha creada pero referencias fallo: ' . $e->getMessage());
                    }
                }

                if (!empty($input['cursos']) && is_array($input['cursos'])) {
                    try {
                        $this->fichaRepo->saveCursos($fichaId, $input['cursos']);
                    } catch (\Throwable $e) {
                        error_log('[DIGISECURE][FICHA] Ficha creada pero cursos fallo: ' . $e->getMessage());
                    }
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
        try {
            $this->audit->log(
                'ficha.crear',
                $usuarioId,
                'ficha',
                $fichaId,
                detalles: ['correlativo' => $correlativo, 'tipo' => $tipo->value],
                ip: $ip,
            );
        } catch (\Throwable $e) {
            error_log('[DIGISECURE][FICHA] Ficha creada pero auditoria fallo: ' . $e->getMessage());
        }

        return ['id' => $fichaId, 'correlativo' => $correlativo];
    }

    private function validateRequired(array $input): void
    {
        $required = ['nombres', 'apellidos', 'cedula', 'genero', 'fecha_nacimiento'];
        $missing = [];
        foreach ($required as $field) {
            if ($this->scalarString($input[$field] ?? '') === '') {
                $missing[] = $field;
            }
        }
        if ($missing !== []) {
            throw new DomainException('Campos obligatorios faltantes: ' . implode(', ', $missing));
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value);
            }
        }

        return $input;
    }

    private function tipoFicha(string $value): TipoFicha
    {
        $value = $value !== '' ? $value : TipoFicha::INGRESO->value;
        $tipo = TipoFicha::tryFrom($value);
        if ($tipo === null) {
            throw new DomainException('Tipo de ficha no es valido.');
        }

        return $tipo;
    }

    private function genero(string $value): Genero
    {
        $genero = Genero::tryFrom($value);
        if ($genero === null) {
            throw new DomainException('Genero no es valido.');
        }

        return $genero;
    }

    private function tipoSangreOptional(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (TipoSangre::tryFrom($value) === null) {
            throw new DomainException('Tipo de sangre no es valido.');
        }

        return $value;
    }

    private function estadoCivilOptional(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (EstadoCivil::tryFrom($value) === null) {
            throw new DomainException('Estado civil no es valido.');
        }

        return $value;
    }

    /**
     * @param list<string> $allowed
     */
    private function allowedOptional(string $value, array $allowed, string $label): ?string
    {
        if ($value === '') {
            return null;
        }

        if (!in_array($value, $allowed, true)) {
            throw new DomainException($label . ' no es valido.');
        }

        return $value;
    }

    private function dateRequired(string $value, string $label): string
    {
        if ($value === '') {
            throw new DomainException($label . ' es obligatorio.');
        }

        if (!$this->isDate($value)) {
            throw new DomainException($label . ' no tiene un formato valido.');
        }

        return $value;
    }

    private function dateOptional(string $value, string $label): ?string
    {
        if ($value === '') {
            return null;
        }

        if (!$this->isDate($value)) {
            throw new DomainException($label . ' no tiene un formato valido.');
        }

        return $value;
    }

    private function decimalOptional(string $value, string $label, ?float $min = null, ?float $max = null): ?float
    {
        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new DomainException($label . ' debe ser numerico.');
        }

        $number = (float)$value;
        if ($min !== null && $number < $min) {
            throw new DomainException($label . ' esta fuera del rango permitido.');
        }
        if ($max !== null && $number > $max) {
            throw new DomainException($label . ' esta fuera del rango permitido.');
        }

        return $number;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function requiredString(array $input, string $field): string
    {
        return $this->scalarString($input[$field] ?? '');
    }

    /**
     * @param array<string, mixed> $input
     */
    private function optionalString(array $input, string $field): ?string
    {
        $value = $this->scalarString($input[$field] ?? '');
        return $value !== '' ? $value : null;
    }

    private function scalarString(mixed $value): string
    {
        if ($value === null || is_scalar($value)) {
            return trim((string)$value);
        }

        return '';
    }
}
