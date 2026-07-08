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

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;

$args = parseArgs(array_slice($argv, 1));
if (isset($args['help'])) {
    showHelp();
    exit(0);
}

$email = mb_strtolower(trim((string) ($args['email'] ?? '')), 'UTF-8');
$documento = preg_replace('/\s+/', '', trim((string) ($args['documento'] ?? ''))) ?? '';

if ($email === '' && $documento === '') {
    fwrite(STDERR, "ERROR: indica --email=correo@dominio.com o --documento=numero.\n\n");
    showHelp();
    exit(1);
}

$envPath = DIGISECURE_ROOT . '/.env';
if (!is_file($envPath)) {
    fwrite(STDERR, "ERROR: .env no encontrado en la raiz del proyecto.\n");
    exit(1);
}

$env = EnvLoader::load($envPath);
date_default_timezone_set($env['APP_TIMEZONE'] ?? 'America/Guayaquil');

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

echo line('=');
echo "DigiSecure :: Diagnostico de aspirante\n";
echo line('=');
printMailConfig($env);
echo "\n";

$aspirante = findAspirante($db, $email, $documento);
if ($aspirante === null) {
    echo "Postulacion: NO encontrada\n";
    echo "- El correo/documento no existe en aspirantes, o se uso otro dato en el registro.\n";
    echo "- En /verificar/reenviar no se informa esto al usuario por seguridad anti-enumeracion.\n";
    printLogHints();
    exit(0);
}

printAspirante($aspirante);
printEventos($db, $aspirante);
printReenvioStatus($db, $aspirante);
printLogHints();

exit(0);

/**
 * @param list<string> $argv
 * @return array<string, string|bool>
 */
function parseArgs(array $argv): array
{
    $out = [];
    foreach ($argv as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $out['help'] = true;
            continue;
        }
        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$key, $value] = explode('=', substr($arg, 2), 2);
            $out[$key] = $value;
        }
    }
    return $out;
}

function findAspirante(PdoConnection $db, string $email, string $documento): ?array
{
    $where = [];
    $bind = [];
    if ($email !== '') {
        $where[] = 'correo_normalizado = :email';
        $bind['email'] = $email;
    }
    if ($documento !== '') {
        $where[] = 'documento = :documento';
        $bind['documento'] = $documento;
    }

    return $db->fetchOne(
        'SELECT id, tenant_id, usuario_id, tipo_documento, documento, nombres, apellidos,
                correo, correo_normalizado, celular, estado, email_verificado,
                email_verificado_at, verificacion_token_expira_at, created_at, updated_at
           FROM aspirantes
          WHERE ' . implode(' OR ', $where) . '
          ORDER BY created_at DESC
          LIMIT 1',
        $bind,
    );
}

function printMailConfig(array $env): void
{
    $driver = strtolower(trim((string) ($env['MAIL_DRIVER'] ?? 'smtp')));
    $host = trim((string) ($env['MAIL_HOST'] ?? ''));
    $from = trim((string) ($env['MAIL_FROM_ADDRESS'] ?? ''));

    echo "Correo transaccional:\n";
    echo "- APP_URL: " . (($env['APP_URL'] ?? '') !== '' ? (string) $env['APP_URL'] : '(vacio)') . "\n";
    echo "- MAIL_DRIVER: {$driver}\n";
    echo "- MAIL_HOST configurado: " . yesNo($host !== '') . "\n";
    echo "- MAIL_USERNAME configurado: " . yesNo(trim((string) ($env['MAIL_USERNAME'] ?? '')) !== '') . "\n";
    echo "- MAIL_PASSWORD configurado: " . yesNo(trim((string) ($env['MAIL_PASSWORD'] ?? '')) !== '') . "\n";
    echo "- MAIL_FROM_ADDRESS: " . ($from !== '' ? $from : '(vacio)') . "\n";
    echo "- PHPMailer disponible: " . yesNo(class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) . "\n";

    if ($driver === 'log') {
        echo "  ALERTA: MAIL_DRIVER=log no envia correos reales; guarda el contenido en var/logs/mail.log.\n";
    } elseif ($driver === 'smtp' && $host === '') {
        echo "  ALERTA: MAIL_DRIVER=smtp sin MAIL_HOST cae a mail() nativo del servidor.\n";
    }
    if ($from === '' || str_ends_with($from, '.local')) {
        echo "  ALERTA: MAIL_FROM_ADDRESS debe ser un correo real del dominio en produccion.\n";
    }
}

