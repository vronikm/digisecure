<?php

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

/**
 * Script de bootstrap para crear/actualizar el usuario superadmin global.
 *
 * Uso:
 *   php bin/create-superadmin.php
 *   php bin/create-superadmin.php --email=superadmin@digisecure.ec --password="Temporal123!" --tenant-id=01H...
 */

if (!is_file(DIGISECURE_ROOT . '/.env')) {
    fwrite(STDERR, "ERROR: .env no encontrado.\n");
    exit(1);
}

$options = parseOptions($argv);
$email = (string)($options['email'] ?? 'superadmin@digisecure.ec');
$password = (string)($options['password'] ?? generateTemporaryPassword());
$tenantIdOpt = isset($options['tenant-id']) ? (string)$options['tenant-id'] : null;
$nombres = (string)($options['nombres'] ?? 'Super');
$apellidos = (string)($options['apellidos'] ?? 'Admin');
$cargo = (string)($options['cargo'] ?? 'Super Administrador del Sistema');

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "ERROR: email inválido.\n");
    exit(1);
}

if (mb_strlen($password, 'UTF-8') < 8) {
    fwrite(STDERR, "ERROR: la contraseña debe tener al menos 8 caracteres.\n");
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
$emailNorm = mb_strtolower(trim($email), 'UTF-8');
$now = Clock::nowString();
$passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,
    'time_cost' => 4,
    'threads' => 2,
]);

if ($passwordHash === false) {
    fwrite(STDERR, "ERROR: no se pudo generar el hash de contraseña.\n");
    exit(1);
}

