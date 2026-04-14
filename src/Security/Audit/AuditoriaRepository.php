<?php

declare(strict_types=1);

namespace DigiSecure\Security\Audit;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio de lectura del log de auditoría.
 *
 * NO extiende BaseRepository porque la tabla `auditoria` usa BIGINT
 * (no ULID CHAR(26)) y porque ya existe `AuditLogger` para escritura.
 * Aplica el filtro de tenant manualmente en cada query.
 *
 * Solo lectura — la escritura va exclusivamente por AuditLogger.
 */
final class AuditoriaRepository
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Lista eventos de auditoría del tenant con filtros y paginación.
     *
     * @param array{evento?:string, nivel?:string, usuario_id?:string, entidad_tipo?:string, desde?:string, hasta?:string} $filters
     * @return list<array<string, mixed>>
     */
    public function listByTenant(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        [$where, $bindings] = $this->buildWhere($tenantId, $filters);

        $bindings['limit'] = $limit;
        $bindings['offset'] = $offset;

        return $this->db->fetchAll(
            "SELECT a.id, a.tenant_id, a.usuario_id, a.evento, a.entidad_tipo, a.entidad_id,
                    INET6_NTOA(a.ip) AS ip_text, a.user_agent, a.detalles_json, a.nivel, a.created_at,
                    u.email AS usuario_email, u.nombres AS usuario_nombres, u.apellidos AS usuario_apellidos
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE {$where}
             ORDER BY a.created_at DESC
             LIMIT :limit OFFSET :offset",
            $bindings,
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countByTenant(array $filters = []): int
    {
        $tenantId = $this->tenantContext->requireTenantId();
        [$where, $bindings] = $this->buildWhere($tenantId, $filters);

        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM auditoria a WHERE {$where}",
            $bindings,
        );
    }

    /**
     * Devuelve los códigos de evento distintos del tenant (para autocompletar/filtros).
     *
     * @return list<string>
     */
    public function distinctEventos(int $limit = 100): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT evento FROM auditoria
             WHERE tenant_id = :tid
             ORDER BY evento ASC
             LIMIT :limit',
            ['tid' => $tenantId, 'limit' => $limit],
        );
        return array_column($rows, 'evento');
    }

    /**
     * Devuelve un cursor de eventos para exportación CSV (sin límite de paginación).
     * Máximo 10000 registros para evitar abusos de memoria.
     *
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function exportByTenant(array $filters = [], int $maxRows = 10000): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        [$where, $bindings] = $this->buildWhere($tenantId, $filters);
        $bindings['limit'] = $maxRows;

        return $this->db->fetchAll(
            "SELECT a.created_at, a.nivel, a.evento, a.entidad_tipo, a.entidad_id,
                    INET6_NTOA(a.ip) AS ip_text, a.detalles_json,
                    u.email AS usuario_email, u.nombres AS usuario_nombres, u.apellidos AS usuario_apellidos
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE {$where}
             ORDER BY a.created_at DESC
             LIMIT :limit",
            $bindings,
        );
    }

    /**
     * @return array{0:string, 1:array<string, scalar|null>}
     */
    private function buildWhere(string $tenantId, array $filters): array
    {
        $where = ['a.tenant_id = :tid'];
        $bindings = ['tid' => $tenantId];

        if (!empty($filters['evento'])) {
            $where[] = 'a.evento = :evento';
            $bindings['evento'] = $filters['evento'];
        }
        if (!empty($filters['nivel'])) {
            $where[] = 'a.nivel = :nivel';
            $bindings['nivel'] = $filters['nivel'];
        }
        if (!empty($filters['usuario_id'])) {
            $where[] = 'a.usuario_id = :uid';
            $bindings['uid'] = $filters['usuario_id'];
        }
        if (!empty($filters['entidad_tipo'])) {
            $where[] = 'a.entidad_tipo = :etipo';
            $bindings['etipo'] = $filters['entidad_tipo'];
        }
        if (!empty($filters['desde'])) {
            $where[] = 'a.created_at >= :desde';
            $bindings['desde'] = $filters['desde'] . ' 00:00:00.000000';
        }
        if (!empty($filters['hasta'])) {
            $where[] = 'a.created_at <= :hasta';
            $bindings['hasta'] = $filters['hasta'] . ' 23:59:59.999999';
        }

        return [implode(' AND ', $where), $bindings];
    }
}
