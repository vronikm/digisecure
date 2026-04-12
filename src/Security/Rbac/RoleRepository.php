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
}
