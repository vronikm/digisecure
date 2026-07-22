<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: este script solo se ejecuta desde CLI.\n");
}

define('DIGISECURE_ROOT', dirname(__DIR__));

require DIGISECURE_ROOT . '/vendor/autoload.php';

use DigiSecure\Ficha\Infrastructure\CroquisStorage;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Kernel\Config\StoragePathResolver;

$email = '';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--email=')) {
        $email = mb_strtolower(trim(substr($argument, 8)), 'UTF-8');
    }
}

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Uso: php bin/diagnose-ficha-images.php --email=usuario@dominio.com\n");
    exit(1);
}

$env = EnvLoader::load(DIGISECURE_ROOT . '/.env');
$db = PdoConnection::fromConfig(new Config([
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
$storage = new CroquisStorage(StoragePathResolver::resolve(
    DIGISECURE_ROOT,
    (string) ($env['STORAGE_PATH'] ?? 'var/storage'),
    'croquis',
));

$rows = $db->fetchAll(
    'SELECT u.id AS usuario_id,
            f.id AS ficha_id,
            f.estado,
            f.foto_archivo_id,
            f.croquis_archivo_id,
            f.croquis_latitud,
            f.croquis_longitud,
            f.updated_at
       FROM usuarios u
       LEFT JOIN fichas f ON f.created_by = u.id
      WHERE u.email_normalizado = :email
      ORDER BY f.created_at DESC',
    ['email' => $email],
);

echo 'Usuario encontrado: ' . ($rows !== [] ? 'si' : 'no') . PHP_EOL;
echo 'Fichas relacionadas: ' . count(array_filter(
    $rows,
    static fn(array $row): bool => !empty($row['ficha_id']),
)) . PHP_EOL;

foreach ($rows as $index => $row) {
    if (empty($row['ficha_id'])) {
        echo 'Sin ficha creada.' . PHP_EOL;
        continue;
    }

    echo PHP_EOL . 'Ficha ' . ($index + 1) . ':' . PHP_EOL;
    echo '- ID: ' . (string) $row['ficha_id'] . PHP_EOL;
    echo '- Estado: ' . (string) ($row['estado'] ?? '') . PHP_EOL;
    echo '- Actualizada: ' . (string) ($row['updated_at'] ?? '') . PHP_EOL;
    echo '- Coordenadas de croquis registradas: ' . (
        is_numeric($row['croquis_latitud'] ?? null) && is_numeric($row['croquis_longitud'] ?? null)
            ? 'si'
            : 'no'
    ) . PHP_EOL;
    printImageStatus('Foto', $row['foto_archivo_id'] ?? null, $storage);
    printImageStatus('Croquis', $row['croquis_archivo_id'] ?? null, $storage);
}

/**
 * @param mixed $archivoId
 */
function printImageStatus(string $label, mixed $archivoId, CroquisStorage $storage): void
{
    $id = is_string($archivoId) ? trim($archivoId) : '';
    echo "- {$label}:" . PHP_EOL;
    echo '  - ID registrado: ' . ($id !== '' ? 'si' : 'no') . PHP_EOL;
    if ($id === '') {
        return;
    }
    echo '  - ID: ' . $id . PHP_EOL;

    $file = $storage->find($id);
    echo '  - Archivo encontrado: ' . ($file !== null ? 'si' : 'no') . PHP_EOL;
    if ($file !== null) {
        echo '  - MIME: ' . $file['mime'] . PHP_EOL;
        echo '  - Tamano: ' . $file['size'] . ' bytes' . PHP_EOL;
    }
}