function printAspirante(array $a): void
{
    $nombre = trim((string) ($a['nombres'] ?? '') . ' ' . (string) ($a['apellidos'] ?? ''));
    $verificado = (int) ($a['email_verificado'] ?? 0) === 1;

    echo "Postulacion encontrada:\n";
    echo "- ID: " . (string) $a['id'] . "\n";
    echo "- Nombre: {$nombre}\n";
    echo "- Documento: " . (string) $a['tipo_documento'] . ' ' . (string) $a['documento'] . "\n";
    echo "- Correo: " . (string) $a['correo'] . "\n";
    echo "- Estado: " . (string) $a['estado'] . "\n";
    echo "- Email verificado: " . yesNo($verificado) . "\n";
    echo "- Usuario creado: " . yesNo(!empty($a['usuario_id'])) . "\n";
    echo "- Tenant ID: " . ((string) ($a['tenant_id'] ?? '') !== '' ? (string) $a['tenant_id'] : '(sin tenant)') . "\n";
    echo "- Token expira: " . ((string) ($a['verificacion_token_expira_at'] ?? '') !== '' ? (string) $a['verificacion_token_expira_at'] : '(sin token pendiente)') . "\n";
    echo "- Creado: " . (string) $a['created_at'] . "\n";
}

function printEventos(PdoConnection $db, array $a): void
{
    echo "\nUltimos eventos:\n";
    try {
        $rows = $db->fetchAll(
            'SELECT evento, email, detalles_json, created_at
               FROM aspirante_eventos
              WHERE aspirante_id = :id OR email = :email
              ORDER BY created_at DESC
              LIMIT 8',
            [
                'id' => (string) $a['id'],
                'email' => (string) $a['correo_normalizado'],
            ],
        );
    } catch (Throwable $e) {
        echo "- No se pudo leer aspirante_eventos: {$e->getMessage()}\n";
        return;
    }

    if ($rows === []) {
        echo "- Sin eventos registrados.\n";
        return;
    }

    foreach ($rows as $row) {
        echo '- ' . (string) $row['created_at'] . ' :: ' . (string) $row['evento'];
        if (!empty($row['detalles_json'])) {
            echo ' :: ' . (string) $row['detalles_json'];
        }
        echo "\n";
    }
}

function printReenvioStatus(PdoConnection $db, array $a): void
{
    echo "\nDiagnostico de /verificar/reenviar:\n";

    $estado = (string) ($a['estado'] ?? '');
    $verificado = (int) ($a['email_verificado'] ?? 0) === 1;
    if ($verificado) {
        echo "- No aplica reenvio: el correo ya esta verificado. Debe iniciar sesion o recuperar password.\n";
        return;
    }
    if ($estado !== 'nuevo') {
        echo "- No aplica reenvio automatico: el estado actual es '{$estado}', no 'nuevo'.\n";
        return;
    }

    $desde = (new DateTimeImmutable('now'))->modify('-60 minutes')->format('Y-m-d H:i:s.u');
    try {
        $reenvios = (int) $db->fetchScalar(
            "SELECT COUNT(*) FROM aspirante_eventos
              WHERE evento = 'reenvio_link'
                AND email = :email
                AND created_at >= :desde",
            ['email' => (string) $a['correo_normalizado'], 'desde' => $desde],
        );
    } catch (Throwable) {
        $reenvios = 0;
    }

    echo "- Aplica reenvio: si.\n";
    echo "- Reenvios ultimos 60 min por correo: {$reenvios}/3.\n";
    if ($reenvios >= 3) {
        echo "  ALERTA: rate-limit activo; esperar una hora o revisar desde otra IP/correo.\n";
    }
}

function printLogHints(): void
{
    echo "\nArchivos a revisar en produccion:\n";
    foreach ([
        'var/logs/verificacion.log',
        'var/logs/mail.log',
        'var/logs/digisecure.log',
    ] as $rel) {
        $path = DIGISECURE_ROOT . '/' . $rel;
        if (!is_file($path)) {
            echo "- {$rel}: no existe\n";
            continue;
        }
        echo "- {$rel}: " . filesize($path) . ' bytes, modificado ' . date('Y-m-d H:i:s', filemtime($path)) . "\n";
    }
}

function yesNo(bool $value): string
{
    return $value ? 'si' : 'no';
}

function line(string $char): string
{
    return str_repeat($char, 78) . "\n";
}

function showHelp(): void
{
    echo <<<TXT
Uso:
  php bin/diagnose-aspirante.php --email=correo@dominio.com
  php bin/diagnose-aspirante.php --documento=0102030405
  php bin/diagnose-aspirante.php --email=correo@dominio.com --documento=0102030405

Muestra estado del aspirante, si el reenvio aplica y configuracion MAIL_*
sin imprimir passwords ni secretos.

TXT;
}
