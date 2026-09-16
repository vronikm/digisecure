<?php
declare(strict_types=1);

/**
 * DigiSecure - Importador controlado de Excel GEMESEG.
 *
 * Seguro por defecto:
 *   - Sin --apply solo analiza estructura, conteos y riesgos.
 *   - Con --apply guarda filas originales cifradas en staging.
 *   - No imprime nombres, cedulas, telefonos, correos ni direcciones.
 */

use DigiSecure\Infrastructure\Import\GemesegExcelImportService;
use DigiSecure\Infrastructure\Import\XlsxReader;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Security\Crypto\BlindIndex;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Shared\Infrastructure\IdGenerator;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: este script solo se ejecuta desde CLI.\n";
    exit(1);
}

define('DIGISECURE_ROOT', dirname(__DIR__));

$autoload = DIGISECURE_ROOT . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "ERROR: vendor/autoload.php no encontrado. Ejecuta composer install.\n");
    exit(1);
}

require $autoload;

try {
    main($argv);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: {$e->getMessage()}\n");
    exit(1);
}

/**
 * @param list<string> $argv
 */
function main(array $argv): void
{
    $options = parseOptions($argv);

    if (isset($options['help']) || isset($options['h'])) {
        printHelp();
        return;
    }

    if (isset($options['list-tenants'])) {
        listTenants(loadDb());
        return;
    }

    $file = optionString($options, 'file');
    if ($file === null) {
        throw new InvalidArgumentException('Falta --file=/ruta/al/archivo.xlsx');
    }

    $apply = isset($options['apply']);
    $tenantId = optionString($options, 'tenant-id');
    $userId = optionString($options, 'user-id');
    $source = optionString($options, 'source') ?? basename($file);
    $sheetFilter = optionString($options, 'sheet');
    $limit = optionInt($options, 'limit');

    if ($apply && $tenantId === null) {
        throw new InvalidArgumentException('Para --apply debes indicar --tenant-id=...');
    }

    $reader = new XlsxReader();
    $workbook = $reader->read($file);
    // El indice de cedula es HMAC: sin ENCRYPTION_KEY solo se puede analizar.
    $env = EnvLoader::load(DIGISECURE_ROOT . '/.env');
    $encryptionKey = trim((string) ($env['ENCRYPTION_KEY'] ?? ''));
    $blindIndex = $encryptionKey !== '' ? Encryption::fromHexKey($encryptionKey)->blindIndexer() : null;
    // --apply ya exige --tenant-id (validado arriba).
    if ($blindIndex === null && $tenantId !== null) {
        throw new InvalidArgumentException('ENCRYPTION_KEY es obligatoria para validar contra la BD o aplicar staging.');
    }
    $analysis = analyzeWorkbook($workbook, $sheetFilter, $limit, $blindIndex);

    echo line('=', 78);
    echo "DigiSecure :: Importacion GEMESEG Excel\n";
    echo "Archivo: " . basename($file) . "\n";
    echo 'Modo: ' . ($apply ? 'APPLY staging cifrado' : 'analisis / dry-run') . "\n";
    echo line('=', 78);

    printAnalysis($analysis);

    $db = null;
    if ($tenantId !== null || $apply) {
        $db = loadDb();
        assertTenantExists($db, (string) $tenantId);
        if ($userId !== null) {
            assertUserExists($db, (string) $tenantId, $userId);
        }
        validateStagingSchema($db);

        $hashes = uniqueCedulaHashes($analysis);
        $existingFichaHashes = existingFichaHashes($db, (string) $tenantId, $hashes);
        $existingStagingCount = existingStagingCount($db, (string) $tenantId, $source);

        echo line('-', 78);
        echo "Validacion BD\n";
        echo "Tenant: {$tenantId}\n";
        echo 'Migracion 019/staging: OK' . "\n";
        echo 'Cedulas validas del Excel ya presentes en fichas: ' . count($existingFichaHashes) . "\n";
        echo "Filas ya existentes en staging para fuente '{$source}': {$existingStagingCount}\n";
    }

    if (!$apply) {
        echo line('-', 78);
        echo "No se escribio en la base. Para guardar staging cifrado usa --apply --tenant-id=...\n";
        return;
    }

    $encryption = Encryption::fromHexKey($encryptionKey);

    $result = stageRows($db, $encryption, (string) $tenantId, $userId, $source, $analysis);

    echo line('-', 78);
    echo "Resultado staging cifrado\n";
    echo "Insertadas: {$result['inserted']}\n";
    echo "Ya existentes/omitidas: {$result['skipped']}\n";
    echo "Errores: {$result['errors']}\n";
    echo line('=', 78);
}

