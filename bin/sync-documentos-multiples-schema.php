<?php
/**
 * =====================================================================
 * DigiSecure - Sincronizador idempotente de documentos multiples (030)
 * =====================================================================
 *
 * Aplica SOLO lo que falte para permitir varios archivos por tipo
 * documental:
 *   - documento_tipos.numero_documentos
 *   - expediente_documentos.documento_indice
 *   - indice ix_expdoc_tenant_tipo_indice
 *
 * Es seguro ejecutarlo varias veces. Util cuando el runner de migraciones
 * esta bloqueado por checksum drift, pero se necesita dejar el schema al dia.
 *
 * Uso:
 *   php bin/sync-documentos-multiples-schema.php
 *
 * Lee la BD desde .env. Nunca exponer por HTTP.
 * =====================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: solo CLI.\n";
    exit(1);
}

define('DIGISECURE_ROOT', dirname(__DIR__));

$autoload = DIGISECURE_ROOT . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "ERROR: vendor/autoload.php no encontrado. Ejecuta composer install.\n");
    exit(1);
}

require $autoload;

use DigiSecure\Infrastructure\Persistence\Migration;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;

if (!is_file(DIGISECURE_ROOT . '/.env')) {
    fwrite(STDERR, "ERROR: .env no encontrado.\n");
    exit(1);
}

$env = EnvLoader::load(DIGISECURE_ROOT . '/.env');
$config = new Config(['db' => [
    'host' => $env['DB_HOST'] ?? '127.0.0.1',
    'port' => $env['DB_PORT'] ?? '3306',
    'database' => $env['DB_DATABASE'] ?? 'digisecure',
    'username' => $env['DB_USERNAME'] ?? '',
    'password' => $env['DB_PASSWORD'] ?? '',
    'charset' => $env['DB_CHARSET'] ?? 'utf8mb4',
    'collation' => $env['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
]]);
date_default_timezone_set($env['APP_TIMEZONE'] ?? 'America/Guayaquil');

$db = PdoConnection::fromConfig($config);
$schema = $config->string('db.database');

$columnExists = static fn(string $table, string $column): bool => (int) $db->fetchScalar(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column',
    ['schema' => $schema, 'table' => $table, 'column' => $column],
) > 0;

$indexExists = static fn(string $table, string $index): bool => (int) $db->fetchScalar(
    'SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND INDEX_NAME = :idx',
    ['schema' => $schema, 'table' => $table, 'idx' => $index],
) > 0;

$tableExists = static fn(string $table): bool => (int) $db->fetchScalar(
    'SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
    ['schema' => $schema, 'table' => $table],
) > 0;

$done = [];
$skip = [];
$step = static function (string $label, callable $callback) use (&$done, &$skip): void {
    try {
        if ($callback() === false) {
            $skip[] = $label;
            echo "  =  {$label} (ya estaba)\n";
            return;
        }

        $done[] = $label;
        echo "  +  {$label}\n";
    } catch (Throwable $e) {
        echo "  !  {$label}: {$e->getMessage()}\n";
        throw $e;
    }
};

echo "Sincronizando schema de documentos multiples...\n";

$step('documento_tipos.numero_documentos', static function () use ($db, $columnExists): bool {
    if ($columnExists('documento_tipos', 'numero_documentos')) {
        return false;
    }

    $db->rawExec(
        'ALTER TABLE documento_tipos
            ADD COLUMN numero_documentos SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER obligatorio',
    );
    return true;
});

$step('expediente_documentos.documento_indice', static function () use ($db, $columnExists): bool {
    if ($columnExists('expediente_documentos', 'documento_indice')) {
        return false;
    }

    $db->rawExec(
        'ALTER TABLE expediente_documentos
            ADD COLUMN documento_indice SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER documento_tipo_id',
    );
    return true;
});

$step('ix_expdoc_tenant_tipo_indice', static function () use ($db, $indexExists): bool {
    if ($indexExists('expediente_documentos', 'ix_expdoc_tenant_tipo_indice')) {
        return false;
    }

    $db->rawExec(
        'ALTER TABLE expediente_documentos
            ADD KEY ix_expdoc_tenant_tipo_indice
                (tenant_id, expediente_id, documento_tipo_id, documento_indice)',
    );
    return true;
});

echo "\nRegistrando migracion 030 si corresponde...\n";
if ($tableExists('migraciones')) {
    $file = DIGISECURE_ROOT . '/migrations/030_documentos_multiples_por_tipo.sql';
    if (is_file($file)) {
        $migration = Migration::fromFile($file);
        $exists = (int) $db->fetchScalar(
            'SELECT COUNT(*) FROM migraciones WHERE version = :version',
            ['version' => $migration->version],
        ) > 0;

        if (!$exists) {
            $db->execute(
                'INSERT INTO migraciones (version, name, filename, checksum, applied_at, execution_ms)
                 VALUES (:version, :name, :filename, :checksum, :applied_at, 0)',
                [
                    'version' => $migration->version,
                    'name' => $migration->name,
                    'filename' => $migration->filename,
                    'checksum' => $migration->checksum,
                    'applied_at' => date('Y-m-d H:i:s.u'),
                ],
            );
            echo "  +  registrada {$migration->version}\n";
        } else {
            echo "  =  migracion {$migration->version} ya registrada\n";
        }
    }
} else {
    echo "  =  tabla migraciones no existe; no se registro historial\n";
}

echo "\nListo. Aplicados: " . ($done !== [] ? implode(', ', $done) : '(nada nuevo)') . "\n";
if ($skip !== []) {
    echo "Ya existian: " . count($skip) . " elemento(s).\n";
}

