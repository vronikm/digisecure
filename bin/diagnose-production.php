<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: solo desde CLI.\n");
}

define('DIGISECURE_ROOT', dirname(__DIR__));

$autoload = DIGISECURE_ROOT . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "[ERROR] vendor/autoload.php no encontrado.\n");
    exit(1);
}
require $autoload;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;

$errors = 0;

function checkLine(bool $ok, string $label, string $detail = ''): void
{
    $status = $ok ? 'OK' : 'ERROR';
    echo "[{$status}] {$label}";
    if ($detail !== '') {
        echo " - {$detail}";
    }
    echo PHP_EOL;
}

function fail(string $label, string $detail = ''): void
{
    global $errors;
    $errors++;
    checkLine(false, $label, $detail);
}

function ok(string $label, string $detail = ''): void
{
    checkLine(true, $label, $detail);
}

echo "DigiSecure - diagnostico de produccion\n";
echo str_repeat('=', 48) . PHP_EOL;

ok('PHP', PHP_VERSION);
checkLine(PHP_VERSION_ID >= 80200, 'Version PHP >= 8.2.0');
if (PHP_VERSION_ID < 80200) {
    $errors++;
}

$envFile = DIGISECURE_ROOT . '/.env';
if (!is_file($envFile)) {
    fail('.env', 'No existe en la raiz del proyecto.');
    exit(1);
}
ok('.env', 'Existe');

$env = EnvLoader::load($envFile);

$required = [
    'APP_ENV',
    'APP_DEBUG',
    'APP_URL',
    'DB_HOST',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'ENCRYPTION_KEY',
];

foreach ($required as $key) {
    $value = trim((string)($env[$key] ?? ''));
    if ($value === '') {
        fail($key, 'Vacio o ausente');
    } else {
        ok($key, $key === 'DB_PASSWORD' || str_contains($key, 'KEY') ? 'Configurado' : $value);
    }
}

$enc = trim((string)($env['ENCRYPTION_KEY'] ?? ''));
if ($enc !== '') {
    checkLine((bool)preg_match('/^[0-9a-fA-F]{64}$/', $enc), 'ENCRYPTION_KEY formato hex 64');
    if (!preg_match('/^[0-9a-fA-F]{64}$/', $enc)) {
        $errors++;
    }
}

$appKey = trim((string)($env['APP_KEY'] ?? ''));
if ($appKey !== '') {
    checkLine((bool)preg_match('/^[0-9a-fA-F]{64}$/', $appKey), 'APP_KEY formato hex 64');
    if (!preg_match('/^[0-9a-fA-F]{64}$/', $appKey)) {
        $errors++;
    }
}

$writableDirs = [
    'var/cache',
    'var/logs',
    'var/sessions',
    'var/storage',
];

foreach ($writableDirs as $dir) {
    $path = DIGISECURE_ROOT . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0770, true);
    }
    checkLine(is_dir($path) && is_writable($path), $dir, is_dir($path) ? 'Directorio existe' : 'No existe');
    if (!is_dir($path) || !is_writable($path)) {
        $errors++;
    }
}

$config = new Config([
    'db' => [
        'host' => $env['DB_HOST'] ?? '127.0.0.1',
        'port' => $env['DB_PORT'] ?? '3306',
        'database' => $env['DB_DATABASE'] ?? '',
        'username' => $env['DB_USERNAME'] ?? '',
        'password' => $env['DB_PASSWORD'] ?? '',
        'charset' => $env['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $env['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    ],
]);

try {
    $db = PdoConnection::fromConfig($config);
    $db->fetchScalar('SELECT 1');
    ok('Conexion DB', 'Correcta');
} catch (Throwable $e) {
    fail('Conexion DB', $e->getMessage());
}

echo str_repeat('=', 48) . PHP_EOL;
if ($errors > 0) {
    echo "Diagnostico final: {$errors} problema(s) encontrados.\n";
    exit(2);
}

echo "Diagnostico final: listo para arrancar.\n";