/**
 * @param list<string> $argv
 * @return array<string, string|bool>
 */
function parseOptions(array $argv): array
{
    $options = [];
    for ($i = 1, $count = count($argv); $i < $count; $i++) {
        $arg = $argv[$i];
        if (!str_starts_with($arg, '--')) {
            continue;
        }

        $arg = substr($arg, 2);
        if (str_contains($arg, '=')) {
            [$key, $value] = explode('=', $arg, 2);
            $options[$key] = $value;
            continue;
        }

        $next = $argv[$i + 1] ?? null;
        if ($next !== null && !str_starts_with($next, '--')) {
            $options[$arg] = $next;
            $i++;
        } else {
            $options[$arg] = true;
        }
    }

    return $options;
}

/**
 * @param array<string, string|bool> $options
 */
function optionString(array $options, string $key): ?string
{
    $value = $options[$key] ?? null;
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    return trim($value);
}

/**
 * @param array<string, string|bool> $options
 */
function optionInt(array $options, string $key): ?int
{
    $value = optionString($options, $key);
    if ($value === null) {
        return null;
    }
    $int = (int) $value;
    return $int > 0 ? $int : null;
}

function printHelp(): void
{
    echo <<<TXT
DigiSecure - Importador GEMESEG Excel

Uso:
  php bin/import-gemeseg-excel.php --file="/ruta/PERSONAL GEMESEG DATOS ACTUALIZADOS.xlsx"
  php bin/import-gemeseg-excel.php --list-tenants
  php bin/import-gemeseg-excel.php --file="/ruta/archivo.xlsx" --tenant-id=TENANT_ID
  php bin/import-gemeseg-excel.php --file="/ruta/archivo.xlsx" --tenant-id=TENANT_ID --apply

Opciones:
  --file=PATH        Ruta del XLSX.
  --tenant-id=ID     Tenant donde se validara o cargara staging.
  --user-id=ID       Usuario responsable de la importacion (opcional).
  --source=NOMBRE    Nombre logico de fuente. Default: basename del archivo.
  --sheet=NOMBRE     Procesar solo una hoja.
  --limit=N          Limitar filas procesadas por hoja para pruebas.
  --apply            Inserta filas cifradas en importacion_excel_registros.
  --list-tenants     Lista tenants disponibles desde .env.
  --help             Muestra esta ayuda.

TXT;
}