try {
    $result = $db->transactional(
        function (PdoConnection $db) use (
            $email,
            $emailNorm,
            $passwordHash,
            $nombres,
            $apellidos,
            $cargo,
            $tenantIdOpt,
            $now,
        ): array {
            $role = $db->fetchOne(
                "SELECT id FROM roles WHERE tenant_id IS NULL AND codigo = 'superadmin' LIMIT 1"
            );
            if ($role === null) {
                throw new RuntimeException(
                    "No existe el rol global 'superadmin'. Ejecuta migraciones primero."
                );
            }
            $superadminRoleId = (string)$role['id'];

            // Garantizar que el rol superadmin tenga todos los permisos del sistema existentes.
            $db->execute(
                "INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
                 SELECT :role_id, p.id, :now
                   FROM permisos p
                   LEFT JOIN roles_permisos rp
                     ON rp.role_id = :role_id2
                    AND rp.permiso_id = p.id
                  WHERE p.es_sistema = 1
                    AND rp.permiso_id IS NULL",
                [
                    'role_id' => $superadminRoleId,
                    'role_id2' => $superadminRoleId,
                    'now' => $now,
                ],
            );

            $tenant = resolveTenantForSuperadmin($db, $tenantIdOpt, $now);
            $tenantId = (string)$tenant['id'];
            $tenantNombre = (string)$tenant['razon_social'];

            $existingUser = $db->fetchOne(
                'SELECT id, tenant_id
                   FROM usuarios
                  WHERE email_normalizado = :email
                  ORDER BY created_at ASC
                  LIMIT 1',
                ['email' => $emailNorm],
            );

            $created = false;
            if ($existingUser === null) {
                $userId = IdGenerator::ulid();
                $db->execute(
                    'INSERT INTO usuarios
                        (id, tenant_id, email, email_normalizado, password_hash, nombres, apellidos, cargo, estado,
                         intentos_fallidos, debe_cambiar_password, password_changed_at, created_at, updated_at)
                     VALUES
                        (:id, :tenant_id, :email, :email_norm, :password_hash, :nombres, :apellidos, :cargo, :estado,
                         0, 1, :password_changed_at, :created_at, :updated_at)',
                    [
                        'id' => $userId,
                        'tenant_id' => $tenantId,
                        'email' => $email,
                        'email_norm' => $emailNorm,
                        'password_hash' => $passwordHash,
                        'nombres' => $nombres,
                        'apellidos' => $apellidos,
                        'cargo' => $cargo,
                        'estado' => 'activo',
                        'password_changed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
                $created = true;
            } else {
                $userId = (string)$existingUser['id'];

                $db->execute(
                    'UPDATE usuarios
                        SET tenant_id = :tenant_id,
                            password_hash = :password_hash,
                            nombres = :nombres,
                            apellidos = :apellidos,
                            cargo = :cargo,
                            estado = :estado,
                            intentos_fallidos = 0,
                            bloqueado_hasta = NULL,
                            debe_cambiar_password = 1,
                            password_changed_at = :password_changed_at,
                            updated_at = :updated_at
                      WHERE id = :id',
                    [
                        'id' => $userId,
                        'tenant_id' => $tenantId,
                        'password_hash' => $passwordHash,
                        'nombres' => $nombres,
                        'apellidos' => $apellidos,
                        'cargo' => $cargo,
                        'estado' => 'activo',
                        'password_changed_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }

            $db->execute(
                'INSERT INTO usuarios_roles (usuario_id, role_id, assigned_at, assigned_by)
                 VALUES (:usuario_id, :role_id, :assigned_at, NULL)
                 ON DUPLICATE KEY UPDATE assigned_at = VALUES(assigned_at)',
                [
                    'usuario_id' => $userId,
                    'role_id' => $superadminRoleId,
                    'assigned_at' => $now,
                ],
            );

            return [
                'created' => $created,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'tenant_nombre' => $tenantNombre,
                'role_id' => $superadminRoleId,
            ];
        }
    );
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: {$e->getMessage()}\n");
    exit(1);
}

echo "DigiSecure - Superadmin bootstrap\n";
echo str_repeat('=', 50) . "\n";
echo 'Acción:      ' . (($result['created'] ?? false) ? "usuario creado\n" : "usuario actualizado\n");
echo 'Usuario ID:  ' . (string)$result['user_id'] . "\n";
echo 'Tenant ID:   ' . (string)$result['tenant_id'] . "\n";
echo 'Tenant:      ' . (string)$result['tenant_nombre'] . "\n";
echo 'Rol ID:      ' . (string)$result['role_id'] . " (superadmin)\n";
echo "Email:       {$email}\n";
echo "Password:    {$password}\n";
echo "Nota:        El usuario queda con cambio forzado de contraseña en el próximo login.\n";
echo str_repeat('=', 50) . "\n";

/**
 * @param array<int, string> $argv
 * @return array<string, string>
 */
function parseOptions(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $pair = explode('=', substr($arg, 2), 2);
        if (count($pair) !== 2 || trim($pair[0]) === '') {
            continue;
        }
        $options[trim($pair[0])] = trim($pair[1]);
    }

    return $options;
}

/**
 * @return array{id:string, razon_social:string}
 */
function resolveTenantForSuperadmin(PdoConnection $db, ?string $tenantIdOpt, string $now): array
{
    if ($tenantIdOpt !== null && $tenantIdOpt !== '') {
        $tenant = $db->fetchOne(
            'SELECT id, razon_social, estado FROM tenants WHERE id = :id LIMIT 1',
            ['id' => $tenantIdOpt],
        );
        if ($tenant === null) {
            throw new RuntimeException("El tenant_id '{$tenantIdOpt}' no existe.");
        }
        if (($tenant['estado'] ?? '') !== 'activo') {
            throw new RuntimeException("El tenant '{$tenantIdOpt}' no está activo.");
        }

        markPlatformTenant($db, (string) $tenant['id']);

        return [
            'id' => (string)$tenant['id'],
            'razon_social' => (string)$tenant['razon_social'],
        ];
    }

    $tenant = $db->fetchOne(
        "SELECT id, razon_social
           FROM tenants
          WHERE platform_marker = 'Y'
          LIMIT 1"
    );
    if ($tenant !== null) {
        markPlatformTenant($db, (string) $tenant['id']);
        return [
            'id' => (string)$tenant['id'],
            'razon_social' => (string)$tenant['razon_social'],
        ];
    }

    $tenant = $db->fetchOne(
        "SELECT id, razon_social
           FROM tenants
          WHERE estado = 'activo'
          ORDER BY created_at ASC
          LIMIT 1"
    );
    if ($tenant !== null) {
        markPlatformTenant($db, (string) $tenant['id']);
        return [
            'id' => (string)$tenant['id'],
            'razon_social' => (string)$tenant['razon_social'],
        ];
    }

    // Si no existe tenant activo, crear uno de plataforma para bootstrap.
    $tenantId = IdGenerator::ulid();
    $ruc = generateUniqueRuc($db);
    $razon = 'DigiSecure Plataforma S.A.';
    $email = 'plataforma@digisecure.ec';

    $db->execute(
        "INSERT INTO tenants
            (id, razon_social, ruc, nombre_comercial, email_contacto, telefono, ciudad, provincia, pais, estado,
             plan_codigo, plan_inicio, max_usuarios, max_guardias, max_storage_mb, timezone, locale,
             platform_marker, created_at, updated_at)
         VALUES
            (:id, :razon, :ruc, :comercial, :email, :telefono, :ciudad, :provincia, 'EC', 'activo',
             'enterprise', CURDATE(), 9999, 999999, 102400, 'America/Guayaquil', 'es_EC',
             'Y', :created_at, :updated_at)",
        [
            'id' => $tenantId,
            'razon' => $razon,
            'ruc' => $ruc,
            'comercial' => 'DigiSecure Plataforma',
            'email' => $email,
            'telefono' => '0990000000',
            'ciudad' => 'Quito',
            'provincia' => 'Pichincha',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    );

    return [
        'id' => $tenantId,
        'razon_social' => $razon,
    ];
}

function markPlatformTenant(PdoConnection $db, string $tenantId): void
{
    $db->execute('UPDATE tenants SET platform_marker = NULL');
    $db->execute(
        "UPDATE tenants
            SET platform_marker = 'Y'
          WHERE id = :id",
        ['id' => $tenantId],
    );
}

function generateUniqueRuc(PdoConnection $db): string
{
    for ($i = 0; $i < 20; $i++) {
        $candidate = '9' . str_pad((string)random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        $exists = $db->fetchScalar(
            'SELECT COUNT(*) FROM tenants WHERE ruc = :ruc',
            ['ruc' => $candidate],
        );
        if ((int)$exists === 0) {
            return $candidate;
        }
    }

    throw new RuntimeException('No fue posible generar un RUC único para bootstrap.');
}

function generateTemporaryPassword(): string
{
    return 'Tmp#' . substr(bin2hex(random_bytes(8)), 0, 16) . '!';
}
