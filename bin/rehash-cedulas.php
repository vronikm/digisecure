<?php
/**
 * Recalcula `cedula_hash` como blind index HMAC (BlindIndex::cedula()).
 *
 * Reemplaza el sha256 sin clave, que permitia recuperar todas las cedulas de
 * un dump de la BD por fuerza bruta. Recalcula desde el dato cifrado, asi que
 * es idempotente: correrlo dos veces no cambia nada la segunda vez. Tambien
 * sirve tras rotar ENCRYPTION_KEY (despues de re-cifrar).
 *
 * Tablas: fichas, fichas_familiares, usuarios (desde cedula_cifrada) e
 * importacion_excel_registros (desde raw_json_cifrado).
 *
 * Filas que no se pueden descifrar con la ENCRYPTION_KEY actual se reportan y
 * no se tocan. Para fichas de aspirantes existe
 * `php bin/repair-encrypted-cedulas.php`.
 *
 * No imprime cedulas ni claves. `updated_at` no se modifica: es un cambio
 * tecnico de indice, no del dato de negocio.
 *
 * Uso:
 *   php bin/rehash-cedulas.php --dry-run
 *   php bin/rehash-cedulas.php --apply
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: este script solo se ejecuta desde CLI.\n");
}

require dirname(__DIR__) . '/vendor/autoload.php';

use DigiSecure\Infrastructure\Import\GemesegExcelImportService;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Security\Crypto\Encryption;

$apply = in_array('--apply', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);

if ($apply === $dryRun) {
    fwrite(STDERR, "Uso: php bin/rehash-cedulas.php --dry-run | --apply\n");
    exit(1);
}

$root = dirname(__DIR__);
$env = EnvLoader::load($root . '/.env');
date_default_timezone_set($env['APP_TIMEZONE'] ?? 'America/Guayaquil');

$db = PdoConnection::fromConfig(new Config([
    'db' => [
        'host' => $env['DB_HOST'] ?? '127.0.0.1',
        'port' => $env['DB_PORT'] ?? '3306',
        'database' => $env['DB_DATABASE'] ?? 'digisecure',
        'username' => $env['DB_USERNAME'] ?? '',
        'password' => $env['DB_PASSWORD'] ?? '',
        'charset' => $env['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $env['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    ],
]));

$encryption = Encryption::fromHexKey((string) ($env['ENCRYPTION_KEY'] ?? ''));
$blindIndex = $encryption->blindIndexer();

// Valor que usa bin/anonimizar-retencion.php: no hay cedula que indexar.
const HASH_ANONIMIZADO = '0000000000000000000000000000000000000000000000000000000000000000';

echo str_repeat('=', 78) . PHP_EOL;
echo 'DigiSecure :: Recalculo de cedula_hash (HMAC)' . PHP_EOL;
echo 'Modo: ' . ($apply ? 'APPLY' : 'dry-run') . PHP_EOL;
echo str_repeat('=', 78) . PHP_EOL;
printf("%-28s %8s %8s %10s %10s %8s\n", 'TABLA', 'FILAS', 'AL DIA', $apply ? 'ACTUALIZ.' : 'A ACTUAL.', 'NO DESCIF.', 'OMITID.');

$totalPendientes = 0;
$totalNoDescifrables = 0;

$db->transactional(function (PdoConnection $conn) use ($encryption, $blindIndex, $apply, &$totalPendientes, &$totalNoDescifrables): void {
    foreach (['fichas', 'fichas_familiares', 'usuarios'] as $table) {
        $rows = $conn->fetchAll(
            "SELECT id, cedula_hash, cedula_cifrada
               FROM {$table}
              WHERE cedula_cifrada IS NOT NULL
                AND cedula_cifrada <> ''",
        );

        $stats = rehashRows(
            $conn,
            $table,
            $rows,
            static function (array $row) use ($encryption, $blindIndex): string {
                return $blindIndex->cedula($encryption->decrypt((string) $row['cedula_cifrada']));
            },
            $apply,
        );
        printStats($table, $stats);
        $totalPendientes += $stats['pendientes'];
        $totalNoDescifrables += $stats['no_descifrables'];
    }

    $rows = $conn->fetchAll(
        'SELECT id, tenant_id, fuente, hoja, fila, cedula_hash, raw_json_cifrado
           FROM importacion_excel_registros',
    );
    $tenantKeys = [];
    $stats = rehashRows(
        $conn,
        'importacion_excel_registros',
        $rows,
        static function (array $row) use ($encryption, $blindIndex, &$tenantKeys): ?string {
            $tenantId = (string) $row['tenant_id'];
            $tenantKeys[$tenantId] ??= $encryption->deriveTenantKey($tenantId);
            // Mismo AAD que GemesegExcelImportService / GemesegStagingProcessorService.
            $aad = "{$tenantId}|gemeseg_excel|{$row['fuente']}|{$row['hoja']}|{$row['fila']}";
            $payload = json_decode($tenantKeys[$tenantId]->decrypt((string) $row['raw_json_cifrado'], $aad), true);
            $data = is_array($payload) && is_array($payload['data'] ?? null) ? $payload['data'] : [];

            return GemesegExcelImportService::cedulaInfo($data, $blindIndex)['hash'];
        },
        $apply,
    );
    printStats('importacion_excel_registros', $stats);
    $totalPendientes += $stats['pendientes'];
    $totalNoDescifrables += $stats['no_descifrables'];
});

echo str_repeat('-', 78) . PHP_EOL;
if ($apply) {
    echo "Indices actualizados: {$totalPendientes}.\n";
} elseif ($totalPendientes > 0) {
    echo "{$totalPendientes} indice(s) cambiarian. Ejecuta con --apply.\n";
} elseif ($totalNoDescifrables === 0) {
    echo "Todos los indices estan al dia.\n";
} else {
    echo "No hay indices pendientes entre las filas descifrables.\n";
}
if ($totalNoDescifrables > 0) {
    echo "ATENCION: {$totalNoDescifrables} fila(s) no se pudieron descifrar con la ENCRYPTION_KEY actual y conservan su hash anterior.\n";
    echo "Para fichas de aspirantes: php bin/repair-encrypted-cedulas.php --dry-run\n";
}

/**
 * @param list<array<string, mixed>> $rows
 * @param callable(array<string, mixed>): ?string $nuevoIndice lanza si no puede descifrar
 * @return array{filas: int, al_dia: int, pendientes: int, no_descifrables: int, omitidas: int}
 */