function loadDb(): PdoConnection
{
    $envPath = DIGISECURE_ROOT . '/.env';
    if (!is_file($envPath)) {
        throw new RuntimeException('No existe .env. Configura la BD antes de usar opciones con base de datos.');
    }

    $env = EnvLoader::load($envPath);
    $config = new Config([
        'db' => [
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => $env['DB_PORT'] ?? '3306',
            'database' => $env['DB_DATABASE'] ?? 'digisecure',
            'username' => $env['DB_USERNAME'] ?? '',
            'password' => $env['DB_PASSWORD'] ?? '',
            'charset' => $env['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => $env['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
        ],
    ]);

    return PdoConnection::fromConfig($config);
}

function listTenants(PdoConnection $db): void
{
    $rows = $db->fetchAll(
        'SELECT id, razon_social, ruc, estado FROM tenants ORDER BY razon_social ASC LIMIT 200',
    );

    echo line('=', 78);
    echo "Tenants disponibles\n";
    echo line('=', 78);

    if ($rows === []) {
        echo "No hay tenants registrados.\n";
        return;
    }

    printf("%-28s %-34s %-14s %s\n", 'ID', 'RAZON_SOCIAL', 'RUC', 'ESTADO');
    echo line('-', 78);
    foreach ($rows as $row) {
        printf(
            "%-28s %-34s %-14s %s\n",
            (string) $row['id'],
            mb_strimwidth((string) $row['razon_social'], 0, 34, ''),
            (string) $row['ruc'],
            (string) $row['estado'],
        );
    }
}

/**
 * @param array{path: string, sheets: list<array{name: string, rows: list<array{number: int, cells: array<int, string>}>}>} $workbook
 * @return array{source_path: string, sheets: list<array{name: string, header_row: int, headers: array<int, array{name: string, key: string}>, rows: list<array{row: int, entity_tipo: string, data: array<string, string>, cedula_digits: ?string, cedula_hash: ?string, cedula_valid: bool, cedula_padded: bool, issues: list<string>}>, stats: array<string, int>}>}
 */
function analyzeWorkbook(array $workbook, ?string $sheetFilter, ?int $limit, ?BlindIndex $blindIndex): array
{
    $result = ['source_path' => $workbook['path'], 'sheets' => []];

    foreach ($workbook['sheets'] as $sheet) {
        if ($sheetFilter !== null && $sheet['name'] !== $sheetFilter) {
            continue;
        }

        $headerRow = knownHeaderRow($sheet['name']) ?? detectHeaderRow($sheet['rows']);
        $headers = extractHeaders($sheet['rows'], $headerRow);
        $entityTipo = sheetEntityType($sheet['name']);
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

            $data = rowData($row['cells'], $headers);
            if (isEmptyDataRow($data)) {
                continue;
            }

            $cedulaInfo = GemesegExcelImportService::cedulaInfo($data, $blindIndex);
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

        $result['sheets'][] = [
            'name' => $sheet['name'],
            'header_row' => $headerRow,
            'headers' => $headers,
            'rows' => $rows,
            'stats' => $stats,
        ];
    }

    return $result;
}

function knownHeaderRow(string $sheetName): ?int
{
    return match (normalizeSheetName($sheetName)) {
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
function detectHeaderRow(array $rows): int
{
    $bestRow = 1;
    $bestScore = 0;

    foreach ($rows as $row) {
        if ($row['number'] > 30) {
            break;
        }
        $score = 0;
        foreach ($row['cells'] as $value) {
            $key = normalizeHeader($value);
            if (in_array($key, ['APELLIDOS', 'NOMBRES', 'CEDULA', 'PUESTO', 'FECHA_INGRESO'], true)) {
                $score += 10;
            } elseif ($key !== '') {
                $score++;
            }
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
function extractHeaders(array $rows, int $headerRow): array
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
        $name = trim($name);
        $key = normalizeHeader($name);
        if ($key === '') {
            $key = 'COL_' . $column;
            $name = '';
        }

        $seen[$key] = ($seen[$key] ?? 0) + 1;
        if ($seen[$key] > 1) {
            $key .= '_' . $seen[$key];
        }

        $headers[$column] = ['name' => $name, 'key' => $key];
    }

    ksort($headers);
    return $headers;
}

/**
 * @param array<int, string> $cells
 * @param array<int, array{name: string, key: string}> $headers
 * @return array<string, string>
 */
function rowData(array $cells, array $headers): array
{
    $data = [];
    foreach ($headers as $column => $header) {
        $key = $header['key'];
        $value = trim((string) ($cells[$column] ?? ''));
        $data[$key] = normalizeCellForHeader($key, $value);
    }
    return $data;
}

/**
 * @param array<string, string> $data
 */
function isEmptyDataRow(array $data): bool
{
    foreach ($data as $key => $value) {
        if (str_starts_with($key, 'COL_')) {
            continue;
        }
        if (trim($value) !== '') {
            return false;
        }
    }
    return true;
}

function sheetEntityType(string $sheetName): string
{
    return match (normalizeSheetName($sheetName)) {
        'GUARDIAS_GEMESEG' => 'ficha',
        'SALIDAS' => 'salida',
        'DOCUMENTACION_ACTUALIZADA', 'DOCUMENTOS_FALTANTES' => 'expediente',
        'CURSOS' => 'curso',
        default => 'otro',
    };
}

function normalizeSheetName(string $value): string
{
    return normalizeHeader($value);
}

function normalizeHeader(string $value): string
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
    $value = trim($value, '_');
    $value = preg_replace('/_+/', '_', $value) ?? $value;

    return str_replace(
        ['C_EDULA', 'N_UMERO', 'ELECTR_ONICO', 'AN_OS'],
        ['CEDULA', 'NUMERO', 'ELECTRONICO', 'ANOS'],
        $value,
    );
}

function normalizeCellForHeader(string $headerKey, string $value): string
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
            $timestamp = (int) round(($serial - 25569) * 86400);
            return gmdate('Y-m-d', $timestamp);
        }
    }

    return trim($value);
}

