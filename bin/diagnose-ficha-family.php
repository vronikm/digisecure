<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: este script solo se ejecuta desde CLI.\n");
}

define('DIGISECURE_ROOT', dirname(__DIR__));

require DIGISECURE_ROOT . '/vendor/autoload.php';

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Security\Crypto\Encryption;

$email = '';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--email=')) {
        $email = mb_strtolower(trim(substr($argument, 8)), 'UTF-8');
    }
}

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Uso: php bin/diagnose-ficha-family.php --email=usuario@dominio.com\n");
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
$encryption = Encryption::fromHexKey((string) ($env['ENCRYPTION_KEY'] ?? ''));

$ficha = $db->fetchOne(
    'SELECT f.id, f.estado, f.estado_civil, f.tenant_id
       FROM usuarios u
       INNER JOIN fichas f ON f.created_by = u.id
      WHERE u.email_normalizado = :email
        AND f.estado <> :anulada
      ORDER BY f.created_at DESC
      LIMIT 1',
    ['email' => $email, 'anulada' => 'ANULADA'],
);

echo 'Ficha encontrada: ' . ($ficha !== null ? 'si' : 'no') . PHP_EOL;
if ($ficha === null) {
    exit(0);
}

echo '- ID: ' . (string) $ficha['id'] . PHP_EOL;
echo '- Estado: ' . (string) $ficha['estado'] . PHP_EOL;
echo '- Estado civil: ' . (string) ($ficha['estado_civil'] ?? '') . PHP_EOL;

$familiares = $db->fetchAll(
    'SELECT parentesco
       FROM fichas_familiares
      WHERE ficha_id = :ficha_id
      ORDER BY parentesco',
    ['ficha_id' => (string) $ficha['id']],
);
echo 'Familiares almacenados: ' . count($familiares) . PHP_EOL;
if ($familiares !== []) {
    echo '- Parentescos: ' . implode(', ', array_map(
        static fn(array $row): string => (string) ($row['parentesco'] ?? ''),
        $familiares,
    )) . PHP_EOL;
}

$conyuge = $db->fetchOne(
    "SELECT id, tenant_id, ficha_id, nombre, cedula_hash, cedula_cifrada, edad, created_at, updated_at
       FROM fichas_familiares
      WHERE ficha_id = :ficha_id
        AND parentesco = 'CONYUGE'
      LIMIT 1",
    ['ficha_id' => (string) $ficha['id']],
);

echo 'Conyuge registrado: ' . ($conyuge !== null ? 'si' : 'no') . PHP_EOL;
if ($conyuge === null) {
    exit(0);
}

$cedulaDescifrable = false;
$hashCoincide = false;
try {
    $cedula = $encryption->decrypt((string) ($conyuge['cedula_cifrada'] ?? ''));
    $cedulaDescifrable = $cedula !== '';
    $hashCoincide = $cedulaDescifrable
        && hash_equals((string) ($conyuge['cedula_hash'] ?? ''), $encryption->blindIndexer()->cedula($cedula));
} catch (Throwable) {
    // El diagnostico no expone el dato ni el detalle criptografico.
}

echo '- Nombre almacenado: ' . (trim((string) ($conyuge['nombre'] ?? '')) !== '' ? 'si' : 'no') . PHP_EOL;
echo '- Cedula cifrada almacenada: ' . (!empty($conyuge['cedula_cifrada']) ? 'si' : 'no') . PHP_EOL;
echo '- Cedula descifrable: ' . ($cedulaDescifrable ? 'si' : 'no') . PHP_EOL;
echo '- Hash de cedula consistente: ' . ($hashCoincide ? 'si' : 'no') . PHP_EOL;
echo '- Edad almacenada: ' . (is_numeric($conyuge['edad'] ?? null) ? 'si' : 'no') . PHP_EOL;
echo '- Tenant coincide con ficha: ' . (
    hash_equals((string) $ficha['tenant_id'], (string) ($conyuge['tenant_id'] ?? '')) ? 'si' : 'no'
) . PHP_EOL;
echo '- Creado: ' . (string) ($conyuge['created_at'] ?? '') . PHP_EOL;
echo '- Actualizado: ' . (string) ($conyuge['updated_at'] ?? '') . PHP_EOL;
