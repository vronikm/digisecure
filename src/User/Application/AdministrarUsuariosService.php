<?php

declare(strict_types=1);

namespace DigiSecure\User\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Crypto\Hasher;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;
use DigiSecure\User\Infrastructure\UserRepository;

/**
 * Caso de uso: alta, edición y cambios de estado de usuarios del tenant.
 *
 * Reglas de negocio:
 *  - Email único por tenant.
 *  - Contraseña inicial generada por el admin (forzar cambio en próximo login).
 *  - Los cambios de estado se auditan con el actor.
 *  - El usuario que realiza la acción no puede bloquearse a sí mismo.
 */
final class AdministrarUsuariosService
{
    public function __construct(
        private readonly UserRepository $repo,
        private readonly PdoConnection $db,
        private readonly Hasher $hasher,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id:string}
     */
    public function crear(
        array $input,
        string $actorId,
        string $ip,
        bool $actorIsSuperAdmin = false,
        ?string $actorTenantId = null,
    ): array
    {
        $email = trim((string) ($input['email'] ?? ''));
        $nombres = trim((string) ($input['nombres'] ?? ''));
        $apellidos = trim((string) ($input['apellidos'] ?? ''));
        $passwordPlano = (string) ($input['password'] ?? '');
        $cargo = trim((string) ($input['cargo'] ?? '')) ?: null;
        $telefono = trim((string) ($input['telefono'] ?? '')) ?: null;
        $estado = (string) ($input['estado'] ?? 'activo');
        $rolId = trim((string) ($input['role_id'] ?? ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Email inválido.');
        }
        if ($nombres === '' || $apellidos === '') {
            throw new DomainException('Nombres y apellidos son obligatorios.');
        }
        if (strlen($passwordPlano) < 8) {
            throw new DomainException('La contraseña inicial debe tener al menos 8 caracteres.');
        }
        if (!in_array($estado, ['activo', 'inactivo', 'bloqueado', 'pendiente_verificacion'], true)) {
            throw new DomainException('Estado inválido.');
        }
        if ($rolId === '') {
            throw new DomainException('Debe asignar al menos un rol.');
        }
        $now = Clock::nowString();
        $passwordHash = $this->hasher->hash($passwordPlano);
        $id = '';
        $roleIdToAssign = '';
        $targetTenantId = null;

        if ($actorIsSuperAdmin) {
            $targetTenantId = $this->resolveTargetTenantIdForSuperAdmin($input, $actorTenantId);
            if ($this->emailExistsInTenant($email, $targetTenantId)) {
                throw new DomainException('Ya existe un usuario con ese email en la empresa seleccionada.');
            }
            $roleIdToAssign = $this->resolveRoleIdForSuperAdminTarget(
                $rolId,
                $targetTenantId,
                $this->isSystemScope($input),
            );
            $id = $this->createUserInTenant($targetTenantId, [
                'email' => $email,
                'password_hash' => $passwordHash,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'cargo' => $cargo,
                'telefono' => $telefono,
                'estado' => $estado,
                'mfa_habilitado' => 0,
                'intentos_fallidos' => 0,
                'password_changed_at' => $now,
                'debe_cambiar_password' => 1,
                'created_by' => $actorId,
            ]);
        } else {
            if ($this->repo->emailExistsInTenant($email)) {
                throw new DomainException('Ya existe un usuario con ese email en esta empresa.');
            }
            $this->ensureRoleAccessible($rolId);
            $roleIdToAssign = $rolId;
            $id = $this->repo->create([
                'email' => $email,
                'password_hash' => $passwordHash,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'cargo' => $cargo,
                'telefono' => $telefono,
                'estado' => $estado,
                'mfa_habilitado' => 0,
                'intentos_fallidos' => 0,
                'password_changed_at' => $now,
                'debe_cambiar_password' => 1,
                'created_by' => $actorId,
            ]);
        }

        $this->repo->assignRole($id, $roleIdToAssign, $actorId);

        $this->audit->log(
            'usuario.creado',
            $actorId,
            'usuario',
            $id,
            detalles: [
                'email' => $email,
                'role_id' => $roleIdToAssign,
                'estado' => $estado,
                'tenant_id' => $targetTenantId,
                'scope' => $actorIsSuperAdmin ? ($this->isSystemScope($input) ? 'sistema' : 'empresa') : 'empresa',
            ],
            nivel: 'info',
            ip: $ip,
        );

        return ['id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function actualizar(
        string $id,
        array $input,
        string $actorId,
        string $ip,
        bool $actorIsSuperAdmin = false,
        ?string $actorTenantId = null,
    ): void
    {
        $usuario = $actorIsSuperAdmin ? $this->findUserGlobalById($id) : $this->repo->findUserById($id);
        if ($usuario === null) {
            throw new DomainException('Usuario no encontrado.');
        }

        $email = trim((string) ($input['email'] ?? ''));
        $nombres = trim((string) ($input['nombres'] ?? ''));
        $apellidos = trim((string) ($input['apellidos'] ?? ''));
        $cargo = trim((string) ($input['cargo'] ?? '')) ?: null;
        $telefono = trim((string) ($input['telefono'] ?? '')) ?: null;

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Email inválido.');
        }
        if ($nombres === '' || $apellidos === '') {
            throw new DomainException('Nombres y apellidos son obligatorios.');
        }
        $rolId = trim((string) ($input['role_id'] ?? ''));

        if ($actorIsSuperAdmin) {
            $targetTenantId = $this->resolveTargetTenantIdForSuperAdmin($input, $actorTenantId);
            if ($this->emailExistsInTenant($email, $targetTenantId, $id)) {
                throw new DomainException('Otro usuario ya usa ese email en la empresa seleccionada.');
            }

            $currentTenantId = (string) ($usuario['tenant_id'] ?? '');
            $tenantChanged = $currentTenantId !== $targetTenantId;
            $scopeSistema = $this->isSystemScope($input);

            if ($rolId === '' && $tenantChanged) {
                $rolId = $this->resolveCurrentRoleIdAsTemplateForRemap($id);
                if ($rolId === '') {
                    throw new DomainException('Seleccione un rol para completar el cambio de empresa.');
                }
            }

            $this->updateUserGlobal($id, [
                'tenant_id' => $targetTenantId,
                'email' => $email,
                'email_normalizado' => mb_strtolower(trim($email), 'UTF-8'),
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'cargo' => $cargo,
                'telefono' => $telefono,
                'updated_at' => Clock::nowString(),
            ]);

            if ($rolId !== '') {
                $roleIdToAssign = $this->resolveRoleIdForSuperAdminTarget($rolId, $targetTenantId, $scopeSistema);
                $this->db->execute(
                    'DELETE FROM usuarios_roles WHERE usuario_id = :uid',
                    ['uid' => $id],
                );
                $this->repo->assignRole($id, $roleIdToAssign, $actorId);
            }
        } else {
            if ($this->repo->emailExistsInTenant($email, $id)) {
                throw new DomainException('Otro usuario ya usa ese email.');
            }

            $this->repo->update($id, [
                'email' => $email,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'cargo' => $cargo,
                'telefono' => $telefono,
            ]);

            // Cambio de rol opcional (reemplaza todos los roles existentes por el nuevo)
            if ($rolId !== '') {
                $this->ensureRoleAccessible($rolId);
                $this->db->execute(
                    'DELETE FROM usuarios_roles WHERE usuario_id = :uid',
                    ['uid' => $id],
                );
                $this->repo->assignRole($id, $rolId, $actorId);
            }
        }

        $this->audit->log(
            'usuario.actualizado',
            $actorId,
            'usuario',
            $id,
            detalles: [
                'email' => $email,
                'role_id' => $rolId ?: null,
                'scope' => $actorIsSuperAdmin ? ($this->isSystemScope($input) ? 'sistema' : 'empresa') : 'empresa',
            ],
            nivel: 'info',
            ip: $ip,
        );
    }

    public function cambiarEstado(string $id, string $nuevoEstado, string $actorId, string $ip): void
    {
        if (!in_array($nuevoEstado, ['activo', 'inactivo', 'bloqueado'], true)) {
            throw new DomainException('Estado inválido.');
        }
        if ($id === $actorId && $nuevoEstado !== 'activo') {
            throw new DomainException('No puedes desactivar o bloquear tu propia cuenta.');
        }
        $usuario = $this->repo->findUserById($id);
        if ($usuario === null) {
            throw new DomainException('Usuario no encontrado.');
        }

        $update = ['estado' => $nuevoEstado];
        // Si se reactiva, limpiar bloqueos
        if ($nuevoEstado === 'activo') {
            $update['intentos_fallidos'] = 0;
            $update['bloqueado_hasta'] = null;
        }
        $this->repo->update($id, $update);

        $this->audit->log(
            'usuario.estado_cambiado',
            $actorId,
            'usuario',
            $id,
            detalles: ['estado_anterior' => $usuario['estado'], 'estado_nuevo' => $nuevoEstado],
            nivel: $nuevoEstado === 'activo' ? 'info' : 'warning',
            ip: $ip,
        );
    }

    public function resetearPassword(
        string $id,
        string $passwordPlano,
        string $actorId,
        string $ip,
        bool $actorIsSuperAdmin = false,
    ): void
    {
        if (strlen($passwordPlano) < 8) {
            throw new DomainException('La contraseña debe tener al menos 8 caracteres.');
        }
        $usuario = $actorIsSuperAdmin
            ? $this->findUserGlobalById($id)
            : $this->repo->findUserById($id);
        if ($usuario === null) {
            throw new DomainException('Usuario no encontrado.');
        }

        $updateData = [
            'password_hash' => $this->hasher->hash($passwordPlano),
            'password_changed_at' => Clock::nowString(),
            'debe_cambiar_password' => 1,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ];
        if ($actorIsSuperAdmin) {
            $updateData['updated_at'] = Clock::nowString();
            $this->updateUserGlobal($id, $updateData);
        } else {
            $this->repo->update($id, $updateData);
        }

        $this->audit->log(
            'usuario.password_reseteado',
            $actorId,
            'usuario',
            $id,
            nivel: 'warning',
            ip: $ip,
        );
    }

    /**
     * Verifica que el rol existe y es accesible (rol de sistema o del tenant actual).
     */
    private function ensureRoleAccessible(string $roleId): void
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $row = $this->db->fetchOne(
            'SELECT r.id
               FROM roles r
              WHERE r.id = :id
                AND r.codigo <> :codigo_superadmin
                AND (
                    r.tenant_id = :tid
                    OR (
                        r.tenant_id IS NULL
                        AND NOT EXISTS (
                            SELECT 1
                              FROM roles rt
                             WHERE rt.tenant_id = :tid_shadow
                               AND rt.codigo = r.codigo
                        )
                    )
                )',
            [
                'id' => $roleId,
                'tid' => $tenantId,
                'tid_shadow' => $tenantId,
                'codigo_superadmin' => 'superadmin',
            ],
        );
        if ($row === null) {
            throw new DomainException('Rol inválido o no accesible.');
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    private function isSystemScope(array $input): bool
    {
        return strtolower(trim((string) ($input['user_scope'] ?? 'empresa'))) === 'sistema';
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resolveTargetTenantIdForSuperAdmin(array $input, ?string $actorTenantId): string
    {
        if ($this->isSystemScope($input)) {
            $platformTenantId = $this->resolvePlatformTenantId();
            if ($platformTenantId === '') {
                throw new DomainException('No se encontró el tenant de plataforma. Ejecuta bootstrap de superadmin o configura el tenant de sistema.');
            }
            return $platformTenantId;
        }

        $tenantId = trim((string) ($input['tenant_id'] ?? ''));
        if ($tenantId === '') {
            throw new DomainException('Debe seleccionar una empresa (tenant) destino.');
        }
        if (!$this->tenantExists($tenantId)) {
            throw new DomainException('La empresa seleccionada no existe o no está disponible.');
        }

        return $tenantId;
    }

    private function resolveRoleIdForSuperAdminTarget(string $selectedRoleId, string $targetTenantId, bool $scopeSistema): string
    {
        $selected = $this->db->fetchOne(
            'SELECT id, codigo FROM roles WHERE id = :id',
            ['id' => $selectedRoleId],
        );
        if ($selected === null) {
            throw new DomainException('Rol inválido.');
        }

        $codigo = (string) $selected['codigo'];
        if (!$scopeSistema && $codigo === 'superadmin') {
            throw new DomainException('El rol Super Administrador solo puede asignarse a usuarios del Sistema.');
        }

        if ($scopeSistema) {
            $systemRole = $this->db->fetchOne(
                'SELECT id FROM roles WHERE tenant_id IS NULL AND codigo = :codigo LIMIT 1',
                ['codigo' => $codigo],
            );
            if ($systemRole === null) {
                throw new DomainException('No existe un rol de sistema equivalente al seleccionado.');
            }
            return (string) $systemRole['id'];
        }

        $tenantRole = $this->db->fetchOne(
            'SELECT id FROM roles WHERE tenant_id = :tid AND codigo = :codigo LIMIT 1',
            ['tid' => $targetTenantId, 'codigo' => $codigo],
        );
        if ($tenantRole !== null) {
            return (string) $tenantRole['id'];
        }

        $systemRole = $this->db->fetchOne(
            'SELECT id FROM roles WHERE tenant_id IS NULL AND codigo = :codigo LIMIT 1',
            ['codigo' => $codigo],
        );
        if ($systemRole !== null) {
            return (string) $systemRole['id'];
        }

        throw new DomainException('El rol seleccionado no está disponible para la empresa destino.');
    }

    private function tenantExists(string $tenantId): bool
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM tenants WHERE id = :id',
            ['id' => $tenantId],
        ) > 0;
    }

    private function emailExistsInTenant(string $email, string $tenantId, ?string $excludeUserId = null): bool
    {
        $emailNorm = mb_strtolower(trim($email), 'UTF-8');
        $sql = 'SELECT COUNT(*) FROM usuarios WHERE tenant_id = :tid AND email_normalizado = :email';
        $bindings = ['tid' => $tenantId, 'email' => $emailNorm];

        if ($excludeUserId !== null && $excludeUserId !== '') {
            $sql .= ' AND id <> :exclude';
            $bindings['exclude'] = $excludeUserId;
        }

        return (int) $this->db->fetchScalar($sql, $bindings) > 0;
    }

    /**
     * @param array<string, scalar|null> $data
     */
    private function createUserInTenant(string $tenantId, array $data): string
    {
        $id = IdGenerator::ulid();
        $now = Clock::nowString();
        $email = trim((string) ($data['email'] ?? ''));
        $payload = [
            'id' => $id,
            'tenant_id' => $tenantId,
            'email' => $email,
            'email_normalizado' => mb_strtolower($email, 'UTF-8'),
            'password_hash' => (string) ($data['password_hash'] ?? ''),
            'nombres' => (string) ($data['nombres'] ?? ''),
            'apellidos' => (string) ($data['apellidos'] ?? ''),
            'cargo' => $data['cargo'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'estado' => (string) ($data['estado'] ?? 'activo'),
            'mfa_habilitado' => (int) ($data['mfa_habilitado'] ?? 0),
            'intentos_fallidos' => (int) ($data['intentos_fallidos'] ?? 0),
            'password_changed_at' => (string) ($data['password_changed_at'] ?? $now),
            'debe_cambiar_password' => (int) ($data['debe_cambiar_password'] ?? 1),
            'created_by' => $data['created_by'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->db->execute(
            'INSERT INTO usuarios
                (id, tenant_id, email, email_normalizado, password_hash, nombres, apellidos, cargo, telefono, estado,
                 mfa_habilitado, intentos_fallidos, password_changed_at, debe_cambiar_password, created_by, created_at, updated_at)
             VALUES
                (:id, :tenant_id, :email, :email_normalizado, :password_hash, :nombres, :apellidos, :cargo, :telefono, :estado,
                 :mfa_habilitado, :intentos_fallidos, :password_changed_at, :debe_cambiar_password, :created_by, :created_at, :updated_at)',
            $payload,
        );

        return $id;
    }

    /**
     * @param array<string, scalar|null> $data
     */
    private function updateUserGlobal(string $userId, array $data): void
    {
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "{$col} = :{$col}";
        }
        $data['id'] = $userId;

        $this->db->execute(
            'UPDATE usuarios SET ' . implode(', ', $set) . ' WHERE id = :id',
            $data,
        );
    }

    /**
     * Devuelve el rol actual (primero asignado) como plantilla para remap por código.
     */
    private function resolveCurrentRoleIdAsTemplateForRemap(string $userId): string
    {
        $row = $this->db->fetchOne(
            'SELECT r.id
               FROM usuarios_roles ur
               INNER JOIN roles r ON r.id = ur.role_id
              WHERE ur.usuario_id = :uid
              ORDER BY ur.assigned_at DESC
              LIMIT 1',
            ['uid' => $userId],
        );

        return $row !== null ? (string) $row['id'] : '';
    }

    private function resolvePlatformTenantId(): string
    {
        $row = $this->db->fetchOne(
            "SELECT id
               FROM tenants
              WHERE platform_marker = 'Y'
              LIMIT 1",
        );

        return $row !== null ? (string) $row['id'] : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUserGlobalById(string $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM usuarios WHERE id = :id',
            ['id' => $id],
        );
    }
}