/**
 * @param array{source_path: string, sheets: list<array{name: string, header_row: int, headers: array<int, array{name: string, key: string}>, rows: list<array{row: int, entity_tipo: string, data: array<string, string>, cedula_digits: ?string, cedula_hash: ?string, cedula_valid: bool, cedula_padded: bool, issues: list<string>}>, stats: array<string, int>}>} $analysis
 */
function printAnalysis(array $analysis): void
{
    $totals = [
        'sheets' => count($analysis['sheets']),
        'data_rows' => 0,
        'with_cedula' => 0,
        'valid_cedula' => 0,
        'invalid_cedula' => 0,
        'missing_cedula' => 0,
        'padded_cedula' => 0,
        'duplicate_cedula_in_sheet' => 0,
    ];

    printf("%-30s %-8s %-8s %-8s %-8s %-8s %-8s\n", 'HOJA', 'HEADER', 'FILAS', 'CEDULA', 'VALIDAS', 'INVALID', 'DUP');
    echo line('-', 78);

    foreach ($analysis['sheets'] as $sheet) {
        foreach ($sheet['stats'] as $key => $value) {
            if (isset($totals[$key])) {
                $totals[$key] += $value;
            }
        }

        printf(
            "%-30s %-8d %-8d %-8d %-8d %-8d %-8d\n",
            mb_strimwidth($sheet['name'], 0, 30, ''),
            $sheet['header_row'],
            $sheet['stats']['data_rows'],
            $sheet['stats']['with_cedula'],
            $sheet['stats']['valid_cedula'],
            $sheet['stats']['invalid_cedula'],
            $sheet['stats']['duplicate_cedula_in_sheet'],
        );
    }

    echo line('-', 78);
    echo "Total hojas: {$totals['sheets']}\n";
    echo "Total filas de datos: {$totals['data_rows']}\n";
    echo "Cedulas presentes: {$totals['with_cedula']}\n";
    echo "Cedulas validas: {$totals['valid_cedula']}\n";
    echo "Cedulas invalidas: {$totals['invalid_cedula']}\n";
    echo "Cedulas faltantes: {$totals['missing_cedula']}\n";
    echo "Cedulas corregidas por cero inicial perdido: {$totals['padded_cedula']}\n";
    echo "Duplicados por cedula dentro de hoja: {$totals['duplicate_cedula_in_sheet']}\n";
}

function assertTenantExists(PdoConnection $db, string $tenantId): void
{
    $exists = $db->fetchScalar('SELECT COUNT(*) FROM tenants WHERE id = :id', ['id' => $tenantId]);
    if ((int) $exists !== 1) {
        throw new RuntimeException("Tenant no encontrado: {$tenantId}");
    }
}

function assertUserExists(PdoConnection $db, string $tenantId, string $userId): void
{
    $exists = $db->fetchScalar(
        'SELECT COUNT(*) FROM usuarios WHERE id = :id AND tenant_id = :tenant_id',
        ['id' => $userId, 'tenant_id' => $tenantId],
    );
    if ((int) $exists !== 1) {
        throw new RuntimeException("Usuario no encontrado en el tenant indicado: {$userId}");
    }
}

function validateStagingSchema(PdoConnection $db): void
{
    $requiredTables = ['importacion_excel_registros', 'catalogo_vacunas', 'fichas_vacunas'];
    foreach ($requiredTables as $table) {
        if (!tableExists($db, $table)) {
            throw new RuntimeException("Falta tabla {$table}. Ejecuta php bin/migrate.php migrate antes de importar.");
        }
    }

    $requiredColumns = [
        ['fichas', 'procedencia'],
        ['fichas', 'cargas_familiares'],
        ['fichas', 'talla_uniforme'],
        ['fichas', 'talla_zapatos'],
        ['asignaciones', 'remuneracion'],
        ['asignaciones', 'clase_trabajo'],
    ];
    foreach ($requiredColumns as [$table, $column]) {
        if (!columnExists($db, $table, $column)) {
            throw new RuntimeException("Falta columna {$table}.{$column}. Ejecuta php bin/migrate.php migrate antes de importar.");
        }
    }
}

