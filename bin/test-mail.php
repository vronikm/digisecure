<?php

declare(strict_types=1);

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

use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Notification\Infrastructure\Channel\EmailChannel;

$to = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        showHelp();
        exit(0);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = trim(substr($arg, 5));
    }
}

if ($to === null || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "ERROR: indica un destinatario valido con --to=correo@dominio.com\n\n");
    showHelp();
    exit(1);
}

$envPath = DIGISECURE_ROOT . '/.env';
if (!is_file($envPath)) {
    fwrite(STDERR, "ERROR: .env no encontrado en la raiz del proyecto.\n");
    exit(1);
}

$env = EnvLoader::load($envPath);
$driver = strtolower(trim($env['MAIL_DRIVER'] ?? 'smtp'));
$host = trim($env['MAIL_HOST'] ?? '');
$port = (int) ($env['MAIL_PORT'] ?? 587);
$username = trim($env['MAIL_USERNAME'] ?? '');
$password = (string) ($env['MAIL_PASSWORD'] ?? '');
$encryption = trim($env['MAIL_ENCRYPTION'] ?? 'tls');
$fromAddress = trim($env['MAIL_FROM_ADDRESS'] ?? '');
$fromName = trim($env['MAIL_FROM_NAME'] ?? 'DigiSecure');

echo "DigiSecure mail test\n";
echo "Destino: {$to}\n";
echo "Driver: {$driver}\n";
echo "Host configurado: " . yesNo($host !== '') . "\n";
echo "Puerto: {$port}\n";
echo "Encryption: " . ($encryption !== '' ? $encryption : '(ninguna)') . "\n";
echo "Username configurado: " . yesNo($username !== '') . "\n";
echo "Password configurado: " . yesNo($password !== '') . "\n";
echo "From configurado: " . yesNo($fromAddress !== '') . "\n";
echo "PHPMailer disponible: " . yesNo(class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) . "\n\n";

$channel = new EmailChannel(
    host: $host,
    port: $port,
    username: $username,
    password: $password,
    encryption: $encryption,
    fromAddress: $fromAddress,
    fromName: $fromName,
    driver: $driver,
);

$now = date('Y-m-d H:i:s');
$result = $channel->send([
    'destinatario_email' => $to,
    'destinatario_nombre' => 'Prueba DigiSecure',
    'asunto' => 'DigiSecure - prueba de correo transaccional',
    'cuerpo_texto' => "Prueba de correo transaccional DigiSecure.\nFecha servidor: {$now}\n",
    'cuerpo_html' => "<p>Prueba de correo transaccional DigiSecure.</p><p>Fecha servidor: <strong>{$now}</strong></p>",
]);

if ($result->success) {
    echo "OK: correo aceptado por el canal ({$result->codigo}) en {$result->duracionMs} ms.\n";
    exit(0);
}

echo "ERROR: no se pudo enviar.\n";
echo "Codigo: " . ($result->codigo ?? 'sin_codigo') . "\n";
echo "Detalle: " . ($result->mensaje ?? 'sin detalle') . "\n";
exit(2);

function yesNo(bool $value): string
{
    return $value ? 'si' : 'no';
}

function showHelp(): void
{
    echo <<<TXT
Uso:
  php bin/test-mail.php --to=correo@dominio.com

Valida el canal de correo usando la configuracion MAIL_* del .env.
No imprime passwords ni secretos.

TXT;
}
