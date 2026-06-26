<?php
declare(strict_types=1);

namespace DigiSecure\Infrastructure\Import;

use DigiSecure\Ficha\Domain\ValueObject\Cedula;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;
use Throwable;

final class GemesegExcelImportService
{
    private const MAX_UPLOAD_BYTES = 20971520; // 20 MB
    private const TEMP_TTL_SECONDS = 86400; // 24 hours

    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
        private readonly Encryption $encryption,
        private readonly AuditLogger $audit,
        private readonly XlsxReader $reader,
        private readonly string $tempPath,
    ) {
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int}|null $file
     * @return array{token: string, path: string, source: string}
     */
    public function storeUploadedFile(?array $file, ?string $source = null): array
    {
        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new DomainException('Selecciona un archivo Excel valido.');
        }

        $originalName = trim((string) ($file['name'] ?? ''));
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($originalName === '' || $tmpName === '' || !is_file($tmpName)) {
            throw new DomainException('No se pudo leer el archivo subido.');
        }
        if ($size <= 0 || $size > self::MAX_UPLOAD_BYTES) {
            throw new DomainException('El archivo Excel supera el tamano maximo permitido de 20 MB.');
        }
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new DomainException('Solo se aceptan archivos .xlsx.');
        }

        $this->ensureTempPath();
        $this->purgeExpiredTempFiles();

        $token = bin2hex(random_bytes(16));
        $target = $this->tempPath . DIRECTORY_SEPARATOR . $token . '.xlsx';
        if (!move_uploaded_file($tmpName, $target)) {
            if (!@rename($tmpName, $target)) {
                throw new DomainException('No se pudo guardar temporalmente el Excel.');
            }
        }

        @chmod($target, 0600);

        return [
            'token' => $token,
            'path' => $target,
            'source' => $this->normalizeSource($source ?: $originalName),
        ];
    }

    /**
     * @return array{source_path: string, sheets: list<array{name: string, header_row: int, headers: array<int, array{name: string, key: string}>, rows: list<array{row: int, entity_tipo: string, data: array<string, string>, cedula_digits: ?string, cedula_hash: ?string, cedula_valid: bool, cedula_padded: bool, issues: list<string>}>, stats: array<string, int>}>, totals: array<string, int>}
     */
    public function analyzeFile(string $path, ?int $limit = null): array
    {
        $workbook = $this->reader->read($path);
        $analysis = ['source_path' => $workbook['path'], 'sheets' => [], 'totals' => $this->emptyTotals()];

        foreach ($workbook['sheets'] as $sheet) {
            $headerRow = $this->knownHeaderRow($sheet['name']) ?? $this->detectHeaderRow($sheet['rows']);
            $headers = $this->extractHeaders($sheet['rows'], $headerRow);
            $entityTipo = $this->sheetEntityType($sheet['name']);
            $rows = [];
            $stats = [
                'data_rows' => 0,
                'with_cedula' => 0,
                'valid_cedula' => 0,
                'invalid_cedula' => 0,
                'missing_cedula' => 0,
                'padded_cedula' => 0,
                'duplicate_cedula_in_sheet' => 0,
            ];
            $seenCedulas = [];

            foreach ($sheet['rows'] as $row) {
                if ($row['number'] <= $headerRow) {
                    continue;
                }

                $data = $this->rowData($row['cells'], $headers);
                if ($this->isEmptyDataRow($data)) {
                    continue;
                }

                $cedulaInfo = $this->cedulaInfo($data);
                $issues = [];
                if ($cedulaInfo['digits'] === null) {
                    $issues[] = 'cedula_missing';
                    $stats['missing_cedula']++;
                } else {
                    $stats['with_cedula']++;
                    if ($cedulaInfo['valid']) {
                        $stats['valid_cedula']++;
                    } else {
                        $issues[] = 'cedula_invalid';
                        $stats['invalid_cedula']++;
                    }
                    if ($cedulaInfo['padded']) {
                        $issues[] = 'cedula_padded_from_9_digits';
                        $stats['padded_cedula']++;
                    }
                    if ($cedulaInfo['hash'] !== null) {
                        if (isset($seenCedulas[$cedulaInfo['hash']])) {
                            $issues[] = 'duplicate_cedula_in_sheet';
                            $stats['duplicate_cedula_in_sheet']++;
                        }
                        $seenCedulas[$cedulaInfo['hash']] = true;
                    }
                }

                $rows[] = [
                    'row' => $row['number'],
                    'entity_tipo' => $entityTipo,
                    'data' => $data,
                    'cedula_digits' => $cedulaInfo['digits'],
                    'cedula_hash' => $cedulaInfo['hash'],
                    'cedula_valid' => $cedulaInfo['valid'],
                    'cedula_padded' => $cedulaInfo['padded'],
                    'issues' => $issues,
                ];

                $stats['data_rows']++;
                if ($limit !== null && $stats['data_rows'] >= $limit) {
                    break;
                }
            }

            foreach ($stats as $key => $value) {
                if (isset($analysis['totals'][$key])) {
                    $analysis['totals'][$key] += $value;
                }
            }
            $analysis['totals']['sheets']++;

            $analysis['sheets'][] = [
                'name' => $sheet['name'],
                'header_row' => $headerRow,
                'headers' => $headers,
                'rows' => $rows,
                'stats' => $stats,
            ];
        }

        return $analysis;
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array{schema_ok: bool, existing_fichas: int, existing_staging: int}
     */
    public function databaseValidation(array $analysis, string $source): array
    {
        $this->validateStagingSchema();
        $tenantId = $this->tenantContext->requireTenantId();

        return [
            'schema_ok' => true,
            'existing_fichas' => count($this->existingFichaHashes($tenantId, $this->uniqueCedulaHashes($analysis))),
            'existing_staging' => $this->existingStagingCount($tenantId, $source),
        ];
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array{inserted: int, skipped: int, errors: int}
     */
    public function stageAnalysis(array $analysis, string $source, string $userId, string $ip): array
    {
        $this->validateStagingSchema();
        $tenantId = $this->tenantContext->requireTenantId();
        $source = $this->normalizeSource($source);
        $tenantEncryption = $this->encryption->deriveTenantKey($tenantId);
        $inserted = 0;
        $skipped = 0;
        $errors = 0;

        $this->db->transactional(function (PdoConnection $db) use (
            $analysis,
            $tenantEncryption,
            $tenantId,
            $userId,
            $source,
            &$inserted,
            &$skipped,
            &$errors
        ): void {
            foreach ($analysis['sheets'] as $sheet) {
                foreach ($sheet['rows'] as $row) {
                    try {
                        $payload = [
                            'source' => $source,
                            'sheet' => $sheet['name'],
                            'row' => $row['row'],
                            'entity_tipo' => $row['entity_tipo'],
                            'cedula_valid' => $row['cedula_valid'],
                            'cedula_padded' => $row['cedula_padded'],
                            'issues' => $row['issues'],
                            'data' => $row['data'],
                        ];

                        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if (!is_string($json)) {
                            throw new \RuntimeException('No se pudo serializar fila.');
                        }

                        $aad = "{$tenantId}|gemeseg_excel|{$source}|{$sheet['name']}|{$row['row']}";
                        $now = gmdate('Y-m-d H:i:s') . '.000000';
                        $stmt = $db->execute(
                            'INSERT INTO importacion_excel_registros
                                (id, tenant_id, fuente, hoja, fila, cedula_hash, raw_json_cifrado, raw_json_hash,
                                 entidad_tipo, entidad_id, estado, error_mensaje, importado_por, importado_at, created_at)
                             VALUES
                                (:id, :tenant_id, :fuente, :hoja, :fila, :cedula_hash, :raw_json_cifrado, :raw_json_hash,
                                 :entidad_tipo, NULL, :estado, :error_mensaje, :importado_por, :importado_at, :created_at)
                             ON DUPLICATE KEY UPDATE id = id',
                            [
                                'id' => IdGenerator::ulid(),
                                'tenant_id' => $tenantId,
                                'fuente' => $source,
                                'hoja' => $sheet['name'],
                                'fila' => $row['row'],
                                'cedula_hash' => $row['cedula_hash'],
                                'raw_json_cifrado' => $tenantEncryption->encrypt($json, $aad),
                                'raw_json_hash' => hash('sha256', $json),
                                'entidad_tipo' => $row['entity_tipo'],
                                'estado' => $row['cedula_valid'] ? 'pendiente' : 'error',
                                'error_mensaje' => $row['cedula_valid'] ? null : implode(',', $row['issues']),
                                'importado_por' => $userId,
                                'importado_at' => $now,
                                'created_at' => $now,
                            ],
                        );

                        if ($stmt->rowCount() > 0) {
                            $inserted++;
                        } else {
                            $skipped++;
                        }
                    } catch (Throwable) {
                        $errors++;
                    }
                }
            }
        });

        $this->audit->log(
            'importacion.gemeseg.staging',
            $userId,
            'importacion_excel',
            null,
            [
                'fuente' => $source,
                'inserted' => $inserted,
                'skipped' => $skipped,
                'errors' => $errors,
                'rows' => (int) ($analysis['totals']['data_rows'] ?? 0),
            ],
            $errors > 0 ? 'warning' : 'info',
            $ip,
        );

        return ['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentStagingSources(int $limit = 10): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        return $this->db->fetchAll(
            "SELECT
                fuente,
                COUNT(*) AS total,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN estado = 'error' THEN 1 ELSE 0 END) AS errores,
                SUM(CASE WHEN estado = 'omitido' THEN 1 ELSE 0 END) AS omitidos,
                SUM(CASE WHEN estado = 'procesado' THEN 1 ELSE 0 END) AS procesados,
                MAX(created_at) AS ultimo_at
             FROM importacion_excel_registros
             WHERE tenant_id = :tenant_id
             GROUP BY fuente
             ORDER BY MAX(created_at) DESC
             LIMIT :limit",
            ['tenant_id' => $tenantId, 'limit' => $limit],
        );
    }

    public function tempPathFromToken(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new DomainException('Token temporal invalido. Vuelve a subir el Excel.');
        }

        $path = $this->tempPath . DIRECTORY_SEPARATOR . $token . '.xlsx';
        if (!is_file($path)) {
            throw new DomainException('El archivo temporal ya no esta disponible. Vuelve a subir el Excel.');
        }

        return $path;
    }

    public function cleanupToken(string $token): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return;
        }
        $path = $this->tempPath . DIRECTORY_SEPARATOR . $token . '.xlsx';
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function normalizeSource(string $source): string
    {
        $source = trim(str_replace(["\r", "\n"], ' ', $source));
        if ($source === '') {
            $source = 'GEMESEG Excel';
        }
        return mb_substr($source, 0, 120);
    }

    private function ensureTempPath(): void
    {
        if (!is_dir($this->tempPath)) {
            if (!@mkdir($this->tempPath, 0770, true) && !is_dir($this->tempPath)) {
                throw new DomainException('No se pudo crear el directorio temporal de importaciones.');
            }
        }
        @chmod($this->tempPath, 0770);
    }

    private function purgeExpiredTempFiles(): void
    {
        $files = glob($this->tempPath . DIRECTORY_SEPARATOR . '*.xlsx');
        if (!is_array($files)) {
            return;
        }

        $expiresBefore = time() - self::TEMP_TTL_SECONDS;
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $modifiedAt = @filemtime($file);
            if ($modifiedAt !== false && $modifiedAt < $expiresBefore) {
                @unlink($file);
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function emptyTotals(): array
    {
        return [
            'sheets' => 0,
            'data_rows' => 0,
            'with_cedula' => 0,
            'valid_cedula' => 0,
            'invalid_cedula' => 0,
            'missing_cedula' => 0,
            'padded_cedula' => 0,
            'duplicate_cedula_in_sheet' => 0,
        ];
    }

    private function knownHeaderRow(string $sheetName): ?int
    {
        return match ($this->normalizeHeader($sheetName)) {
            'GUARDIAS_GEMESEG' => 3,
            'SALIDAS' => 3,
            'DOCUMENTACION_ACTUALIZADA' => 1,
            'DOCUMENTOS_FALTANTES' => 22,
            'CURSOS' => 3,
            default => null,
        };
    }

    /**
     * @param list<array{number: int, cells: array<int, string>}> $rows
     */
    private function detectHeaderRow(array $rows): int
    {
        $bestRow = 1;
        $bestScore = 0;
        foreach ($rows as $row) {
            if ($row['number'] > 30) {
                break;
            }
            $score = 0;
            foreach ($row['cells'] as $value) {
                $key = $this->normalizeHeader($value);
                $score += in_array($key, ['APELLIDOS', 'NOMBRES', 'CEDULA', 'PUESTO', 'FECHA_INGRESO'], true)
                    ? 10
                    : ($key !== '' ? 1 : 0);
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $row['number'];
            }
        }

        return $bestRow;
    }

    /**
     * @param list<array{number: int, cells: array<int, string>}> $rows
     * @return array<int, array{name: string, key: string}>
     */
    private function extractHeaders(array $rows, int $headerRow): array
    {
        $headerCells = [];
        foreach ($rows as $row) {
            if ($row['number'] === $headerRow) {
                $headerCells = $row['cells'];
                break;
            }
        }

        $headers = [];
        $seen = [];
        foreach ($headerCells as $column => $name) {
            $key = $this->normalizeHeader($name);
            if ($key === '') {
                $key = 'COL_' . $column;
                $name = '';
            }
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            if ($seen[$key] > 1) {
                $key .= '_' . $seen[$key];
            }
            $headers[$column] = ['name' => trim($name), 'key' => $key];
        }

        ksort($headers);
        return $headers;
    }

    /**
     * @param array<int, string> $cells
     * @param array<int, array{name: string, key: string}> $headers
     * @return array<string, string>
     */
    private function rowData(array $cells, array $headers): array
    {
        $data = [];
        foreach ($headers as $column => $header) {
            $data[$header['key']] = $this->normalizeCellForHeader($header['key'], trim((string) ($cells[$column] ?? '')));
        }
        return $data;
    }

    /**
     * @param array<string, string> $data
     */
    private function isEmptyDataRow(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (!str_starts_with($key, 'COL_') && trim($value) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string, string> $data
     * @return array{digits: ?string, hash: ?string, valid: bool, padded: bool}
     */
    private function cedulaInfo(array $data): array
    {
        $raw = $data['CEDULA'] ?? $data['CEDULA_2'] ?? '';
        if ($raw === '') {
            foreach ($data as $key => $value) {
                if ($key === 'C_EDULA' || preg_match('/^C_?EDULA(_\d+)?$/', $key) === 1) {
                    $raw = $value;
                    break;
                }
            }
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return ['digits' => null, 'hash' => null, 'valid' => false, 'padded' => false];
        }

        $candidate = $digits;
        $padded = false;
        if (strlen($digits) === 9) {
            $candidate = '0' . $digits;
            $padded = true;
        }

        $valid = false;
        try {
            Cedula::fromString($candidate);
            $valid = true;
        } catch (Throwable) {
            $valid = false;
        }

        return [
            'digits' => $candidate,
            'hash' => strlen($candidate) === 10 ? hash('sha256', $candidate) : null,
            'valid' => $valid,
            'padded' => $padded,
        ];
    }

    private function sheetEntityType(string $sheetName): string
    {
        return match ($this->normalizeHeader($sheetName)) {
            'GUARDIAS_GEMESEG' => 'ficha',
            'SALIDAS' => 'salida',
            'DOCUMENTACION_ACTUALIZADA', 'DOCUMENTOS_FALTANTES' => 'expediente',
            'CURSOS' => 'curso',
            default => 'otro',
        };
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', '_', $value) ?? $value;
        $value = preg_replace('/_+/', '_', trim($value, '_')) ?? $value;

        return str_replace(
            ['C_EDULA', 'N_UMERO', 'ELECTR_ONICO', 'AN_OS'],
            ['CEDULA', 'NUMERO', 'ELECTRONICO', 'ANOS'],
            $value,
        );
    }

    private function normalizeCellForHeader(string $headerKey, string $value): string
    {
        if ($value === '') {
            return '';
        }
        $isDateColumn = str_contains($headerKey, 'FECHA')
            || str_contains($headerKey, 'NACIMIENTO')
            || str_contains($headerKey, 'INGRESO')
            || str_contains($headerKey, 'SALIDA')
            || str_contains($headerKey, 'CULMINACION');

        if ($isDateColumn && preg_match('/^\d+(\.\d+)?$/', $value) === 1) {
            $serial = (float) $value;
            if ($serial > 1) {
                return gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
            }
        }

        return trim($value);
    }

    private function validateStagingSchema(): void
    {
        foreach (['importacion_excel_registros', 'catalogo_vacunas', 'fichas_vacunas'] as $table) {
            if (!$this->tableExists($table)) {
                throw new DomainException("Falta tabla {$table}. Ejecuta migraciones antes de importar.");
            }
        }
        foreach ([['fichas', 'procedencia'], ['fichas', 'talla_uniforme'], ['asignaciones', 'remuneracion']] as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                throw new DomainException("Falta columna {$table}.{$column}. Ejecuta migraciones antes de importar.");
            }
        }
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $table],
        ) === 1;
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
            ['table' => $table, 'column' => $column],
        ) === 1;
    }

    /**
     * @param array<string, mixed> $analysis
     * @return list<string>
     */
    private function uniqueCedulaHashes(array $analysis): array
    {
        $hashes = [];
        foreach ($analysis['sheets'] as $sheet) {
            foreach ($sheet['rows'] as $row) {
                if ($row['cedula_hash'] !== null) {
                    $hashes[$row['cedula_hash']] = true;
                }
            }
        }
        return array_keys($hashes);
    }

    /**
     * @param list<string> $hashes
     * @return array<string, bool>
     */
    private function existingFichaHashes(string $tenantId, array $hashes): array
    {
        if ($hashes === []) {
            return [];
        }

        $found = [];
        foreach (array_chunk($hashes, 200) as $chunk) {
            $bindings = ['tenant_id' => $tenantId];
            $placeholders = [];
            foreach ($chunk as $i => $hash) {
                $key = 'h' . $i;
                $bindings[$key] = $hash;
                $placeholders[] = ':' . $key;
            }
            $rows = $this->db->fetchAll(
                'SELECT cedula_hash FROM fichas WHERE tenant_id = :tenant_id AND cedula_hash IN (' . implode(',', $placeholders) . ')',
                $bindings,
            );
            foreach ($rows as $row) {
                $found[(string) $row['cedula_hash']] = true;
            }
        }

        return $found;
    }

    private function existingStagingCount(string $tenantId, string $source): int
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM importacion_excel_registros WHERE tenant_id = :tenant_id AND fuente = :fuente',
            ['tenant_id' => $tenantId, 'fuente' => $source],
        );
    }
}
