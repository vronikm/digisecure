<?php

declare(strict_types=1);

namespace DigiSecure\Security\Rbac;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio de lectura de roles y permisos.
 *
 * Soporta la coexistencia de roles del sistema (tenant_id IS NULL) y roles
 * propios del tenant. Solo lectura — la gestión avanzada de roles vendrá
 * en una fase posterior.
 */
final class RoleRepository
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Lista roles accesibles para el tenant actual (sistema + propios).
     *
     * @return list<array<string, mixed>>
     */
    public function listAccesibles(): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        return $this->db->fetchAll(
            'SELECT r.id, r.tenant_id, r.codigo, r.nombre, r.descripcion, r.es_sistema, r.created_at,
                    (SELECT COUNT(*) FROM usuarios_roles ur
                     INNER JOIN usuarios u ON u.id = ur.usuario_id
                     WHERE ur.role_id = r.id AND u.tenant_id = :tid_count) AS usuarios_count,
                    (SELECT COUNT(*) FROM roles_permisos rp WHERE rp.role_id = r.id) AS permisos_count
             FROM roles r
             WHERE r.tenant_id = :tid OR r.tenant_id IS NULL
             ORDER BY r.es_sistema DESC, r.nombre ASC',
            ['tid' => $tenantId, 'tid_count' => $tenantId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        return $this->db->fetchOne(
            'SELECT * FROM roles
             WHERE id = :id AND (tenant_id = :tid OR tenant_id IS NULL)',
            ['id' => $id, 'tid' => $tenantId],
        );
    }

    /**
     * Permisos asignados a un rol (con metadatos del permiso).
     *
     * @return list<array<string, mixed>>
     */
    public function permisosByRoleId(string $roleId): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.codigo, p.recurso, p.accion, p.descripcion
             FROM roles_permisos rp
             INNER JOIN permisos p ON p.id = rp.permiso_id
             WHERE rp.role_id = :rid
             ORDER BY p.recurso, p.accion',
            ['rid' => $roleId],
        );
    }

    /**
     * Devuelve todos los permisos del catálogo agrupados por recurso.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function catalogoPermisosByRecurso(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, codigo, recurso, accion, descripcion
             FROM permisos
             ORDER BY recurso, accion',
        );
        $byRecurso = [];
        foreach ($rows as $row) {
            $byRecurso[$row['recurso']][] = $row;
        }
        return $byRecurso;
    }

    /**
     * Busca un rol por código dentro del tenant actual.
     */
    public function findByCodigo(string $codigo): ?array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        return $this->db->fetchOne(
            'SELECT * FROM roles
             WHERE codigo = :codigo AND (tenant_id = :tid OR tenant_id IS NULL)',
            ['codigo' => $codigo, 'tid' => $tenantId],
        );
    }

    /**
     * Crea un rol propio del tenant.
     *
     * @return string ID del rol creado
     */
    public function create(string $id, string $codigo, string $nombre, ?string $descripcion): string
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $now = \DigiSecure\Shared\Infrastructure\Clock::nowString();

        $this->db->execute(
            'INSERT INTO roles (id, tenant_id, codigo, nombre, descripcion, es_sistema, created_at, updated_at)
             VALUES (:id, :tid, :codigo, :nombre, :desc, 0, :now, :now2)',
            [
                'id' => $id,
                'tid' => $tenantId,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'desc' => $descripcion,
                'now' => $now,
                'now2' => $now,
            ],
        );

        return $id;
    }

    /**
     * Actualiza nombre y descripción de un rol del tenant (no sistema).
     */
    public function update(string $id, string $nombre, ?string $descripcion): void
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $now = \DigiSecure\Shared\Infrastructure\Clock::nowString();

        $this->db->execute(
            'UPDATE roles SET nombre = :nombre, descripcion = :desc, updated_at = :now
             WHERE id = :id AND tenant_id = :tid AND es_sistema = 0',
            [
                'id' => $id,
                'tid' => $tenantId,
                'nombre' => $nombre,
                'desc' => $descripcion,
                'now' => $now,
            ],
        );
    }

    /**
     * Elimina un rol del tenant (no sistema y sin usuarios asignados).
     */
    public function delete(string $id): bool
    {
        $tenantId = $this->tenantContext->requireTenantId();

        // Primero eliminar permisos asociados
        $this->db->execute(
            'DELETE FROM roles_permisos WHERE role_id = :rid',
            ['rid' => $id],
        );

        return $this->db->affectedRows(
            'DELETE FROM roles WHERE id = :id AND tenant_id = :tid AND es_sistema = 0',
            ['id' => $id, 'tid' => $tenantId],
        ) > 0;
    }

    /**
     * Cuenta usuarios que tienen asignado un rol.
     */
    public function countUsuariosByRoleId(string $roleId): int
    {
        $tenantId = $this->tenantContext->requireTenantId();

        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM usuarios_roles ur
             INNER JOIN usuarios u ON u.id = ur.usuario_id
             WHERE ur.role_id = :rid AND u.tenant_id = :tid',
            ['rid' => $roleId, 'tid' => $tenantId],
        );
    }

    /**
     * Sincroniza los permisos de un rol: elimina todos y reasigna.
     *
     * @param list<string> $permisoIds  IDs de permisos a asignar
     */
    public function syncPermisos(string $roleId, array $permisoIds): void
    {
        $now = \DigiSecure\Shared\Infrastructure\Clock::nowString();

        // Eliminar actuales
        $this->db->execute(
            'DELETE FROM roles_permisos WHERE role_id = :rid',
            ['rid' => $roleId],
        );

        // Insertar nuevos
        foreach ($permisoIds as $pid) {
            $this->db->execute(
                'INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
                 VALUES (:rid, :pid, :now)',
                ['rid' => $roleId, 'pid' => $pid, 'now' => $now],
            );
        }
    }

    /**
     * Devuelve IDs de permisos asignados a un rol.
     *
     * @return list<string>
     */
    public function permisoIdsByRoleId(string $roleId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT permiso_id FROM roles_permisos WHERE role_id = :rid',
            ['rid' => $roleId],
        );
        return array_column($rows, 'permiso_id');
    }

    /**
     * Catálogo plano de permisos (para forms de checkboxes).
     *
     * @return list<array<string, mixed>>
     */
    public function catalogoPermisos(): array
    {
        return $this->db->fetchAll(
            'SELECT id, codigo, recurso, accion, descripcion
             FROM permisos
             ORDER BY recurso, accion',
        );
    }
}
