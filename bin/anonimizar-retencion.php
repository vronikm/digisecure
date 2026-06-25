<?php
/**
 * =====================================================================
 * DigiSecure - CLI: Anonimización por retención LOPDP (5 años post-baja)
 * =====================================================================
 *
 * Job (idealmente mensual) que aplica el período de retención de la
 * Ley Orgánica de Protección de Datos Personales del Ecuador:
 *
 *   Decisión vinculante #9: las fichas dadas de baja se conservan
 *   5 años para fines de auditoría e integridad referencial. Pasado
 *   ese plazo, los datos personales (PII) se anonimizan: la fila
 *   permanece en BD, pero los campos sensibles quedan neutralizados.
 *
 * Tablas afectadas:
 *   - fichas: nombres, apellidos, cédula (hash + cifrada), fecha_nac,
 *     género, dirección, contactos, instrucción, email, banco, etc.
 *   - fichas_trabajos_anteriores
 *   - fichas_hijos_menores
 *   - fichas_referencias
 *
 * Criterio de selección:
 *   fecha_baja IS NOT NULL
 *   AND fecha_baja <= CURDATE() - INTERVAL 5 YEAR
 *   AND anonimizada_at IS NULL
 *
 * Uso:
 *     php bin/anonimizar-retencion.php             # ejecuta
 *     php bin/anonimizar-retencion.php --dry-run   # solo reporta
 *     php bin/anonimizar-retencion.php --tenant=ID # un tenant
 *
 * Programar mensualmente:
 *     0 3 1 * * /usr/bin/php /var/www/digisecure/bin/anonimizar-retencion.php
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

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Config\EnvLoader;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Tenant\Domain\TenantContext;

// --- Args ---
$dryRun = false;
$tenantFiltro = null;
$retencionAnios = 5;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') { $dryRun = true; continue; }
    if (str_starts_with($arg, '--tenant=')) { $tenantFiltro = substr($arg, 9); continue; }
    if (str_starts_with($arg, '--anios=')) { $retencionAnios = (int)substr($arg, 8); continue; }
    if (in_array($arg, ['-h', '--help', 'help'], true)) {
        echo <<<TXT
Uso: php bin/anonimizar-retencion.php [opciones]

Opciones:
    --dry-run         Solo reporta, no escribe en BD
    --tenant=ID       Procesar solo un tenant específico
    --anios=N         Años de retención (default 5, conforme LOPDP)
    --help            Mostrar esta ayuda

TXT;
        exit(0);
    }
}

if ($retencionAnios < 1) {
    fwrite(STDERR, "ERROR: --anios debe ser >= 1.\n");
    exit(1);
}

// --- Config ---
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
]);
date_default_timezone_set($envVars['APP_TIMEZONE'] ?? 'America/Guayaquil');

try {
    $db = PdoConnection::fromConfig($config);

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

    echo str_repeat('=', 78) . "\n";
    echo "DigiSecure :: Anonimización LOPDP por retención\n";
    echo 'Modo: ' . ($dryRun ? 'DRY-RUN' : 'EJECUCIÓN') . " | Retención: {$retencionAnios} años | Tenants: " . count($tenants) . "\n";
    echo str_repeat('=', 78) . "\n";

    $totalAnonimizadas = 0;
    $totalCandidatas = 0;
    $now = Clock::nowString();

    foreach ($tenants as $t) {
        $tenantId = (string) $t['id'];
        $tenantNombre = (string) ($t['nombre_comercial'] ?: $t['razon_social']);
        echo "\n-> Tenant: {$tenantNombre} ({$tenantId})\n";

        $ctx = new TenantContext();
        $ctx->set($tenantId, $tenantNombre, 'activo');
        $audit = new AuditLogger($db, $ctx);

        // Candidatas: fecha_baja vencida y aún no anonimizadas
        $candidatas = $db->fetchAll(
            'SELECT id, correlativo, fecha_baja
               FROM fichas
              WHERE tenant_id = :tid
                AND fecha_baja IS NOT NULL
                AND fecha_baja <= DATE_SUB(CURDATE(), INTERVAL :anios YEAR)
                AND anonimizada_at IS NULL',
            ['tid' => $tenantId, 'anios' => $retencionAnios],
        );

        $totalCandidatas += count($candidatas);
        echo '  . Fichas candidatas: ' . count($candidatas) . "\n";

        if ($candidatas === [] || $dryRun) {
            continue;
        }

        $anonimizadasTenant = 0;

        foreach ($candidatas as $f) {
            $fichaId = (string) $f['id'];

            $db->beginTransaction();
            try {
                // 1) Neutralizar PII directa de la ficha. La fila se conserva
                //    para integridad referencial y estadísticas, pero ya no
                //    contiene datos personales identificables.
                $db->execute(
                    "UPDATE fichas SET
                        nombres = '[ANONIMIZADO]',
                        apellidos = '[ANONIMIZADO]',
                        cedula_hash = REPEAT('0', 64),
                        cedula_cifrada = 0x00,
                        fecha_nacimiento = '1900-01-01',
                        genero = 'PREFIERO_NO_DECIR',
                        lugar_nacimiento_ciudad = NULL,
                        lugar_nacimiento_provincia = NULL,
                        tipo_sangre = NULL,
                        estatura = NULL,
                        celular = NULL,
                        email = NULL,
                        banco = NULL,
                        tipo_cuenta = NULL,
                        cuenta_bancaria_cifrada = NULL,
                        domicilio_ciudad = NULL,
                        domicilio_tipo_vivienda = NULL,
                        domicilio_calle = NULL,
                        domicilio_numero = NULL,
                        domicilio_barrio = NULL,
                        domicilio_referencia = NULL,
                        domicilio_telefono = NULL,
                        contacto_emergencia_nombre = NULL,
                        contacto_emergencia_telefono = NULL,
                        instruccion_primaria = NULL,
                        instruccion_secundaria = NULL,
                        instruccion_superior = NULL,
                        croquis_latitud = NULL,
                        croquis_longitud = NULL,
                        anonimizada_at = :now,
                        updated_at = :now
                     WHERE id = :id AND tenant_id = :tid",
                    ['now' => $now, 'id' => $fichaId, 'tid' => $tenantId],
                );

                // 2) Borrar relacionadas con PII de terceros (hijos, referencias).
                //    Trabajos anteriores se conservan SIN datos identificables.
                $db->execute(
                    'DELETE FROM fichas_hijos_menores WHERE ficha_id = :fid AND tenant_id = :tid',
                    ['fid' => $fichaId, 'tid' => $tenantId],
                );
                $db->execute(
                    'DELETE FROM fichas_referencias WHERE ficha_id = :fid AND tenant_id = :tid',
                    ['fid' => $fichaId, 'tid' => $tenantId],
                );
                $db->execute(
                    "UPDATE fichas_trabajos_anteriores
                        SET telefonos = NULL, direccion = NULL
                      WHERE ficha_id = :fid AND tenant_id = :tid",
                    ['fid' => $fichaId, 'tid' => $tenantId],
                );

                $audit->log(
                    'lopdp.ficha_anonimizada',
                    null,
                    'ficha',
                    $fichaId,
                    [
                        'correlativo' => $f['correlativo'],
                        'fecha_baja' => $f['fecha_baja'],
                        'retencion_anios' => $retencionAnios,
                    ],
                    nivel: 'warning',
                );

                $db->commit();
                $anonimizadasTenant++;
            } catch (\Throwable $e) {
                $db->rollBack();
                fwrite(STDERR, "  ! Error en ficha {$fichaId}: {$e->getMessage()}\n");
            }
        }

        echo "  . Anonimizadas: {$anonimizadasTenant}\n";
        $totalAnonimizadas += $anonimizadasTenant;
    }

    echo "\n" . str_repeat('-', 78) . "\n";
    echo "Resumen global:\n";
    echo "  Candidatas detectadas: {$totalCandidatas}\n";
    echo '  Anonimizadas:          ' . ($dryRun ? '0 (dry-run)' : (string)$totalAnonimizadas) . "\n";
    echo str_repeat('=', 78) . "\n";

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "ERROR: {$e->getMessage()}\n");
    fwrite(STDERR, '  in ' . $e->getFile() . ':' . $e->getLine() . "\n");
    exit(2);
}