function tableExists(PdoConnection $db, string $table): bool
{
    $count = $db->fetchScalar(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
        ['table' => $table],
    );
    return (int) $count === 1;
}

function columnExists(PdoConnection $db, string $table, string $column): bool
{
    $count = $db->fetchScalar(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
        ['table' => $table, 'column' => $column],
    );
    return (int) $count === 1;
}

/**
 * @param array{source_path: string, sheets: list<array{name: string, header_row: int, headers: array<int, array{name: string, key: string}>, rows: list<array{row: int, entity_tipo: string, data: array<string, string>, cedula_digits: ?string, cedula_hash: ?string, cedula_valid: bool, cedula_padded: bool, issues: list<string>}>, stats: array<string, int>}>} $analysis
 * @return list<string>
 */
function uniqueCedulaHashes(array $analysis): array
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
function existingFichaHashes(PdoConnection $db, string $tenantId, array $hashes): array
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

        $rows = $db->fetchAll(
            'SELECT cedula_hash FROM fichas WHERE tenant_id = :tenant_id AND cedula_hash IN (' . implode(',', $placeholders) . ')',
            $bindings,
        );
        foreach ($rows as $row) {
            $found[(string) $row['cedula_hash']] = true;
        }
    }

    return $found;
}

function existingStagingCount(PdoConnection $db, string $tenantId, string $source): int
{
    return (int) $db->fetchScalar(
        'SELECT COUNT(*) FROM importacion_excel_registros WHERE tenant_id = :tenant_id AND fuente = :fuente',
        ['tenant_id' => $tenantId, 'fuente' => $source],
    );
}

/**
 * @param array{source_path: string, sheets: list<array{name: string, header_row: int, headers: array<int, array{name: string, key: string}>, rows: list<array{row: int, entity_tipo: string, data: array<string, string>, cedula_digits: ?string, cedula_hash: ?string, cedula_valid: bool, cedula_padded: bool, issues: list<string>}>, stats: array<string, int>}>} $analysis
 * @return array{inserted: int, skipped: int, errors: int}
 */
function stageRows(PdoConnection $db, Encryption $encryption, string $tenantId, ?string $userId, string $source, array $analysis): array
{
    $inserted = 0;
    $skipped = 0;
    $errors = 0;

    $db->transactional(function (PdoConnection $db) use (
        $analysis,
        $encryption,
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
                        throw new RuntimeException('No se pudo serializar fila a JSON.');
                    }

                    $aad = "{$tenantId}|gemeseg_excel|{$source}|{$sheet['name']}|{$row['row']}";
                    $ciphertext = $encryption->deriveTenantKey($tenantId)->encrypt($json, $aad);
                    $now = gmdate('Y-m-d H:i:s') . '.000000';

                    $stmt = $db->execute(
                        'INSERT INTO importacion_excel_registros
                            (id, tenant_id, fuente, hoja, fila, cedula_hash, raw_json_cifrado, raw_json_hash,
                             entidad_tipo, entidad_id, estado, error_mensaje, importado_por, importado_at, created_at)
                         VALUES
                            (:id, :tenant_id, :fuente, :hoja, :fila, :cedula_hash, :raw_json_cifrado, :raw_json_hash,
                             :entidad_tipo, NULL, :estado, NULL, :importado_por, :importado_at, :created_at)
                         ON DUPLICATE KEY UPDATE id = id',
                        [
                            'id' => IdGenerator::ulid(),
                            'tenant_id' => $tenantId,
                            'fuente' => $source,
                            'hoja' => $sheet['name'],
                            'fila' => $row['row'],
                            'cedula_hash' => $row['cedula_hash'],
                            'raw_json_cifrado' => $ciphertext,
                            'raw_json_hash' => hash('sha256', $json),
                            'entidad_tipo' => $row['entity_tipo'],
                            'estado' => $row['cedula_valid'] ? 'pendiente' : 'error',
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

    return ['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors];
}

function line(string $char, int $length): string
{
    return str_repeat($char, $length) . "\n";
}