function rehashRows(PdoConnection $conn, string $table, array $rows, callable $nuevoIndice, bool $apply): array
{
    $stats = ['filas' => count($rows), 'al_dia' => 0, 'pendientes' => 0, 'no_descifrables' => 0, 'omitidas' => 0];

    foreach ($rows as $row) {
        $actual = $row['cedula_hash'] !== null ? (string) $row['cedula_hash'] : null;
        if ($actual === HASH_ANONIMIZADO) {
            $stats['omitidas']++;
            continue;
        }

        try {
            $nuevo = $nuevoIndice($row);
        } catch (Throwable) {
            $stats['no_descifrables']++;
            continue;
        }

        if ($nuevo === $actual) {
            $stats['al_dia']++;
            continue;
        }

        $stats['pendientes']++;
        if ($apply) {
            $conn->execute(
                "UPDATE {$table} SET cedula_hash = :nuevo WHERE id = :id",
                ['nuevo' => $nuevo, 'id' => (string) $row['id']],
            );
        }
    }

    return $stats;
}

/**
 * @param array{filas: int, al_dia: int, pendientes: int, no_descifrables: int, omitidas: int} $stats
 */
function printStats(string $table, array $stats): void
{
    printf(
        "%-28s %8d %8d %10d %10d %8d\n",
        $table,
        $stats['filas'],
        $stats['al_dia'],
        $stats['pendientes'],
        $stats['no_descifrables'],
        $stats['omitidas'],
    );
}
