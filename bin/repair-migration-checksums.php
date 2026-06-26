<?php
declare(strict_types=1);

/**
 * DigiSecure - Reparacion controlada de checksums de migraciones.
 *
 * Uso:
 *   php bin/repair-migration-checksums.php
 *   php bin/repair-migration-checksums.php --apply
 *
 * Este comando NO ejecuta SQL de migraciones pendientes. Solo sincroniza el
 * checksum guardado para migraciones que ya constan aplicadas en la tabla
 * `migraciones` y cuyo archivo existe en el Git actual.
 */

use DigiSecure\Infrastructure\Persistence\Migration;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;

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
    $apply = in_array('--apply', $argv, true);
    $db = loadDbForRepair();
    $migrations = discoverRepairMigrations(DIGISECURE_ROOT . '/migrations');
    $applied = loadAppliedRepairMigrations($db);

    echo str_repeat('=', 78) . PHP_EOL;
    echo "DigiSecure :: Reparacion de checksums de migraciones\n";
    echo 'Modo: ' . ($apply ? 'APPLY' : 'dry-run') . PHP_EOL;
    echo str_repeat('=', 78) . PHP_EOL;

    $mismatches = [];
    foreach ($migrations as $migration) {
        $row = $applied[$migration->version] ?? null;
        if ($row === null) {
            continue;
        }
        if ($row['checksum'] === $migration->checksum) {
            continue;
        }

        $mismatches[] = $migration;
        printf(
            "%-8s %-40s %s -> %s\n",
            $migration->version,
            $migration->name,
            substr($row['checksum'], 0, 12),
            substr($migration->checksum, 0, 12),
        );
    }

    if ($mismatches === []) {
        echo "No hay checksums para reparar.\n";
        exit(0);
    }

    if (!$apply) {
        echo str_repeat('-', 78) . PHP_EOL;
        echo count($mismatches) . " checksum(s) cambiarian. Ejecuta con --apply para actualizar la tabla migraciones.\n";
        exit(0);
    }

    foreach ($mismatches as $migration) {
        $db->execute(
            'UPDATE migraciones
                SET name = :name, filename = :filename, checksum = :checksum
              WHERE version = :version',
            [
                'version' => $migration->version,
                'name' => $migration->name,
                'filename' => $migration->filename,
                'checksum' => $migration->checksum,
            ],
        );
    }

    echo str_repeat('-', 78) . PHP_EOL;
    echo count($mismatches) . " checksum(s) reparado(s).\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: {$e->getMessage()}\n");
    exit(1);
}

function loadDbForRepair(): PdoConnection
{
    $envPath = DIGISECURE_ROOT . '/.env';
    if (!is_file($envPath)) {
        throw new RuntimeException('No existe .env. Configura la BD antes de reparar checksums.');
    }

    $env = EnvLoader::load($envPath);
    return PdoConnection::fromConfig(new Config([
        'db' => [
            'host' => $env['DB_HOST'] ?? '127.0.0.1',
            'port' => $env['DB_PORT'] ?? '3306',
            'database' => $env['DB_DATABASE'] ?? '',
            'username' => $env['DB_USERNAME'] ?? '',
            'password' => $env['DB_PASSWORD'] ?? '',
            'charset' => $env['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => $env['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
        ],
    ]));
}

/**
 * @return array<string, Migration>
 */
function discoverRepairMigrations(string $path): array
{
    $files = glob($path . '/*.sql');
    if ($files === false) {
        return [];
    }
    sort($files, SORT_STRING);

    $migrations = [];
    foreach ($files as $file) {
        $migration = Migration::fromFile($file);
        $migrations[$migration->version] = $migration;
    }

    return $migrations;
}

/**
 * @return array<string, array{checksum: string}>
 */
function loadAppliedRepairMigrations(PdoConnection $db): array
{
    $rows = $db->fetchAll('SELECT version, checksum FROM migraciones');
    $applied = [];
    foreach ($rows as $row) {
        $applied[(string) $row['version']] = [
            'checksum' => (string) $row['checksum'],
        ];
    }

    return $applied;
}
