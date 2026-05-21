<?php
/**
 * =====================================================================
 * DigiSecure - Migration CLI
 * =====================================================================
 *
 * Uso (desde la raíz del proyecto):
 *
 *     php bin/migrate.php status      Ver estado de cada migración
 *     php bin/migrate.php migrate     Aplicar todas las pendientes
 *     php bin/migrate.php rollback    Revertir la última aplicada
 *     php bin/migrate.php help        Mostrar ayuda
 *
 * Lee la configuración de BD desde .env (mismo loader que la app web).
 * NUNCA exponer este script vía HTTP — bin/ está bloqueado por .htaccess.
 * =====================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: este script solo se ejecuta desde CLI.\n";
    exit(1);
}

define('DIGISECURE_ROOT', dirname(__DIR__));

$autoload = DIGISECURE_ROOT . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "ERROR: vendor/autoload.php no encontrado. Ejecuta `composer install`.\n");
    exit(1);
}
/** @var mixed $loader */
$loader = require $autoload;
if ($loader instanceof \Composer\Autoload\ClassLoader) {
    $loader->addPsr4('DigiSecure\\Superadmin\\', DIGISECURE_ROOT . '/src/Superadmin');
    $loader->addPsr4('DigiSecure\\Cliente\\', DIGISECURE_ROOT . '/src/Cliente');
    $loader->addPsr4('DigiSecure\\Puesto\\', DIGISECURE_ROOT . '/src/Puesto');
    $loader->addPsr4('DigiSecure\\Asignacion\\', DIGISECURE_ROOT . '/src/Asignacion');
}

use DigiSecure\Infrastructure\Persistence\MigrationException;
use DigiSecure\Infrastructure\Persistence\MigrationRunner;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;

// --- Comando ---
$command = $argv[1] ?? 'help';

// Comandos que no necesitan BD
if (in_array($command, ['help', '--help', '-h'], true)) {
    cmdHelp();
    exit(0);
}

if (!is_file(DIGISECURE_ROOT . '/.env')) {
    fwrite(STDERR, "ERROR: .env no encontrado. Copia .env.example a .env y configura BD.\n");
    exit(1);
}

// --- Cargar config ---
$envVars = EnvLoader::load(DIGISECURE_ROOT . '/.env');
$config = new Config([
    'db' => [
        'host' => $envVars['DB_HOST'] ?? '127.0.0.1',
        'port' => $envVars['DB_PORT'] ?? '3306',
        'database' => $envVars['DB_DATABASE'] ?? 'digisecure',
        'username' => $envVars['DB_USERNAME'] ?? '',
        'password' => $envVars['DB_PASSWORD'] ?? '',
        'charset' => $envVars['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $envVars['DB_COLLATION'] ?? 'utf8mb4_0900_ai_ci',
    ],
]);

date_default_timezone_set($envVars['APP_TIMEZONE'] ?? 'America/Guayaquil');

try {
    $db = PdoConnection::fromConfig($config);
    $runner = new MigrationRunner($db, DIGISECURE_ROOT . '/migrations');

    match ($command) {
        'status' => cmdStatus($runner, $config),
        'migrate' => cmdMigrate($runner, $config),
        'rollback' => cmdRollback($runner),
        default => (function () use ($command): never {
            fwrite(STDERR, "Comando desconocido: '{$command}'\n");
            cmdHelp();
            exit(1);
        })(),
    };
} catch (MigrationException $e) {
    fwrite(STDERR, "ERROR DE MIGRACIÓN: {$e->getMessage()}\n");
    exit(2);
} catch (\Throwable $e) {
    fwrite(STDERR, "ERROR: {$e->getMessage()}\n");
    fwrite(STDERR, "  in " . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(3);
}

exit(0);

// =====================================================================
// Comandos
// =====================================================================

function cmdHelp(): void
{
    echo <<<TXT
DigiSecure - Migration CLI

Uso:
    php bin/migrate.php <comando>

Comandos:
    status       Mostrar estado de cada migración (aplicada / pendiente)
    migrate      Aplicar todas las migraciones pendientes en orden
    rollback     Revertir la última migración aplicada
    help         Mostrar esta ayuda

Las migraciones viven en: migrations/NNN_descripcion.sql
Formato esperado:
    -- @up
    CREATE TABLE ...;
    -- @down
    DROP TABLE ...;

TXT;
}

function cmdStatus(MigrationRunner $runner, Config $config): void
{
    echo line('=', 78);
    echo "DigiSecure :: Estado de migraciones\n";
    echo "BD: {$config->string('db.database')}@{$config->string('db.host')}:{$config->int('db.port')}\n";
    echo line('=', 78);

    $rows = $runner->status();

    if ($rows === []) {
        echo "(no hay migraciones en el directorio migrations/)\n";
        return;
    }

    printf("%-8s %-40s %-12s %s\n", 'VERSION', 'NAME', 'STATUS', 'APPLIED_AT');
    echo line('-', 78);

    $pending = 0;
    $applied = 0;
    $mismatch = 0;

    foreach ($rows as $row) {
        /** @var \DigiSecure\Infrastructure\Persistence\Migration $m */
        $m = $row['migration'];
        $status = $row['status'];
        $appliedAt = $row['appliedAt'] ?? '-';

        printf("%-8s %-40s %-12s %s\n", $m->version, $m->name, $status, $appliedAt);

        if ($status === 'applied') {
            $applied++;
        } elseif ($status === 'pending') {
            $pending++;
        } else {
            $mismatch++;
        }
    }

    echo line('-', 78);
    echo "Total: {$applied} aplicada(s), {$pending} pendiente(s)";
    if ($mismatch > 0) {
        echo ", {$mismatch} CON CHECKSUM ALTERADO";
    }
    echo "\n";
}

function cmdMigrate(MigrationRunner $runner, Config $config): void
{
    echo line('=', 78);
    echo "DigiSecure :: Aplicar migraciones pendientes\n";
    echo "BD: {$config->string('db.database')}@{$config->string('db.host')}:{$config->int('db.port')}\n";
    echo line('=', 78);

    $executed = $runner->migrate();

    if ($executed === []) {
        echo "Nada que migrar. Todas las migraciones están aplicadas.\n";
        return;
    }

    foreach ($executed as $m) {
        echo "  ✓ {$m->version}_{$m->name}\n";
    }
    echo line('-', 78);
    echo count($executed) . " migración(es) aplicada(s) con éxito.\n";
}

function cmdRollback(MigrationRunner $runner): void
{
    echo line('=', 78);
    echo "DigiSecure :: Rollback de la última migración\n";
    echo line('=', 78);

    $reverted = $runner->rollbackLast();

    if ($reverted === null) {
        echo "No hay migraciones aplicadas para revertir.\n";
        return;
    }

    echo "  ✓ Revertida: {$reverted->version}_{$reverted->name}\n";
}

function line(string $char, int $length): string
{
    return str_repeat($char, $length) . "\n";
}
