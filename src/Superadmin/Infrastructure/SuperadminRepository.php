<?php

declare(strict_types=1);

namespace DigiSecure\Superadmin\Infrastructure;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;

/**
 * Repositorio global (sin filtro de tenant) para el panel superadmin.
 *
 * Accede a datos cross-tenant: tenants, usuarios, registros, métricas globales.
 * Solo debe ser invocado desde rutas protegidas por permisos superadmin.*.
 */
final class SuperadminRepository
{
    public function __construct(
        private readonly PdoConnection $db,
    ) {
    }

    // ==================== Dashboard global ====================

    /**
     * @return array<string, int|float>
     */
    public function statsGlobales(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM tenants) AS total_tenants,
                (SELECT COUNT(*) FROM tenants WHERE estado = 'activo') AS tenants_activos,
                (SELECT COUNT(*) FROM tenants WHERE estado = 'pendiente') AS tenants_pendientes,
                (SELECT COUNT(*) FROM tenants WHERE estado = 'suspendido') AS tenants_suspendidos,
                (SELECT COUNT(*) FROM usuarios) AS total_usuarios,
                (SELECT COUNT(*) FROM usuarios WHERE estado = 'activo') AS usuarios_activos,
                (SELECT COUNT(*) FROM fichas) AS total_fichas,
                (SELECT COUNT(*) FROM registros_empresa WHERE evento = 'creado') AS total_registros",
        ) ?? [];

        return array_map('intval', $row);
    }

    /**
     * Tenants por plan (para chart).
     *
     * @return list<array{plan_codigo: string, cantidad: int}>
     */
    public function tenantsPorPlan(): array
    {
        return $this->db->fetchAll(
            "SELECT plan_codigo, COUNT(*) AS cantidad
               FROM tenants
              GROUP BY plan_codigo
              ORDER BY cantidad DESC",
        );
    }

    /**
     * Registros recientes (últimos N).
     *
     * @return list<array<string, mixed>>
     */
    public function registrosRecientes(int $limit = 15): array
    {
        return $this->db->fetchAll(
            'SELECT re.*, t.razon_social AS tenant_razon_social
               FROM registros_empresa re
               LEFT JOIN tenants t
                      ON t.id COLLATE utf8mb4_unicode_ci = re.tenant_id COLLATE utf8mb4_unicode_ci
              ORDER BY re.created_at DESC
              LIMIT :lim',
            ['lim' => $limit],
        );
    }

    /**
     * Registros nuevos por día (últimos 30 días).
     *
     * @return list<array{dia: string, cantidad: int}>
     */
    public function registrosPorDia(int $dias = 30): array
    {
        return $this->db->fetchAll(
            "SELECT DATE(created_at) AS dia, COUNT(*) AS cantidad
               FROM registros_empresa
              WHERE evento = 'creado'
                AND created_at >= DATE_SUB(NOW(), INTERVAL :d DAY)
              GROUP BY dia
              ORDER BY dia",
            ['d' => $dias],
        );
    }

    // ==================== Listado de tenants ====================

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listarTenants(
        ?string $busqueda = null,
        ?string $estado = null,
        ?string $planCodigo = null,
        int $pagina = 1,
        int $porPagina = 20,
    ): array {
        $where = [];
        $params = [];

        if ($busqueda !== null && $busqueda !== '') {
            $where[] = '(t.razon_social LIKE :q OR t.ruc LIKE :q2 OR t.email_contacto LIKE :q3)';
            $params['q'] = "%{$busqueda}%";
            $params['q2'] = "%{$busqueda}%";
            $params['q3'] = "%{$busqueda}%";
        }
        if ($estado !== null && $estado !== '') {
            $where[] = 't.estado = :estado';
            $params['estado'] = $estado;
        }
        if ($planCodigo !== null && $planCodigo !== '') {
            $where[] = 't.plan_codigo = :plan';
            $params['plan'] = $planCodigo;
        }

        $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM tenants t {$whereClause}",
            $params,
        );

        $offset = ($pagina - 1) * $porPagina;
        $params['lim'] = $porPagina;
        $params['off'] = $offset;

        $rows = $this->db->fetchAll(
            "SELECT t.*,
                    (SELECT COUNT(*)
                       FROM usuarios u
                      WHERE u.tenant_id COLLATE utf8mb4_unicode_ci = t.id COLLATE utf8mb4_unicode_ci
                        AND u.estado = 'activo') AS usuarios_activos,
                    (SELECT COUNT(*)
                       FROM fichas f
                      WHERE f.tenant_id COLLATE utf8mb4_unicode_ci = t.id COLLATE utf8mb4_unicode_ci) AS total_fichas
               FROM tenants t
               {$whereClause}
              ORDER BY t.created_at DESC
              LIMIT :lim OFFSET :off",
            $params,
        );

        return ['rows' => $rows, 'total' => $total];
    }

    // ==================== Detalle de un tenant ====================

    /**
     * @return array<string, mixed>|null
     */
    public function findTenant(string $tenantId): ?array
    {
        return $this->db->fetchOne(
            'SELECT t.*,
                    p.nombre AS plan_nombre, p.nivel AS plan_nivel,
                    p.precio_mensual_usd, p.precio_anual_usd
               FROM tenants t
               LEFT JOIN planes p
                      ON p.codigo COLLATE utf8mb4_unicode_ci = t.plan_codigo COLLATE utf8mb4_unicode_ci
              WHERE t.id = :id',
            ['id' => $tenantId],
        );
    }

    /**
     * @return array<string, int>
     */
    public function usoTenant(string $tenantId): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                (SELECT COUNT(*) FROM usuarios WHERE tenant_id = :t1 AND estado = 'activo') AS usuarios_activos,
                (SELECT COUNT(*) FROM usuarios WHERE tenant_id = :t2) AS usuarios_totales,
                (SELECT COUNT(*) FROM fichas WHERE tenant_id = :t3) AS total_fichas,
                (SELECT COALESCE(SUM(archivo_tamano_bytes), 0) FROM expediente_documentos ed
                   JOIN expedientes e ON e.id = ed.expediente_id
                  WHERE e.tenant_id = :t4) AS storage_bytes",
            ['t1' => $tenantId, 't2' => $tenantId, 't3' => $tenantId, 't4' => $tenantId],
        ) ?? [];

        return array_map('intval', $row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function usuariosTenant(string $tenantId): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.email, u.nombres, u.apellidos, u.estado, u.ultimo_login_at,
                    GROUP_CONCAT(r.nombre SEPARATOR ', ') AS roles
               FROM usuarios u
               LEFT JOIN usuarios_roles ur ON ur.usuario_id = u.id
               LEFT JOIN roles r ON r.id = ur.role_id
              WHERE u.tenant_id = :tid
              GROUP BY u.id
              ORDER BY u.created_at",
            ['tid' => $tenantId],
        );
    }

    /**
     * Historial de suscripción de un tenant.
     *
     * @return list<array<string, mixed>>
     */
    public function ciclosTenant(string $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT sc.*, p.nombre AS plan_nombre
               FROM suscripcion_ciclos sc
               LEFT JOIN planes p ON p.codigo = sc.plan_codigo
              WHERE sc.tenant_id = :tid
              ORDER BY sc.fecha_inicio DESC
              LIMIT 20',
            ['tid' => $tenantId],
        );
    }

    /**
     * Registros de empresa de un tenant.
     *
     * @return list<array<string, mixed>>
     */
    public function registrosTenant(string $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM registros_empresa
              WHERE tenant_id = :tid
              ORDER BY created_at DESC
              LIMIT 30',
            ['tid' => $tenantId],
        );
    }

    // ==================== Acciones ====================

    public function cambiarEstadoTenant(string $tenantId, string $nuevoEstado): bool
    {
        return $this->db->affectedRows(
            'UPDATE tenants SET estado = :es, updated_at = :now WHERE id = :id',
            ['es' => $nuevoEstado, 'now' => Clock::nowString(), 'id' => $tenantId],
        ) > 0;
    }

    public function cambiarPlanTenant(
        string $tenantId,
        string $planCodigo,
        int $maxUsuarios,
        int $maxGuardias,
        int $maxStorageMb,
        string $planInicio,
        string $planVencimiento,
    ): bool {
        return $this->db->affectedRows(
            'UPDATE tenants SET
                plan_codigo = :pc, max_usuarios = :mu, max_guardias = :mg,
                max_storage_mb = :ms, plan_inicio = :pi, plan_vencimiento = :pv,
                updated_at = :now
              WHERE id = :id',
            [
                'pc' => $planCodigo, 'mu' => $maxUsuarios, 'mg' => $maxGuardias,
                'ms' => $maxStorageMb, 'pi' => $planInicio, 'pv' => $planVencimiento,
                'now' => Clock::nowString(), 'id' => $tenantId,
            ],
        ) > 0;
    }

    /**
     * Crea un tenant global.
     *
     * @param array<string, scalar|null> $data
     */
    public function crearTenant(array $data): string
    {
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            throw new \InvalidArgumentException('El id del tenant es obligatorio.');
        }

        $this->db->execute(
            'INSERT INTO tenants
                (id, razon_social, ruc, nombre_comercial, email_contacto, telefono,
                 direccion, ciudad, provincia, pais, estado,
                 plan_codigo, plan_inicio, plan_vencimiento,
                 max_usuarios, max_guardias, max_storage_mb,
                 timezone, locale, notas, created_at, updated_at)
             VALUES
                (:id, :razon_social, :ruc, :nombre_comercial, :email_contacto, :telefono,
                 :direccion, :ciudad, :provincia, :pais, :estado,
                 :plan_codigo, :plan_inicio, :plan_vencimiento,
                 :max_usuarios, :max_guardias, :max_storage_mb,
                 :timezone, :locale, :notas, :created_at, :updated_at)',
            $data,
        );

        return $id;
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function actualizarTenant(string $tenantId, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "{$col} = :{$col}";
        }

        $data['id'] = $tenantId;

        return $this->db->affectedRows(
            'UPDATE tenants SET ' . implode(', ', $set) . ' WHERE id = :id',
            $data,
        ) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function usuariosDisponiblesParaTenant(string $tenantId, int $limit = 100): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.email, u.nombres, u.apellidos, u.estado,
                    t.id AS tenant_actual_id, t.razon_social AS tenant_actual
               FROM usuarios u
               INNER JOIN tenants t
                       ON t.id COLLATE utf8mb4_unicode_ci = u.tenant_id COLLATE utf8mb4_unicode_ci
              WHERE u.tenant_id <> :tid
                AND u.estado IN ('activo','pendiente_verificacion','inactivo')
              ORDER BY t.razon_social, u.apellidos, u.nombres
              LIMIT :lim",
            ['tid' => $tenantId, 'lim' => $limit],
        );
    }

    /**
     * @return list<array{id:string, nombre:string, codigo:string, tenant_id:?string}>
     */
    public function rolesDisponiblesParaTenant(string $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT r.id, r.nombre, r.codigo, r.tenant_id
               FROM roles r
              WHERE r.codigo <> :codigo_superadmin
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
                )
              ORDER BY es_sistema ASC, nombre ASC',
            ['tid' => $tenantId, 'tid_shadow' => $tenantId, 'codigo_superadmin' => 'superadmin'],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUsuarioGlobal(string $usuarioId): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.id, u.tenant_id, u.email, u.nombres, u.apellidos, u.estado,
                    t.razon_social AS tenant_actual
               FROM usuarios u
               INNER JOIN tenants t
                       ON t.id COLLATE utf8mb4_unicode_ci = u.tenant_id COLLATE utf8mb4_unicode_ci
              WHERE u.id = :id',
            ['id' => $usuarioId],
        );
    }

    public function asociarUsuarioATenant(
        string $usuarioId,
        string $tenantId,
        ?string $roleId,
        ?string $assignedBy,
    ): void {
        $now = Clock::nowString();

        $this->db->transactional(function () use ($usuarioId, $tenantId, $roleId, $assignedBy, $now): void {
            $this->db->execute(
                'UPDATE usuarios
                    SET tenant_id = :tid, updated_at = :now
                  WHERE id = :uid',
                ['tid' => $tenantId, 'now' => $now, 'uid' => $usuarioId],
            );

            $this->db->execute(
                'DELETE FROM usuarios_roles WHERE usuario_id = :uid',
                ['uid' => $usuarioId],
            );

            if ($roleId !== null && $roleId !== '') {
                $this->db->execute(
                    'INSERT INTO usuarios_roles (usuario_id, role_id, assigned_at, assigned_by)
                     VALUES (:uid, :rid, :at, :ab)',
                    [
                        'uid' => $usuarioId,
                        'rid' => $roleId,
                        'at' => $now,
                        'ab' => $assignedBy,
                    ],
                );
            }
        });
    }
}
