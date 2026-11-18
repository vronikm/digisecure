<?php

declare(strict_types=1);

namespace DigiSecure\Security\Rbac;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Motor de autorización RBAC.
 *
 * Modelo: Usuario → Rol(es) → Permiso(s).
 * Los permisos se identifican por string: "recurso.accion" (ej: "guardias.crear").
 *
 * Los permisos del usuario se cargan una vez al autenticarse y se cachean
 * en sesión para evitar queries repetidos.
 */
final class Authorizer
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Verifica que el usuario tenga el permiso y lanza excepción si no lo tiene.
     *
     * @param list<string> $permisosUsuario  Permisos cargados del usuario en sesión
     */
    public function ensure(array $permisosUsuario, string $permisoRequerido, ?string $usuarioId = null): void
    {
        if (!$this->has($permisosUsuario, $permisoRequerido)) {
            throw new AuthorizationException($permisoRequerido, $usuarioId);
        }
    }

    /**
     * Verifica si el usuario tiene un permiso específico.
     *
     * @param list<string> $permisosUsuario
     */
    public function has(array $permisosUsuario, string $permiso): bool
    {
        return in_array($permiso, $permisosUsuario, true);
    }

    /**
     * Verifica si el usuario tiene AL MENOS UNO de los permisos dados.
     *
     * @param list<string> $permisosUsuario
     * @param list<string> $permisos
     */
    public function hasAny(array $permisosUsuario, array $permisos): bool
    {
        foreach ($permisos as $permiso) {
            if (in_array($permiso, $permisosUsuario, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica si el usuario tiene TODOS los permisos dados.
     *
     * @param list<string> $permisosUsuario
     * @param list<string> $permisos
     */
    public function hasAll(array $permisosUsuario, array $permisos): bool
    {
        foreach ($permisos as $permiso) {
            if (!in_array($permiso, $permisosUsuario, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Carga todos los permisos del usuario desde la BD.
     *
     * Se ejecuta una sola vez tras el login. El resultado se almacena en sesión.
     * Resuelve: usuario → usuarios_roles → roles → roles_permisos → permisos.
     *
     * Incluye tanto roles del tenant del usuario como roles de sistema (tenant_id IS NULL).
     *
     * El superadministrador del sistema (rol global 'superadmin') recibe TODOS
     * los permisos del catalogo: acceso total a vistas y funciones, incluidos
     * los permisos que se agreguen en el futuro, sin depender de roles_permisos.
     *
     * @param string|null $tenantId Tenant del usuario; por defecto, el del contexto actual.
     * @return list<string> Lista de códigos de permiso ("recurso.accion")
     */
    public function loadPermisosUsuario(string $usuarioId, ?string $tenantId = null): array
    {
        if ($this->esSuperadminDeSistema($usuarioId)) {
            return array_column(
                $this->db->fetchAll('SELECT codigo FROM permisos ORDER BY codigo'),
                'codigo',
            );
        }

        $rows = $this->db->fetchAll(
            'SELECT DISTINCT p.codigo
             FROM usuarios_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             INNER JOIN roles_permisos rp ON rp.role_id = r.id
             INNER JOIN permisos p ON p.id = rp.permiso_id
             WHERE ur.usuario_id = :usuario_id
               AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)
             ORDER BY p.codigo',
            [
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId ?? $this->tenantContext->currentTenantId(),
            ],
        );

        return array_column($rows, 'codigo');
    }

    /**
     * Carga los roles asignados al usuario.
     *
     * @param string|null $tenantId Tenant del usuario; por defecto, el del contexto actual.
     * @return list<array{codigo: string, nombre: string}>
     */
    public function loadRolesUsuario(string $usuarioId, ?string $tenantId = null): array
    {
        return $this->db->fetchAll(
            'SELECT r.codigo, r.nombre
             FROM usuarios_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.usuario_id = :usuario_id
               AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)',
            [
                'usuario_id' => $usuarioId,
                'tenant_id' => $tenantId ?? $this->tenantContext->currentTenantId(),
            ],
        );
    }

    /**
     * ¿El usuario tiene el rol global 'superadmin' (roles.tenant_id IS NULL)?
     * Un rol 'superadmin' creado dentro de una empresa NO cuenta.
     */
    public function esSuperadminDeSistema(string $usuarioId): bool
    {
        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*)
               FROM usuarios_roles ur
               INNER JOIN roles r ON r.id = ur.role_id
              WHERE ur.usuario_id = :usuario_id
                AND r.codigo = 'superadmin'
                AND r.tenant_id IS NULL",
            ['usuario_id' => $usuarioId],
        ) > 0;
    }
}
