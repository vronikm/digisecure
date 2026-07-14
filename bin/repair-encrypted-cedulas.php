<?php
/**
 * Repara cedulas de fichas cifradas con una clave anterior.
 *
 * Solo re-cifra cuando la ficha pertenece a un aspirante con cedula valida
 * y el hash de ambos documentos coincide. No imprime cedulas ni claves.
 *
 * Uso:
 *   php bin/repair-encrypted-cedulas.php --dry-run
 *   php bin/repair-encrypted-cedulas.php --apply
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: este script solo se ejecuta desde CLI.\n");
}

require dirname(__DIR__) . '/vendor/autoload.php';

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Security\Crypto\Encryption;

$apply = in_array('--apply', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);

if ($apply === $dryRun) {
    fwrite(STDERR, "Uso: php bin/repair-encrypted-cedulas.php --dry-run | --apply\n");
    exit(1);
}

$root = dirname(__DIR__);
$env = EnvLoader::load($root . '/.env');
date_default_timezone_set($env['APP_TIMEZONE'] ?? 'America/Guayaquil');

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

$encryption = Encryption::fromHexKey((string) ($env['ENCRYPTION_KEY'] ?? ''));
$db = PdoConnection::fromConfig($config);
$rows = $db->fetchAll(
    "SELECT f.id, f.cedula_hash, f.cedula_cifrada, a.documento
       FROM fichas f
       INNER JOIN aspirantes a ON a.usuario_id = f.created_by
      WHERE a.tipo_documento = 'cedula'
        AND a.documento REGEXP '^[0-9]{10}$'
        AND f.cedula_cifrada IS NOT NULL
        AND f.cedula_cifrada <> ''",
);

$stats = [
    'analizadas' => count($rows),
    'ya_legibles' => 0,
    'reparadas' => 0,
    'omitidas' => 0,
];

$db->transactional(function (PdoConnection $connection) use ($rows, $encryption, $apply, &$stats): void {
    foreach ($rows as $row) {
        $ciphertext = (string) $row['cedula_cifrada'];
        try {
            $encryption->decrypt($ciphertext);
            $stats['ya_legibles']++;
            continue;
        } catch (Throwable) {
            // Se valida una fuente primaria antes de re-cifrar.
        }

        $documento = (string) $row['documento'];
        if (!hash_equals((string) $row['cedula_hash'], hash('sha256', $documento))) {
            $stats['omitidas']++;
            continue;
        }

        if ($apply) {
            $connection->execute(
                'UPDATE fichas
                    SET cedula_cifrada = :cedula_cifrada,
                        updated_at = UTC_TIMESTAMP(6)
                  WHERE id = :id
                    AND cedula_hash = :cedula_hash',
                [
                    'cedula_cifrada' => $encryption->encrypt($documento),
                    'id' => (string) $row['id'],
                    'cedula_hash' => (string) $row['cedula_hash'],
                ],
            );
        }

        $stats['reparadas']++;
    }
});

echo sprintf(
    "Cedulas: %d analizadas, %d ya legibles, %d %s, %d omitidas.\n",
    $stats['analizadas'],
    $stats['ya_legibles'],
    $stats['reparadas'],
    $apply ? 'reparadas' : 'reparables',
    $stats['omitidas'],
);
