<?php
declare(strict_types=1);

namespace DigiSecure\Infrastructure\Import;

use DigiSecure\Ficha\Domain\ValueObject\Cedula;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;
use Throwable;

final class GemesegStagingProcessorService
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
        private readonly Encryption $encryption,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function process(string $source, string $userId, string $ip, int $limit = 500): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $source = $this->normalizeSource($source);
        if ($source === '') {
            throw new DomainException('Selecciona una fuente de staging para procesar.');
        }

        $rows = $this->pendingRows($tenantId, $source, $limit);
        $result = [
            'source' => $source,
            'read_rows' => count($rows),
            'processed_rows' => 0,
            'omitted_rows' => 0,
            'error_rows' => 0,
            'created_fichas' => 0,
            'updated_fichas' => 0,
            'courses_created' => 0,
            'vaccines_created' => 0,
            'documents_created' => 0,
        ];

        if ($rows === []) {
            return $result;
        }

        $tenantEncryption = $this->encryption->deriveTenantKey($tenantId);

        $this->db->transactional(function (PdoConnection $db) use (
            $rows,
            $tenantEncryption,
            $tenantId,
            $userId,
            &$result
        ): void {
            foreach ($rows as $row) {
                try {
                    $payload = $this->decryptPayload($tenantEncryption, $tenantId, $row);
                    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                    $cedula = $this->cedulaFromPayload($payload);
                    $cedulaHash = (string) ($row['cedula_hash'] ?? '');

                    if ($cedula === '' || $cedulaHash === '') {
                        $this->markRow((string) $row['id'], 'error', null, 'Cedula ausente o invalida.');
                        $result['error_rows']++;
                        continue;
                    }

                    $ficha = $this->findLatestActiveFicha($tenantId, $cedulaHash);
                    $mapped = $this->mapFichaData($data);
                    $created = false;
                    $updated = false;

                    if ($ficha === null) {
                        if (!$this->canCreateFicha($mapped)) {
                            $this->markRow((string) $row['id'], 'omitido', null, 'Sin ficha previa y datos minimos incompletos.');
                            $result['omitted_rows']++;
                            continue;
                        }

                        $fichaId = $this->createFicha($tenantId, $mapped, $cedula, $cedulaHash, $userId);
                        $created = true;
                        $result['created_fichas']++;
                    } else {
                        $fichaId = (string) $ficha['id'];
                        $updated = $this->updateFichaMissingFields($tenantId, $ficha, $mapped, $userId);
                        if ($updated) {
                            $result['updated_fichas']++;
                        }
                    }

                    $courses = $this->processCourses($tenantId, $fichaId, $data);
                    $vaccines = $this->processVaccines($tenantId, $fichaId, $data);
                    $documents = $this->processDocuments($tenantId, $fichaId, (string) ($row['hoja'] ?? ''), $data, $userId);

                    $result['courses_created'] += $courses;
                    $result['vaccines_created'] += $vaccines;
                    $result['documents_created'] += $documents;
                    $result['processed_rows']++;

                    $this->markRow((string) $row['id'], 'procesado', $fichaId, null);
                } catch (Throwable $e) {
                    $this->markRow((string) ($row['id'] ?? ''), 'error', null, $this->shortError($e->getMessage()));
                    $result['error_rows']++;
                }
            }
        });

        $this->audit->log(
            'importacion.gemeseg.procesar',
            $userId,
            'importacion_excel',
            null,
            [
                'fuente' => $source,
                'read_rows' => $result['read_rows'],
                'processed_rows' => $result['processed_rows'],
                'omitted_rows' => $result['omitted_rows'],
                'error_rows' => $result['error_rows'],
                'created_fichas' => $result['created_fichas'],
                'updated_fichas' => $result['updated_fichas'],
                'courses_created' => $result['courses_created'],
                'vaccines_created' => $result['vaccines_created'],
                'documents_created' => $result['documents_created'],
            ],
            $result['error_rows'] > 0 ? 'warning' : 'info',
            $ip,
        );

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingRows(string $tenantId, string $source, int $limit): array
    {
        return $this->db->fetchAll(
            "SELECT id, tenant_id, fuente, hoja, fila, cedula_hash, raw_json_cifrado
             FROM importacion_excel_registros
             WHERE tenant_id = :tenant_id
               AND fuente = :fuente
               AND estado = 'pendiente'
             ORDER BY
                CASE hoja
                    WHEN 'GUARDIAS GEMESEG' THEN 1
                    WHEN 'SALIDAS' THEN 2
                    WHEN 'DOCUMENTACION ACTUALIZADA' THEN 3
                    WHEN 'CURSOS' THEN 4
                    WHEN 'DOCUMENTOS FALTANTES' THEN 5
                    ELSE 9
                END,
                fila
             LIMIT :limit",
            ['tenant_id' => $tenantId, 'fuente' => $source, 'limit' => max(1, min(2000, $limit))],
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decryptPayload(Encryption $tenantEncryption, string $tenantId, array $row): array
    {
        $aad = "{$tenantId}|gemeseg_excel|{$row['fuente']}|{$row['hoja']}|{$row['fila']}";
        $json = $tenantEncryption->decrypt((string) $row['raw_json_cifrado'], $aad);
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            throw new DomainException('Payload de staging invalido.');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function cedulaFromPayload(array $payload): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($payload['data']['CEDULA'] ?? '')) ?? '';
        if ($digits === '' && isset($payload['data']['CEDULA_2'])) {
            $digits = preg_replace('/\D+/', '', (string) $payload['data']['CEDULA_2']) ?? '';
        }
        if (strlen($digits) === 9) {
            $digits = '0' . $digits;
        }
        if (strlen($digits) !== 10) {
            return '';
        }

        try {
            return Cedula::fromString($digits)->valor;
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findLatestActiveFicha(string $tenantId, string $cedulaHash): ?array
    {
        return $this->db->fetchOne(
            "SELECT *
             FROM fichas
             WHERE tenant_id = :tenant_id
               AND cedula_hash = :cedula_hash
               AND estado <> 'ANULADA'
             ORDER BY created_at DESC
             LIMIT 1",
            ['tenant_id' => $tenantId, 'cedula_hash' => $cedulaHash],
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mapFichaData(array $data): array
    {
        $fechaNacimiento = $this->dateValue($this->first($data, ['FECHA_NACIMIENTO']));
        $fechaIngreso = $this->dateValue($this->first($data, ['FECHA_INGRESO']));
        $reentrenamiento = $this->dateValue($this->first($data, ['REENTRENAMIENTO_ACT_2_A_NOS', 'REENTRENAMIENTO', 'REENT']));
        $email = $this->emailValue($this->first($data, ['CORREO_ELECTRONICO', 'EMAIL']));

        $mapped = [
            'nombres' => $this->textValue($this->first($data, ['NOMBRES']), 120),
            'apellidos' => $this->textValue($this->first($data, ['APELLIDOS']), 120),
            'fecha_nacimiento' => $fechaNacimiento,
            'fecha_emision' => $fechaIngreso,
            'genero' => 'PREFIERO_NO_DECIR',
            'procedencia' => $this->textValue($this->first($data, ['PROCEDENCIA']), 150),
            'celular' => $this->textValue($this->first($data, ['NUMERO_CELULAR', 'CELULAR', 'TELEFONO']), 30),
            'email' => $email,
            'domicilio_calle' => $this->textValue($this->first($data, ['DIRECCI_ON', 'DIRECCION']), 255),
            'estado_civil' => $this->estadoCivilValue($this->first($data, ['ESTADO_CIVIL'])),
            'cargas_familiares' => $this->intValue($this->first($data, ['CARGAS_FAMILIARES']), 0, 99),
            'tipo_sangre' => $this->bloodValue($this->first($data, ['TIPO_SANGRE'])),
            'instruccion_secundaria' => $this->present($this->first($data, ['BACHILLERATO', 'TITULO_BACHILL']))
                ? 'Bachillerato registrado en Excel GEMESEG'
                : null,
            'fecha_ultimo_reentrenamiento' => $reentrenamiento,
            'talla_uniforme' => $this->textValue($this->first($data, ['TALLA_UNIFORME']), 50),
            'talla_zapatos' => $this->textValue($this->first($data, ['TALLA_ZAPATOS']), 30),
        ];

        return array_filter($mapped, static fn(mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param array<string, mixed> $mapped
     */
    private function canCreateFicha(array $mapped): bool
    {
        return !empty($mapped['nombres'])
            && !empty($mapped['apellidos'])
            && !empty($mapped['fecha_nacimiento']);
    }

    /**
     * @param array<string, mixed> $mapped
     */
    private function createFicha(string $tenantId, array $mapped, string $cedula, string $cedulaHash, string $userId): string
    {
        $fichaId = IdGenerator::ulid();
        $now = Clock::nowString();
        $data = [
            'id' => $fichaId,
            'tenant_id' => $tenantId,
            'correlativo' => $this->nextCorrelativo($tenantId),
            'tipo_ficha' => 'INGRESO',
            'estado' => 'BORRADOR',
            'fecha_emision' => (string) ($mapped['fecha_emision'] ?? Clock::now()->format('Y-m-d')),
            'fecha_revision' => null,
            'nombres' => (string) $mapped['nombres'],
            'apellidos' => (string) $mapped['apellidos'],
            'cedula_hash' => $cedulaHash,
            'cedula_cifrada' => $this->encryption->encrypt($cedula),
            'genero' => (string) ($mapped['genero'] ?? 'PREFIERO_NO_DECIR'),
            'fecha_nacimiento' => (string) $mapped['fecha_nacimiento'],
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ($this->optionalFichaFields() as $field) {
            $data[$field] = $mapped[$field] ?? null;
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $this->db->execute(
            "INSERT INTO fichas ({$columns}) VALUES ({$placeholders})",
            $data,
        );

        return $fichaId;
    }

    /**
     * @param array<string, mixed> $ficha
     * @param array<string, mixed> $mapped
     */
    private function updateFichaMissingFields(string $tenantId, array $ficha, array $mapped, string $userId): bool
    {
        $updates = [];
        foreach ($this->updatableFichaFields() as $field) {
            if (!array_key_exists($field, $mapped)) {
                continue;
            }

            $current = $ficha[$field] ?? null;
            if ($current === null || trim((string) $current) === '') {
                $updates[$field] = $mapped[$field];
            }
        }

        if ($updates === []) {
            return false;
        }

        $updates['updated_by'] = $userId;
        $updates['updated_at'] = Clock::nowString();
        $sets = [];
        foreach (array_keys($updates) as $field) {
            $sets[] = "{$field} = :{$field}";
        }
        $updates['tenant_id'] = $tenantId;
        $updates['id'] = (string) $ficha['id'];

        $this->db->execute(
            'UPDATE fichas SET ' . implode(', ', $sets) . ' WHERE id = :id AND tenant_id = :tenant_id',
            $updates,
        );

        return true;
    }

    private function nextCorrelativo(string $tenantId): string
    {
        $year = Clock::now()->format('Y');
        $prefix = "{$year}-";
        $last = $this->db->fetchScalar(
            'SELECT correlativo FROM fichas WHERE tenant_id = :tenant_id AND correlativo LIKE :prefix ORDER BY correlativo DESC LIMIT 1',
            ['tenant_id' => $tenantId, 'prefix' => "{$prefix}%"],
        );
        $lastNumber = $last !== null ? (int) substr((string) $last, strlen($prefix)) : 0;

        return $prefix . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    private function optionalFichaFields(): array
    {
        return [
            'procedencia',
            'celular',
            'email',
            'domicilio_calle',
            'estado_civil',
            'cargas_familiares',
            'tipo_sangre',
            'instruccion_secundaria',
            'fecha_ultimo_reentrenamiento',
            'talla_uniforme',
            'talla_zapatos',
        ];
    }

    /**
     * @return list<string>
     */
    private function updatableFichaFields(): array
    {
        return array_merge(
            ['nombres', 'apellidos', 'fecha_nacimiento'],
            $this->optionalFichaFields(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processCourses(string $tenantId, string $fichaId, array $data): int
    {
        $created = 0;
        $seenCodes = [];
        foreach ($this->courseColumnMap() as $column => $code) {
            $value = (string) ($data[$column] ?? '');
            if (!$this->present($value) || isset($seenCodes[$code])) {
                continue;
            }

            $cursoId = $this->catalogId('catalogo_cursos', $code);
            if ($cursoId === null) {
                continue;
            }

            $stmt = $this->db->execute(
                'INSERT IGNORE INTO fichas_cursos (ficha_id, curso_id, tenant_id, fecha_obtencion, observacion)
                 VALUES (:ficha_id, :curso_id, :tenant_id, :fecha, :observacion)',
                [
                    'ficha_id' => $fichaId,
                    'curso_id' => $cursoId,
                    'tenant_id' => $tenantId,
                    'fecha' => $this->dateValue($value),
                    'observacion' => 'Importado desde Excel GEMESEG.',
                ],
            );
            if ($stmt->rowCount() > 0) {
                $created++;
            }
            $seenCodes[$code] = true;
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processVaccines(string $tenantId, string $fichaId, array $data): int
    {
        $created = 0;
        foreach ($this->vaccineColumnMap() as $column => $code) {
            $value = (string) ($data[$column] ?? '');
            if (!$this->present($value)) {
                continue;
            }

            $vacunaId = $this->catalogId('catalogo_vacunas', $code);
            if ($vacunaId === null) {
                continue;
            }

            $stmt = $this->db->execute(
                'INSERT IGNORE INTO fichas_vacunas
                    (id, tenant_id, ficha_id, vacuna_id, fecha_aplicacion, evidencia_documento_id, observacion, created_at, updated_at)
                 VALUES
                    (:id, :tenant_id, :ficha_id, :vacuna_id, :fecha, NULL, :observacion, :created_at, :updated_at)',
                [
                    'id' => IdGenerator::ulid(),
                    'tenant_id' => $tenantId,
                    'ficha_id' => $fichaId,
                    'vacuna_id' => $vacunaId,
                    'fecha' => $this->dateValue($value),
                    'observacion' => 'Importado desde Excel GEMESEG.',
                    'created_at' => Clock::nowString(),
                    'updated_at' => Clock::nowString(),
                ],
            );
            if ($stmt->rowCount() > 0) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processDocuments(string $tenantId, string $fichaId, string $sheet, array $data, string $userId): int
    {
        $created = 0;
        $expedienteId = null;
        $isMissingSheet = $this->normalizeKey($sheet) === 'DOCUMENTOS_FALTANTES';

        foreach ($this->documentColumnMap() as $column => $code) {
            $value = (string) ($data[$column] ?? '');
            if (!$this->present($value)) {
                continue;
            }

            $tipoId = $this->documentTypeId($tenantId, $code);
            if ($tipoId === null) {
                continue;
            }

            $expedienteId ??= $this->ensureExpediente($tenantId, $fichaId, $userId);
            if ($this->documentExists($tenantId, $expedienteId, $tipoId)) {
                continue;
            }

            $estado = $isMissingSheet ? 'PENDIENTE' : 'EN_REVISION';
            $observacion = $isMissingSheet
                ? 'Detectado como faltante en Excel GEMESEG.'
                : 'Detectado en Excel GEMESEG, pendiente de adjuntar archivo fisico/digital.';

            $this->db->execute(
                'INSERT INTO expediente_documentos
                    (id, tenant_id, expediente_id, documento_tipo_id, estado, observacion_revisor,
                     subido_por, subido_at, created_at, updated_at)
                 VALUES
                    (:id, :tenant_id, :expediente_id, :documento_tipo_id, :estado, :observacion,
                     NULL, NULL, :created_at, :updated_at)',
                [
                    'id' => IdGenerator::ulid(),
                    'tenant_id' => $tenantId,
                    'expediente_id' => $expedienteId,
                    'documento_tipo_id' => $tipoId,
                    'estado' => $estado,
                    'observacion' => $observacion,
                    'created_at' => Clock::nowString(),
                    'updated_at' => Clock::nowString(),
                ],
            );
            $created++;
        }

        return $created;
    }

    private function ensureExpediente(string $tenantId, string $fichaId, string $userId): string
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM expedientes WHERE tenant_id = :tenant_id AND ficha_id = :ficha_id',
            ['tenant_id' => $tenantId, 'ficha_id' => $fichaId],
        );
        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $counts = $this->db->fetchOne(
            "SELECT
                SUM(CASE WHEN dt.bloque = 'A' THEN COALESCE(dt.numero_documentos, 1) ELSE 0 END) AS bloque_a_total,
                SUM(CASE WHEN dt.bloque = 'B' THEN COALESCE(dt.numero_documentos, 1) ELSE 0 END) AS bloque_b_total
             FROM documento_tipos dt
             WHERE (dt.tenant_id = :tid_outer OR dt.tenant_id IS NULL)
               AND dt.activo = 1
               AND dt.obligatorio = 1
               AND NOT EXISTS (
                   SELECT 1 FROM documento_tipos dt2
                   WHERE dt2.tenant_id = :tid_inner
                     AND dt2.codigo = dt.codigo
                     AND dt.tenant_id IS NULL
               )",
            ['tid_outer' => $tenantId, 'tid_inner' => $tenantId],
        ) ?? [];

        $id = IdGenerator::ulid();
        $now = Clock::nowString();
        $this->db->execute(
            'INSERT INTO expedientes
                (id, tenant_id, ficha_id, estado, bloque_a_total, bloque_a_aceptados,
                 bloque_b_total, bloque_b_aceptados, bloque_b_desbloqueado,
                 created_by, updated_by, created_at, updated_at)
             VALUES
                (:id, :tenant_id, :ficha_id, :estado, :bloque_a_total, 0,
                 :bloque_b_total, 0, 0,
                 :created_by, :updated_by, :created_at, :updated_at)',
            [
                'id' => $id,
                'tenant_id' => $tenantId,
                'ficha_id' => $fichaId,
                'estado' => 'INCOMPLETO',
                'bloque_a_total' => (int) ($counts['bloque_a_total'] ?? 0),
                'bloque_b_total' => (int) ($counts['bloque_b_total'] ?? 0),
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        return $id;
    }

    private function documentExists(string $tenantId, string $expedienteId, string $tipoId): bool
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM expediente_documentos
             WHERE tenant_id = :tenant_id
               AND expediente_id = :expediente_id
               AND documento_tipo_id = :documento_tipo_id',
            [
                'tenant_id' => $tenantId,
                'expediente_id' => $expedienteId,
                'documento_tipo_id' => $tipoId,
            ],
        ) > 0;
    }

    private function documentTypeId(string $tenantId, string $code): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT id
             FROM documento_tipos
             WHERE codigo = :codigo
               AND activo = 1
               AND (tenant_id = :tenant_id OR tenant_id IS NULL)
             ORDER BY tenant_id IS NULL ASC
             LIMIT 1',
            ['codigo' => $code, 'tenant_id' => $tenantId],
        );

        return $row !== null ? (string) $row['id'] : null;
    }

    private function catalogId(string $table, string $code): ?string
    {
        if (!in_array($table, ['catalogo_cursos', 'catalogo_vacunas'], true)) {
            return null;
        }

        $row = $this->db->fetchOne(
            "SELECT id FROM {$table} WHERE codigo = :codigo AND activo = 1 LIMIT 1",
            ['codigo' => $code],
        );

        return $row !== null ? (string) $row['id'] : null;
    }

    private function markRow(string $rowId, string $estado, ?string $entityId, ?string $message): void
    {
        if ($rowId === '') {
            return;
        }

        $this->db->execute(
            'UPDATE importacion_excel_registros
             SET estado = :estado,
                 entidad_id = :entidad_id,
                 error_mensaje = :error_mensaje
             WHERE id = :id',
            [
                'estado' => $estado,
                'entidad_id' => $entityId,
                'error_mensaje' => $message !== null ? mb_substr($message, 0, 500) : null,
                'id' => $rowId,
            ],
        );
    }

    private function normalizeSource(string $source): string
    {
        return mb_substr(trim(str_replace(["\r", "\n"], ' ', $source)), 0, 120);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     */
    private function first(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function present(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $normalized = $this->normalizeKey($value);
        return !in_array($normalized, [
            'NO',
            'N',
            'NA',
            'N_A',
            'NO_APLICA',
            'NINGUNO',
            'NINGUNA',
            'PENDIENTE',
            'FALTA',
            'FALTANTE',
            'NO_TIENE',
            'SIN',
            '0',
        ], true);
    }

    private function normalizeKey(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', '_', $value) ?? $value;
        return trim(preg_replace('/_+/', '_', $value) ?? $value, '_');
    }

    private function textValue(string $value, int $max): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
        return $value !== '' ? mb_substr($value, 0, $max) : null;
    }

    private function emailValue(string $value): ?string
    {
        $value = trim($value);
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_substr($value, 0, 190) : null;
    }

    private function intValue(string $value, int $min, int $max): ?int
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            $number = (int) round((float) $value);
        } else {
            $digits = preg_replace('/\D+/', '', $value) ?? '';
            if ($digits === '') {
                return null;
            }
            $number = (int) $digits;
        }

        return $number >= $min && $number <= $max ? $number : null;
    }

    private function dateValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $this->validDate($value) ? $value : null;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m) === 1) {
            $date = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            return $this->validDate($date) ? $date : null;
        }

        return null;
    }

    private function validDate(string $date): bool
    {
        [$year, $month, $day] = array_map('intval', explode('-', $date));
        return checkdate($month, $day, $year);
    }

    private function estadoCivilValue(string $value): ?string
    {
        $key = $this->normalizeKey($value);
        return match (true) {
            str_contains($key, 'SOLTER') => 'SOLTERO',
            str_contains($key, 'CASAD') => 'CASADO',
            str_contains($key, 'UNION') || str_contains($key, 'UNIDO') => 'UNION_LIBRE',
            str_contains($key, 'DIVOR') => 'DIVORCIADO',
            str_contains($key, 'VIUD') => 'VIUDO',
            default => null,
        };
    }

    private function bloodValue(string $value): ?string
    {
        $key = strtoupper(str_replace([' ', 'RH'], '', trim($value)));
        $key = str_replace('0', 'O', $key);
        return in_array($key, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true) ? $key : null;
    }

    private function shortError(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message)) ?? trim($message);
        return $message !== '' ? mb_substr($message, 0, 500) : 'Error procesando fila.';
    }

    /**
     * @return array<string, string>
     */
    private function courseColumnMap(): array
    {
        return [
            'NIVEL_1' => 'NIVEL_1',
            'CERTIF_120_H' => 'NIVEL_1',
            'CREDENCIAL_GUARDIA' => 'NIVEL_1',
            'NIVEL_2' => 'NIVEL_2',
            'CERTIF_NIVEL_2' => 'NIVEL_2',
            'CREDENCIAL_NIVEL_2' => 'NIVEL_2',
            'REENT' => 'REENTRENAMIENTO',
            'REENTRENAMIENTO' => 'REENTRENAMIENTO',
            'REENTRENAMIENTO_ACT_2_A_NOS' => 'REENTRENAMIENTO',
            'CERTIF_SUPERVISOR' => 'SUPERVISOR',
            'CREDENCIAL_SUPERVISOR' => 'SUPERVISOR',
            'ATENCION_Y_SERVICIO_AL_CLIENTE' => 'ATENCION_CLIENTE',
            'COMPUTACION_BASICA' => 'COMPUTACION_BASICA',
            'CONOCIMIENTOS_DE_EQUIPOS_DE_PROTECCION' => 'EQUIPOS_PROTECCION',
            'CONOCIMIENTO_Y_MANEJO_DE_ARMAS_POLIGONO_VIRTUAL' => 'ARMAS_POLIGONO_VIRTUAL',
            'CONOCIMIENTOS_TECNICOS' => 'CONOCIMIENTOS_TECNICOS',
            'DEFENSA_PERSONAL' => 'DEFENSA_PERSONAL',
            'DEFENSA_PERSONAL_PRIMEROS_AUXILIOS_Y_MANEJO_DE_ARMAS' => 'DEFENSA_AUX_ARMAS',
            'DESARROLLO_HUMANO' => 'DESARROLLO_HUMANO',
            'EMPLEO_TACTICO_DE_ARMAS' => 'EMPLEO_TACTICO_ARMAS',
            'ESPECIALIZACION_DE_BARES_Y_RESTAURANTE' => 'BARES_RESTAURANTE',
            'ETICA_Y_RELACIONES_HUMANAS' => 'ETICA_RELACIONES_HUMANAS',
            'LEGISLACION' => 'LEGISLACION',
            'MANEJO_CRISIS_Y_CONTROL_EMERGENCIAS' => 'CRISIS_EMERGENCIAS',
            'MANEJO_DE_RIESGOS' => 'MANEJO_RIESGOS',
            'NORMATIVA_LEGAL' => 'NORMATIVA_LEGAL',
            'NORMATIVA_VIGENTE_EN_SEGURIDAD_Y_TRANSITO' => 'NORMATIVA_SEG_TRANSITO',
            'OPERACIONES_DE_SEGURIDAD' => 'OPERACIONES_SEGURIDAD',
            'OPERADOR_DE_CONSOLA_CCTV' => 'CCTV_CONSOLA',
            'PREVENCION_DE_RIESGOS' => 'PREVENCION_RIESGOS',
            'PRIMEROS_AUXILIOS' => 'PRIMEROS_AUXILIOS',
            'PROCEDIMIENTOS_DE_SEGURIDAD' => 'PROCEDIMIENTOS_SEGURIDAD',
            'PROTECCION_A_EJECUTIVOS_CARGA_CRITICA_ESPECIES_MONETARIAS' => 'PROTECCION_EJECUTIVOS_CARGA',
            'RELACIONES_HUMANAS' => 'RELACIONES_HUMANAS',
            'SEGURIDAD_DE_LA_COMUNICACI_ON_E_INFORMACION' => 'SEG_COMUNICACION_INFO',
            'SEGURIDAD_CIUDADANA' => 'SEGURIDAD_CIUDADANA',
            'SEGURIDAD_ELECTRONICA' => 'SEGURIDAD_ELECTRONICA',
            'SEGURIDAD_FISICA' => 'SEGURIDAD_FISICA',
            'SEGURIDAD_PRIVADA_APLICADA_A_LA_SEGURIDAD_CIUDADANA' => 'SEG_PRIVADA_CIUDADANA',
            'SISTEMAS_DE_COMUNICACI_ON_Y_MONITOREO' => 'SISTEMAS_COM_MONITOREO',
            'TECNICAS_DE_MONITOREO' => 'TECNICAS_MONITOREO',
            'TIRO_VIRTUL' => 'TIRO_VIRTUAL',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function vaccineColumnMap(): array
    {
        return [
            'HB_HEPATITIS_B' => 'HB',
            'DT_DIFTERIA_Y_TETANO' => 'DT',
            'SR_SARAMPION_Y_RUBIOLA' => 'SR',
            'FA_FIEBRE_AMARILLA' => 'FA',
            'INFLUENZA' => 'INFLUENZA',
            'COVID' => 'COVID',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function documentColumnMap(): array
    {
        return [
            'HOJA_INGRESO' => 'HOJA_INGRESO',
            'VISIT_DOMIC' => 'VISITA_DOMICILIARIA',
            'HOJA_DE_VIDA' => 'HOJA_VIDA',
            'CEDULA_2' => 'CEDULA_COLOR',
            'CERT_VOT' => 'PAPELETA_VOTACION',
            'TITULO_BACHILL' => 'TITULO_BACHILLER',
            'BACHILLERATO' => 'TITULO_BACHILLER',
            'NIVEL_1' => 'CERT_NIVEL1',
            'CERTIF_120_H' => 'CERT_NIVEL1',
            'CREDENCIAL_GUARDIA' => 'CRED_NIVEL1',
            'NIVEL_2' => 'CERT_NIVEL2',
            'CERTIF_NIVEL_2' => 'CERT_NIVEL2',
            'CREDENCIAL_NIVEL_2' => 'CRED_NIVEL2',
            'REENT' => 'CERT_REENTRENAMIENTO',
            'REENTRENAMIENTO' => 'CERT_REENTRENAMIENTO',
            'REENTRENAMIENTO_ACT_2_A_NOS' => 'CERT_REENTRENAMIENTO',
            'CERT_MANEJO_ARMA' => 'DIPLOMA_ARMAS',
            'EXAM_FISICO' => 'EXAMEN_SALUD',
            'DROG_Y_ALCOHOL' => 'EXAMEN_DROGAS',
            'EXAMEN_PSICO' => 'CERT_PSICOLOGICO',
            'CERT_VIOL_INTRA' => 'CERT_NO_VIOLENCIA',
            'RECORD_POLIC' => 'ANTECEDENTES_PENALES',
            'AFIS' => 'CERT_AFIS',
            'TIPO_SANGRE' => 'TIPIFICACION_SANGRE',
            'CARNET_MSP' => 'CARNET_VACUNACION',
            'CERT_TRABAJO' => 'CERT_TRABAJO',
            'CERT_HONORAB' => 'CERT_HONORABILIDAD',
            'HIST_IESS' => 'HISTORIAL_IESS',
            'HISTORIA_LABORAL' => 'HISTORIAL_IESS',
            'PLANILLA_LUZ' => 'PLANILLA_BASICO',
            'CONTRATO' => 'CONTRATO_FIRMADO',
            'CONTRATOS' => 'CONTRATO_FIRMADO',
            'IESS' => 'AVISO_ENTRADA_IESS',
            'MRL' => 'REGISTRO_SUT',
            'SICOSEP' => 'SICOSEP',
            'CERT_FUERZAS_ARM' => 'CERT_FUERZAS_ARMADAS',
        ];
    }
}
