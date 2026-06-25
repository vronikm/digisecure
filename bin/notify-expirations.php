<?php
/**
 * =====================================================================
 * DigiSecure - CLI: Verificar vencimientos y despachar notificaciones
 * =====================================================================
 *
 * Job diario que para cada tenant activo:
 *   1. Marca documentos vencidos (ACEPTADO -> VENCIDO).
 *   2. Encola notificaciones de "documento vencido" y "por vencer" en N dias.
 *   3. Despacha la cola de notificaciones pendientes.
 *
 * Uso (desde la raiz del proyecto):
 *
 *     php bin/notify-expirations.php             # default 30 dias alerta
 *     php bin/notify-expirations.php --dias=15
 *     php bin/notify-expirations.php --dry-run   # no marca, no envia
 *     php bin/notify-expirations.php --tenant=ID # solo un tenant
 *
 * Programar como cron diario, p.ej. cada dia a las 06:00:
 *     0 6 * * * /usr/bin/php /var/www/digisecure/bin/notify-expirations.php
 *
 * NUNCA exponer este script via HTTP - bin/ esta bloqueado por .htaccess.
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
require $autoload;

use DigiSecure\Expediente\Application\VerificarVencimientosService;
use DigiSecure\Expediente\Infrastructure\ExpedienteRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Notification\Application\DespacharNotificacionesService;
use DigiSecure\Notification\Application\EncolarNotificacionService;
use DigiSecure\Notification\Domain\Enum\CanalNotificacion;
use DigiSecure\Notification\Infrastructure\Channel\EmailChannel;
use DigiSecure\Notification\Infrastructure\Channel\WhatsAppChannel;
use DigiSecure\Notification\Infrastructure\NotificacionRepository;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Tenant\Domain\TenantContext;

// --- Parsear argumentos ---
$diasAlerta = 30;
$dryRun = false;
$tenantFiltro = null;
$batch = 100;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if (str_starts_with($arg, '--dias=')) {
        $diasAlerta = (int) substr($arg, 7);
        continue;
    }
    if (str_starts_with($arg, '--tenant=')) {
        $tenantFiltro = substr($arg, 9);
        continue;
    }
    if (str_starts_with($arg, '--batch=')) {
        $batch = (int) substr($arg, 8);
        continue;
    }
    if (in_array($arg, ['-h', '--help', 'help'], true)) {
        echo <<<TXT
Uso: php bin/notify-expirations.php [opciones]

Opciones:
    --dias=N          Dias de anticipacion para alertas (default 30)
    --tenant=ID       Procesar solo un tenant especifico
    --batch=N         Tamano del lote de despacho (default 100)
    --dry-run         Solo reportar, no marcar ni enviar
    --help            Mostrar esta ayuda

TXT;
        exit(0);
    }
}

// --- Cargar config ---
if (!is_file(DIGISECURE_ROOT . '/.env')) {
    fwrite(STDERR, "ERROR: .env no encontrado.\n");
    exit(1);
}

$envVars = EnvLoader::load(DIGISECURE_ROOT . '/.env');
$config = new Config([
    'db' => [
        'host' => $envVars['DB_HOST'] ?? '127.0.0.1',
        'port' => $envVars['DB_PORT'] ?? '3306',
        'database' => $envVars['DB_DATABASE'] ?? 'digisecure',
        'username' => $envVars['DB_USERNAME'] ?? '',
        'password' => $envVars['DB_PASSWORD'] ?? '',
        'charset' => $envVars['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $envVars['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    ],
    'mail' => [
        'host' => $envVars['MAIL_HOST'] ?? '',
        'port' => (int) ($envVars['MAIL_PORT'] ?? 587),
        'username' => $envVars['MAIL_USERNAME'] ?? '',
        'password' => $envVars['MAIL_PASSWORD'] ?? '',
        'encryption' => $envVars['MAIL_ENCRYPTION'] ?? 'tls',
        'from_address' => $envVars['MAIL_FROM_ADDRESS'] ?? 'noreply@digisecure.local',
        'from_name' => $envVars['MAIL_FROM_NAME'] ?? 'DigiSecure',
    ],
    'whatsapp' => [
        'phone_number_id' => $envVars['WHATSAPP_PHONE_NUMBER_ID'] ?? '',
        'access_token' => $envVars['WHATSAPP_ACCESS_TOKEN'] ?? '',
        'timeout_seconds' => (int) ($envVars['WHATSAPP_TIMEOUT_SECONDS'] ?? 15),
    ],
]);

date_default_timezone_set($envVars['APP_TIMEZONE'] ?? 'America/Guayaquil');

try {
    $db = PdoConnection::fromConfig($config);

    // Listar tenants activos a procesar
    $sqlTenants = 'SELECT id, razon_social, nombre_comercial FROM tenants WHERE estado = :estado';
    $bindings = ['estado' => 'activo'];
    if ($tenantFiltro !== null) {
        $sqlTenants .= ' AND id = :id';
        $bindings['id'] = $tenantFiltro;
    }
    $tenants = $db->fetchAll($sqlTenants . ' ORDER BY razon_social', $bindings);

    if ($tenants === []) {
        echo "No hay tenants activos para procesar.\n";
        exit(0);
    }

    // Canal de email (compartido entre todos los tenants)
    $emailChannel = new EmailChannel(
        host: $config->string('mail.host', ''),
        port: $config->int('mail.port', 587),
        username: $config->string('mail.username', ''),
        password: $config->string('mail.password', ''),
        encryption: $config->string('mail.encryption', 'tls'),
        fromAddress: $config->string('mail.from_address', ''),
        fromName: $config->string('mail.from_name', 'DigiSecure'),
    );

    $whatsAppConfigured = $config->string('whatsapp.phone_number_id', '') !== ''
        && $config->string('whatsapp.access_token', '') !== '';

    $whatsAppChannel = new WhatsAppChannel(
        phoneNumberId: $config->string('whatsapp.phone_number_id', ''),
        accessToken: $config->string('whatsapp.access_token', ''),
        timeoutSeconds: $config->int('whatsapp.timeout_seconds', 15),
    );

    $totalVencidos = 0;
    $totalPorVencer = 0;
    $totalEncoladas = 0;
    $totalEnviadas = 0;
    $totalFallidas = 0;

    echo str_repeat('=', 78) . "\n";
    echo "DigiSecure :: Vencimientos y notificaciones\n";
    echo 'Modo: ' . ($dryRun ? 'DRY-RUN' : 'EJECUCION') . " | Dias alerta: {$diasAlerta} | Tenants: " . count($tenants) . "\n";
    echo 'Canales activos: EMAIL' . ($whatsAppConfigured ? ' + WHATSAPP' : '') . "\n";
    echo str_repeat('=', 78) . "\n";

    foreach ($tenants as $t) {
        $tenantId = (string) $t['id'];
        $tenantNombre = (string) ($t['nombre_comercial'] ?: $t['razon_social']);
        echo "\n-> Tenant: {$tenantNombre} ({$tenantId})\n";

        // Fresh tenant context per tenant (la invariante de TenantContext bloquea reset)
        $ctx = new TenantContext();
        $ctx->set($tenantId, $tenantNombre, 'activo');

        $audit = new AuditLogger($db, $ctx);
        $expRepo = new ExpedienteRepository($db, $ctx);
        $notifRepo = new NotificacionRepository($db, $ctx);
        $encolar = new EncolarNotificacionService($notifRepo, $audit);
        $verificar = new VerificarVencimientosService($expRepo, $audit, $encolar, $whatsAppConfigured);

        if ($dryRun) {
            $vencidos = $expRepo->getDocumentosVencidos();
            $porVencer = $expRepo->getDocumentosPorVencer($diasAlerta);
            echo '  . Documentos a marcar como VENCIDO: ' . count($vencidos) . "\n";
            echo "  . Documentos por vencer (<={$diasAlerta}d): " . count($porVencer) . "\n";
            continue;
        }

        $resVerif = $verificar->execute('cli', $diasAlerta);
        echo "  . Vencidos marcados: {$resVerif['vencidos']}\n";
        echo "  . Por vencer detectados: {$resVerif['por_vencer']}\n";
        echo "  . Notificaciones encoladas: {$resVerif['notificaciones']}\n";

        $totalVencidos += $resVerif['vencidos'];
        $totalPorVencer += $resVerif['por_vencer'];
        $totalEncoladas += $resVerif['notificaciones'];

        // Despachar cola del tenant
        $channels = [
            CanalNotificacion::EMAIL->value => $emailChannel,
        ];
        if ($whatsAppConfigured) {
            $channels[CanalNotificacion::WHATSAPP->value] = $whatsAppChannel;
        }

        $despachar = new DespacharNotificacionesService(
            $notifRepo,
            $audit,
            $channels,
        );
        $resDisp = $despachar->execute($batch, 'cli');
        echo "  . Despachadas: procesadas={$resDisp['procesadas']} enviadas={$resDisp['enviadas']} fallidas={$resDisp['fallidas']}\n";

        $totalEnviadas += $resDisp['enviadas'];
        $totalFallidas += $resDisp['fallidas'];
    }

    echo "\n" . str_repeat('-', 78) . "\n";
    echo "Resumen global:\n";
    echo "  Vencidos marcados:        {$totalVencidos}\n";
    echo "  Por vencer detectados:    {$totalPorVencer}\n";
    echo "  Notificaciones encoladas: {$totalEncoladas}\n";
    echo "  Enviadas con exito:       {$totalEnviadas}\n";
    echo "  Fallidas:                 {$totalFallidas}\n";
    echo str_repeat('=', 78) . "\n";

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "ERROR: {$e->getMessage()}\n");
    fwrite(STDERR, '  in ' . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(2);
}
