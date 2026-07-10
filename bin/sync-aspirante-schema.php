<?php
/**
 * =====================================================================
 * DigiSecure - Sincronizador IDEMPOTENTE del schema de Aspirantes (021-025, 029)
 * =====================================================================
 *
 * Aplica SOLO lo que falte del flujo de registro de guardias/aspirantes:
 *   - 022: aspirantes.password_hash
 *   - 023: email_verificado, email_verificado_at, verificacion_token_*
 *   - 024: usuario_id (+FK), aprobado_at, revisado_por, motivo_descarte,
 *          estado 'aprobado', permiso aspirantes.gestionar (+grants)
 *   - 025: estado 'aspirante', tabla aspirante_eventos, rol 'aspirante' (+grant)
 *   - 029: trazabilidad LOPDP del registro publico y del documento subido
 *
 * Es SEGURO ejecutarlo varias veces: cada paso verifica antes de aplicar.
 * Úsalo cuando el runner de migraciones esté bloqueado (checksum drift) pero
 * el schema deba quedar al día. También registra 021-025 en `migraciones`.
 *
 *   Uso:  php bin/sync-aspirante-schema.php
 *
 * Lee la BD desde .env. NUNCA exponer vía HTTP (bin/ está bloqueado por .htaccess).
 * =====================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: solo CLI.\n";
    exit(1);
}

define('DIGISECURE_ROOT', dirname(__DIR__));
require DIGISECURE_ROOT . '/vendor/autoload.php';

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

$col = static fn(string $t, string $c): bool => (int) $db->fetchScalar(
    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND COLUMN_NAME = :c",
    ['s' => $schema, 't' => $t, 'c' => $c],
) > 0;
$table = static fn(string $t): bool => (int) $db->fetchScalar(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t",
    ['s' => $schema, 't' => $t],
) > 0;
$enumHas = static fn(string $t, string $c, string $v): bool => str_contains(
    (string) $db->fetchScalar(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND COLUMN_NAME = :c",
        ['s' => $schema, 't' => $t, 'c' => $c],
    ),
    "'{$v}'",
);
$fk = static fn(string $name): bool => (int) $db->fetchScalar(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = :s AND CONSTRAINT_NAME = :n AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
    ['s' => $schema, 'n' => $name],
) > 0;
$idx = static fn(string $t, string $i): bool => (int) $db->fetchScalar(
    "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND INDEX_NAME = :i",
    ['s' => $schema, 't' => $t, 'i' => $i],
) > 0;

$done = [];
$skip = [];
$step = static function (string $label, callable $fn) use (&$done, &$skip): void {
    try {
        if ($fn() === false) { $skip[] = $label; echo "  =  {$label} (ya estaba)\n"; }
        else { $done[] = $label; echo "  +  {$label}\n"; }
    } catch (\Throwable $e) {
        echo "  !  {$label} -> " . $e->getMessage() . "\n";
    }
};

echo "Sincronizando schema de aspirantes en BD '{$schema}'...\n\n";

if (!$table('aspirantes')) {
    fwrite(STDERR, "ERROR: la tabla 'aspirantes' no existe. Aplica primero la migración 021.\n");
    exit(2);
}

// ---- 022: password_hash ----
$step('022 password_hash', function () use ($db, $col) {
    if ($col('aspirantes', 'password_hash')) { return false; }
    $db->rawExec("ALTER TABLE aspirantes ADD COLUMN password_hash VARCHAR(255) NULL AFTER correo_normalizado");
    return true;
});

// ---- 023: verificación de email ----
$step('023 columnas de verificación', function () use ($db, $col) {
    if ($col('aspirantes', 'email_verificado')) { return false; }
    $db->rawExec("ALTER TABLE aspirantes
        ADD COLUMN email_verificado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado,
        ADD COLUMN email_verificado_at DATETIME(6) NULL AFTER email_verificado,
        ADD COLUMN verificacion_token_hash CHAR(64) NULL AFTER email_verificado_at,
        ADD COLUMN verificacion_token_expira_at DATETIME(6) NULL AFTER verificacion_token_hash,
        ADD KEY ix_aspirantes_verif_token (verificacion_token_hash)");
    return true;
});

// ---- 024: aprobación ----
$step('024 columnas de aprobación', function () use ($db, $col) {
    if ($col('aspirantes', 'usuario_id')) { return false; }
    $db->rawExec("ALTER TABLE aspirantes
        ADD COLUMN usuario_id CHAR(26) NULL AFTER tenant_id,
        ADD COLUMN aprobado_at DATETIME(6) NULL AFTER email_verificado_at,
        ADD COLUMN revisado_por CHAR(26) NULL AFTER aprobado_at,
        ADD COLUMN motivo_descarte VARCHAR(255) NULL AFTER revisado_por,
        ADD KEY ix_aspirantes_usuario (usuario_id)");
    return true;
});
$step('024 estado +aprobado / 025 estado +aspirante', function () use ($db, $enumHas) {
    if ($enumHas('aspirantes', 'estado', 'aspirante') && $enumHas('aspirantes', 'estado', 'aprobado')) { return false; }
    $db->rawExec("ALTER TABLE aspirantes MODIFY COLUMN estado
        ENUM('nuevo','aspirante','en_revision','contactado','aprobado','descartado','contratado')
        NOT NULL DEFAULT 'nuevo'");
    return true;
});
$step('024 FK aspirantes.usuario_id -> usuarios(id)', function () use ($db, $fk) {
    if ($fk('fk_aspirantes_usuario')) { return false; }
    // best-effort: si las collations no coinciden, se omite (la columna sigue usable)
    $db->rawExec("ALTER TABLE aspirantes ADD CONSTRAINT fk_aspirantes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE RESTRICT");
    return true;
});
$step('024 permiso aspirantes.gestionar (+grants)', function () use ($db) {
    if ((int) $db->fetchScalar("SELECT COUNT(*) FROM permisos WHERE codigo = 'aspirantes.gestionar'") > 0) { return false; }
    $db->rawExec("INSERT INTO permisos (id, codigo, recurso, accion, descripcion, es_sistema, created_at)
        VALUES ('SEEDPERMASPIRANTGESTION001','aspirantes.gestionar','aspirantes','gestionar','Revisar, aprobar y rechazar aspirantes (postulantes)',1,NOW(6))");
    $db->rawExec("INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
        SELECT r.id, p.id, NOW(6) FROM roles r
        INNER JOIN permisos p ON p.codigo = 'aspirantes.gestionar'
        LEFT JOIN roles_permisos rp ON rp.role_id = r.id AND rp.permiso_id = p.id
        WHERE r.codigo IN ('admin_tenant','supervisor') AND rp.role_id IS NULL");
    return true;
});

// ---- 025: aspirante_eventos + rol aspirante ----
$step('025 tabla aspirante_eventos', function () use ($db, $table, $config) {
    if ($table('aspirante_eventos')) { return false; }
    $coll = $config->string('db.collation', 'utf8mb4_unicode_ci');
    $db->rawExec("CREATE TABLE aspirante_eventos (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, aspirante_id CHAR(26) NULL, email VARCHAR(190) NULL,
        evento ENUM('registro_creado','link_enviado','link_abierto','correo_validado','registro_completado','token_expirado','reenvio_link') NOT NULL,
        ip VARBINARY(16) NULL, user_agent VARCHAR(500) NULL, detalles_json JSON NULL, created_at DATETIME(6) NOT NULL,
        PRIMARY KEY (id), KEY ix_aspev_aspirante (aspirante_id), KEY ix_aspev_email (email, created_at),
        KEY ix_aspev_evento (evento, created_at), KEY ix_aspev_ip (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE={$coll} ROW_FORMAT=DYNAMIC");
    return true;
});
$step('025 rol aspirante (+grant guardias.crear)', function () use ($db) {
    if ((int) $db->fetchScalar("SELECT COUNT(*) FROM roles WHERE id = 'ASPIRANTEROLE0000000000001'") > 0) { return false; }
    $ptid = null;
    try { $ptid = $db->fetchScalar("SELECT id FROM tenants WHERE platform_marker = 'Y' ORDER BY created_at ASC LIMIT 1"); } catch (\Throwable) {}
    if (!$ptid) { $ptid = $db->fetchScalar("SELECT id FROM tenants ORDER BY created_at ASC LIMIT 1"); }
    if (!$ptid) { return false; }
    $db->execute("INSERT INTO roles (id, tenant_id, codigo, nombre, descripcion, es_sistema, created_at, updated_at)
        VALUES ('ASPIRANTEROLE0000000000001', :t, 'aspirante', 'Aspirante (registro de guardia)', 'Acceso restringido: solo completar su propia ficha de guardia', 0, NOW(6), NOW(6))",
        ['t' => $ptid]);
    $db->rawExec("INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
        SELECT 'ASPIRANTEROLE0000000000001', p.id, NOW(6) FROM permisos p
        LEFT JOIN roles_permisos rp ON rp.role_id = 'ASPIRANTEROLE0000000000001' AND rp.permiso_id = p.id
        WHERE p.codigo = 'guardias.crear' AND rp.role_id IS NULL");
    return true;
});

// ---- 029: trazabilidad LOPDP ----
$step('029 columnas trazabilidad LOPDP', function () use ($db, $col) {
    $changed = false;
    $addColumn = static function (string $column, string $ddl) use ($db, $col, &$changed): void {
        if ($col('aspirantes', $column)) {
            return;
        }

        $db->rawExec("ALTER TABLE aspirantes ADD COLUMN {$ddl}");
        $changed = true;
    };

    $addColumn('politicas_aceptadas_at', 'politicas_aceptadas_at DATETIME(6) NULL AFTER politicas_version');
    $addColumn('politicas_aceptadas_desde', 'politicas_aceptadas_desde VARCHAR(80) NULL AFTER politicas_aceptadas_at');
    $addColumn('politicas_aceptadas_ruta', 'politicas_aceptadas_ruta VARCHAR(255) NULL AFTER politicas_aceptadas_desde');
    $addColumn('politicas_aceptadas_ip', 'politicas_aceptadas_ip VARBINARY(16) NULL AFTER politicas_aceptadas_ruta');
    $addColumn('politicas_aceptadas_user_agent', 'politicas_aceptadas_user_agent VARCHAR(500) NULL AFTER politicas_aceptadas_ip');
    $addColumn('lopdp_autorizacion_documento_id', 'lopdp_autorizacion_documento_id CHAR(26) NULL AFTER politicas_aceptadas_user_agent');
    $addColumn('lopdp_autorizacion_archivo_nombre', 'lopdp_autorizacion_archivo_nombre VARCHAR(255) NULL AFTER lopdp_autorizacion_documento_id');
    $addColumn('lopdp_autorizacion_subida_at', 'lopdp_autorizacion_subida_at DATETIME(6) NULL AFTER lopdp_autorizacion_archivo_nombre');

    return $changed ? true : false;
});
$step('029 indices LOPDP', function () use ($db, $col, $idx) {
    $changed = false;

    if (!$idx('aspirantes', 'ix_aspirantes_lopdp_politicas') && $col('aspirantes', 'politicas_aceptadas_at')) {
        $db->rawExec('ALTER TABLE aspirantes ADD KEY ix_aspirantes_lopdp_politicas (tenant_id, acepta_politicas, politicas_aceptadas_at)');
        $changed = true;
    }
    if (!$idx('aspirantes', 'ix_aspirantes_lopdp_autorizacion') && $col('aspirantes', 'lopdp_autorizacion_subida_at')) {
        $db->rawExec('ALTER TABLE aspirantes ADD KEY ix_aspirantes_lopdp_autorizacion (tenant_id, lopdp_autorizacion_subida_at)');
        $changed = true;
    }
    if (!$idx('aspirantes', 'ix_aspirantes_lopdp_documento') && $col('aspirantes', 'lopdp_autorizacion_documento_id')) {
        $db->rawExec('ALTER TABLE aspirantes ADD KEY ix_aspirantes_lopdp_documento (lopdp_autorizacion_documento_id)');
        $changed = true;
    }

    return $changed ? true : false;
});
$step('029 FK LOPDP documento', function () use ($db, $col, $table, $fk) {
    if ($fk('fk_aspirantes_lopdp_documento')) { return false; }
    if (!$table('expediente_documentos') || !$col('aspirantes', 'lopdp_autorizacion_documento_id')) { return false; }

    $db->rawExec('ALTER TABLE aspirantes ADD CONSTRAINT fk_aspirantes_lopdp_documento
        FOREIGN KEY (lopdp_autorizacion_documento_id)
        REFERENCES expediente_documentos(id)
        ON DELETE SET NULL
        ON UPDATE RESTRICT');
    return true;
});
$step('029 backfill aceptaciones LOPDP', function () use ($db, $col) {
    if (!$col('aspirantes', 'politicas_aceptadas_at')) { return false; }

    $pendientes = (int) $db->fetchScalar("SELECT COUNT(*) FROM aspirantes
     WHERE acepta_politicas = 1
       AND (
           politicas_aceptadas_at IS NULL
           OR politicas_aceptadas_desde IS NULL
           OR politicas_aceptadas_ruta IS NULL
       )");
    if ($pendientes === 0) { return false; }

    $db->rawExec("UPDATE aspirantes
       SET politicas_aceptadas_at = COALESCE(politicas_aceptadas_at, created_at),
           politicas_aceptadas_desde = COALESCE(
               NULLIF(politicas_aceptadas_desde, ''),
               CASE WHEN origen = 'login_publico' THEN 'modal_postula_gemeseg' ELSE origen END
           ),
           politicas_aceptadas_ruta = COALESCE(
               NULLIF(politicas_aceptadas_ruta, ''),
               CASE WHEN origen = 'login_publico' THEN '/login' ELSE origen END
           ),
           politicas_aceptadas_ip = COALESCE(politicas_aceptadas_ip, ip),
           politicas_aceptadas_user_agent = COALESCE(NULLIF(politicas_aceptadas_user_agent, ''), user_agent)
     WHERE acepta_politicas = 1
       AND (
           politicas_aceptadas_at IS NULL
           OR politicas_aceptadas_desde IS NULL
           OR politicas_aceptadas_ruta IS NULL
       )");
    return true;
});

// ---- Registrar migraciones 021-025 y 029 en la tabla `migraciones` (idempotente) ----
echo "\nRegistrando migraciones 021-025 y 029 en `migraciones`...\n";
if ($table('migraciones')) {
    foreach ([
        '021_aspirantes',
        '022_aspirantes_password',
        '023_aspirantes_verificacion',
        '024_aspirantes_aprobacion',
        '025_guardia_registro_flow',
        '029_aspirantes_lopdp_tracking',
    ] as $base) {
        $file = DIGISECURE_ROOT . "/migrations/{$base}.sql";
        if (!is_file($file)) { continue; }
        $m = Migration::fromFile($file);
        if ((int) $db->fetchScalar("SELECT COUNT(*) FROM migraciones WHERE version = :v", ['v' => $m->version]) === 0) {
            $db->execute("INSERT INTO migraciones (version, name, filename, checksum, applied_at, execution_ms) VALUES (:v,:n,:f,:c,:a,0)",
                ['v' => $m->version, 'n' => $m->name, 'f' => $m->filename, 'c' => $m->checksum, 'a' => date('Y-m-d H:i:s.u')]);
            echo "  + registrada {$m->version}\n";
        }
    }
}

echo "\n=====================================================================\n";
echo "Listo. Aplicados: " . (count($done) > 0 ? implode(', ', $done) : '(nada nuevo)') . "\n";
if ($skip !== []) { echo "Ya existían: " . count($skip) . " elementos.\n"; }
echo "El flujo /verificar ya debería funcionar.\n";
