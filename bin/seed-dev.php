<?php

/**
 * =====================================================================
 * DigiSecure - Seed de desarrollo (Fase 0.7)
 * =====================================================================
 *
 * Crea un tenant de prueba y un usuario admin para probar el login.
 *
 * Uso:
 *     php bin/seed-dev.php
 *
 * SOLO para desarrollo. NUNCA ejecutar en producción.
 * =====================================================================
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: solo desde CLI.\n");
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
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;

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
        'collation' => $envVars['DB_COLLATION'] ?? 'utf8mb4_0900_ai_ci',
    ],
]);

date_default_timezone_set($envVars['APP_TIMEZONE'] ?? 'America/Guayaquil');

$db = PdoConnection::fromConfig($config);
$now = Clock::nowString();

echo "DigiSecure - Seed de desarrollo\n";
echo str_repeat('=', 50) . "\n";

// --- Verificar si ya existe el tenant de prueba ---
$existing = $db->fetchOne(
    "SELECT id FROM tenants WHERE ruc = :ruc",
    ['ruc' => '0990000000001'],
);

if ($existing !== null) {
    echo "El tenant de prueba ya existe (ID: {$existing['id']}). Saltando seed.\n";
    echo "Para re-ejecutar, elimina manualmente el tenant primero.\n";
    exit(0);
}

$tenantId = IdGenerator::ulid();
$adminId = IdGenerator::ulid();
$email = 'admin@digisecure.test';
$password = 'DigiSecure2024!';
$passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,
    'time_cost' => 4,
    'threads' => 2,
]);

$db->transactional(function (PdoConnection $db) use ($tenantId, $adminId, $email, $passwordHash, $now): void {
    // 1. Crear tenant de prueba
    $db->execute(
        "INSERT INTO tenants (id, razon_social, ruc, nombre_comercial, email_contacto, telefono, ciudad, provincia, estado, plan_codigo, plan_inicio, max_usuarios, max_guardias, max_storage_mb, created_at, updated_at)
         VALUES (:id, :razon_social, :ruc, :nombre_comercial, :email_contacto, :telefono, :ciudad, :provincia, 'activo', 'professional', CURDATE(), 50, 500, 5120, :created_at, :updated_at)",
        [
            'id' => $tenantId,
            'razon_social' => 'SecuriTest Ecuador S.A.',
            'ruc' => '0990000000001',
            'nombre_comercial' => 'SecuriTest',
            'email_contacto' => 'admin@digisecure.test',
            'telefono' => '0991234567',
            'ciudad' => 'Guayaquil',
            'provincia' => 'Guayas',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    );
    echo "  [OK] Tenant creado: SecuriTest Ecuador S.A. (ID: {$tenantId})\n";

    // 2. Crear usuario admin
    $emailNorm = mb_strtolower(trim($email), 'UTF-8');
    $db->execute(
        "INSERT INTO usuarios (id, tenant_id, email, email_normalizado, password_hash, nombres, apellidos, cargo, estado, created_at, updated_at)
         VALUES (:id, :tenant_id, :email, :email_norm, :password_hash, :nombres, :apellidos, :cargo, 'activo', :created_at, :updated_at)",
        [
            'id' => $adminId,
            'tenant_id' => $tenantId,
            'email' => $email,
            'email_norm' => $emailNorm,
            'password_hash' => $passwordHash,
            'nombres' => 'Admin',
            'apellidos' => 'DigiSecure',
            'cargo' => 'Administrador del Sistema',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    );
    echo "  [OK] Usuario creado: {$email} (ID: {$adminId})\n";

    // 3. Clonar roles del sistema para este tenant
    $systemRoles = $db->fetchAll(
        "SELECT id, codigo, nombre, descripcion
           FROM roles
          WHERE tenant_id IS NULL
            AND es_sistema = 1
            AND codigo <> 'superadmin'"
    );

    $roleMapping = []; // sistema_role_id => nuevo_tenant_role_id
    foreach ($systemRoles as $sysRole) {
        $newRoleId = \DigiSecure\Shared\Infrastructure\IdGenerator::ulid();
        $roleMapping[$sysRole['id']] = $newRoleId;

        $db->execute(
            "INSERT INTO roles (id, tenant_id, codigo, nombre, descripcion, es_sistema, created_at, updated_at)
             VALUES (:id, :tenant_id, :codigo, :nombre, :descripcion, 0, :created_at, :updated_at)",
            [
                'id' => $newRoleId,
                'tenant_id' => $tenantId,
                'codigo' => $sysRole['codigo'],
                'nombre' => $sysRole['nombre'],
                'descripcion' => $sysRole['descripcion'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }
    echo "  [OK] " . count($systemRoles) . " roles clonados al tenant\n";

    // 4. Clonar permisos de cada rol del sistema al nuevo rol del tenant
    foreach ($roleMapping as $sysRoleId => $newRoleId) {
        $db->execute(
            "INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
             SELECT :new_role_id, permiso_id, :now
             FROM roles_permisos
             WHERE role_id = :sys_role_id",
            [
                'new_role_id' => $newRoleId,
                'sys_role_id' => $sysRoleId,
                'now' => $now,
            ],
        );
    }
    echo "  [OK] Permisos copiados a los roles del tenant\n";

    // 5. Asignar rol admin_tenant al usuario
    $adminRoleId = null;
    foreach ($systemRoles as $sysRole) {
        if ($sysRole['codigo'] === 'admin_tenant') {
            $adminRoleId = $roleMapping[$sysRole['id']];
            break;
        }
    }

    if ($adminRoleId !== null) {
        $db->execute(
            "INSERT INTO usuarios_roles (usuario_id, role_id, assigned_at)
             VALUES (:usuario_id, :role_id, :now)",
            [
                'usuario_id' => $adminId,
                'role_id' => $adminRoleId,
                'now' => $now,
            ],
        );
        echo "  [OK] Rol 'admin_tenant' asignado al usuario admin\n";
    }
});

echo str_repeat('=', 50) . "\n";
echo "Seed completado.\n\n";
echo "Credenciales de acceso:\n";
echo "  Email:    {$email}\n";
echo "  Password: {$password}\n";
echo "\nPuedes iniciar sesion en: " . ($envVars['APP_URL'] ?? 'http://localhost/digisecure') . "/login\n";
