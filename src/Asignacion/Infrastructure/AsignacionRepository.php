<?php

declare(strict_types=1);

namespace DigiSecure\Asignacion\Infrastructure;

use DigiSecure\Infrastructure\Persistence\BaseRepository;

/**
 * Repositorio de la tabla `asignaciones` (tenant-aware).
 * Hace JOIN a fichas + puestos + clientes para listados/detalle.
 */
final class AsignacionRepository extends BaseRepository
{
    protected function tableName(): string
    {
        return 'asignaciones';
    }

    private const SELECT_BASE = 'a.id, a.ficha_id, a.puesto_id, a.fecha_inicio, a.fecha_fin,
                                  a.estado, a.motivo_fin, a.finalizada_at, a.finalizada_por,
                                  a.rol_en_puesto, a.notas, a.created_by, a.created_at, a.updated_at,
                                  f.correlativo AS ficha_correlativo, f.apellidos AS ficha_apellidos,
                                  f.nombres AS ficha_nombres, f.estado AS ficha_estado,
                                  p.nombre AS puesto_nombre, p.codigo AS puesto_codigo,
                                  p.cliente_id AS cliente_id, c.razon_social AS cliente_razon_social';

    private const FROM_JOINS = 'FROM asignaciones a
                                 INNER JOIN fichas   f ON f.id = a.ficha_id   AND f.tenant_id = a.tenant_id
                                 INNER JOIN puestos  p ON p.id = a.puesto_id  AND p.tenant_id = a.tenant_id
                                 INNER JOIN clientes c ON c.id = p.cliente_id AND c.tenant_id = a.tenant_id';

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        return $this->tenantQueryOne(
            'SELECT ' . self::SELECT_BASE . ' ' . self::FROM_JOINS . ' WHERE a.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @param array{
     *   q?: string|null,
     *   estado?: string|null,
     *   ficha_id?: string|null,
     *   puesto_id?: string|null,
     *   cliente_id?: string|null,
     * } $filters
     * @return list<array<string, mixed>>
     */
    public function listar(array $filters, int $limit, int $offset): array
    {
        [$where, $bindings] = $this->buildWhere($filters);

        $sql = 'SELECT ' . self::SELECT_BASE . ' ' . self::FROM_JOINS
             . ' WHERE a.tenant_id = :__tenant_id'
             . ($where !== '' ? ' AND ' . $where : '')
             . ' ORDER BY a.estado = "activa" DESC, a.fecha_inicio DESC, a.created_at DESC'
             . ' LIMIT :limit OFFSET :offset';

        $bindings['__tenant_id'] = $this->tenantContext->requireTenantId();
        $bindings['limit'] = $limit;
        $bindings['offset'] = $offset;

        return $this->db->fetchAll($sql, $bindings);
    }

    public function contar(array $filters): int
    {
        [$where, $bindings] = $this->buildWhere($filters);
        $sql = 'SELECT COUNT(*) ' . self::FROM_JOINS
             . ' WHERE a.tenant_id = :__tenant_id'
             . ($where !== '' ? ' AND ' . $where : '');
        $bindings['__tenant_id'] = $this->tenantContext->requireTenantId();
        return (int) $this->db->fetchScalar($sql, $bindings);
    }

    /**
     * Asignaciones activas de una ficha (para mostrar en su detalle).
     * @return list<array<string, mixed>>
     */
    public function listarActivasPorFicha(string $fichaId): array
    {
        $sql = 'SELECT ' . self::SELECT_BASE . ' ' . self::FROM_JOINS
             . ' WHERE a.tenant_id = :__tenant_id AND a.ficha_id = :fid AND a.estado = "activa"'
             . ' ORDER BY a.fecha_inicio DESC';
        return $this->db->fetchAll($sql, [
            '__tenant_id' => $this->tenantContext->requireTenantId(),
            'fid' => $fichaId,
        ]);
    }

    /**
     * Asignaciones activas de un puesto (para mostrar en su detalle).
     * @return list<array<string, mixed>>
     */
    public function listarActivasPorPuesto(string $puestoId): array
    {
        $sql = 'SELECT ' . self::SELECT_BASE . ' ' . self::FROM_JOINS
             . ' WHERE a.tenant_id = :__tenant_id AND a.puesto_id = :pid AND a.estado = "activa"'
             . ' ORDER BY a.fecha_inicio ASC';
        return $this->db->fetchAll($sql, [
            '__tenant_id' => $this->tenantContext->requireTenantId(),
            'pid' => $puestoId,
        ]);
    }

    /**
     * Cuenta asignaciones activas a un puesto (para validar plazas).
     */
    public function contarActivasPorPuesto(string $puestoId, ?string $excluyendoAsignacionId = null): int
    {
        $sql = "estado = 'activa' AND puesto_id = :pid";
        $bindings = ['pid' => $puestoId];
        if ($excluyendoAsignacionId !== null) {
            $sql .= ' AND id <> :id';
            $bindings['id'] = $excluyendoAsignacionId;
        }
        return $this->tenantCount($sql, $bindings);
    }

    /**
     * Verifica si la ficha ya tiene una asignacion activa que se solape en el periodo.
     */
    public function existeSolapeFicha(
        string $fichaId,
        string $fechaInicio,
        ?string $fechaFin,
        ?string $excluyendoId = null,
    ): bool {
        $sql = "estado = 'activa' AND ficha_id = :fid";
        $bindings = ['fid' => $fichaId];

        // Solape clasico: A.inicio <= B.fin (o NULL) AND A.fin (o NULL) >= B.inicio
        $sql .= ' AND fecha_inicio <= :fechaFinSolape';
        $bindings['fechaFinSolape'] = $fechaFin ?? '9999-12-31';

        $sql .= ' AND (fecha_fin IS NULL OR fecha_fin >= :fechaInicio)';
        $bindings['fechaInicio'] = $fechaInicio;

        if ($excluyendoId !== null) {
            $sql .= ' AND id <> :id';
            $bindings['id'] = $excluyendoId;
        }

        return $this->tenantCount($sql, $bindings) > 0;
    }

    /**
     * @return array{id: string, correlativo: string, label: string}|null
     */
    public function findFicha(string $fichaId): ?array
    {
        $row = $this->tenantQueryOne(
            "SELECT id, correlativo, apellidos, nombres, estado
             FROM fichas WHERE id = :id",
            ['id' => $fichaId],
        );
        if ($row === null) return null;
        return [
            'id' => (string) $row['id'],
            'correlativo' => (string) $row['correlativo'],
            'estado' => (string) $row['estado'],
            'label' => trim(($row['apellidos'] ?? '') . ' ' . ($row['nombres'] ?? '')),
        ];
    }

    /**
     * Lista fichas COMPLETAS para el select del form (las elegibles).
     * @return list<array{id: string, correlativo: string, label: string}>
     */
    public function fichasCompletasParaSelect(): array
    {
        $rows = $this->tenantQuery(
            "SELECT id, correlativo, apellidos, nombres
             FROM fichas
             WHERE estado = 'COMPLETA'
             ORDER BY apellidos ASC, nombres ASC",
        );
        return array_map(static fn(array $r): array => [
            'id' => (string) $r['id'],
            'correlativo' => (string) $r['correlativo'],
            'label' => trim(($r['apellidos'] ?? '') . ' ' . ($r['nombres'] ?? '')),
        ], $rows);
    }

    /**
     * Lista puestos activos para el select del form.
     * @return list<array{id: string, nombre: string, cliente: string, plazas: int, ocupadas: int}>
     */
    public function puestosActivosParaSelect(): array
    {
        $sql = "SELECT p.id, p.nombre, p.num_guardias_requeridos, c.razon_social AS cliente,
                       (SELECT COUNT(*) FROM asignaciones a
                        WHERE a.puesto_id = p.id AND a.estado = 'activa' AND a.tenant_id = p.tenant_id) AS ocupadas
                FROM puestos p
                INNER JOIN clientes c ON c.id = p.cliente_id AND c.tenant_id = p.tenant_id
                WHERE p.tenant_id = :__tenant_id AND p.estado = 'activo'
                ORDER BY c.razon_social ASC, p.nombre ASC";
        $rows = $this->db->fetchAll($sql, ['__tenant_id' => $this->tenantContext->requireTenantId()]);
        return array_map(static fn(array $r): array => [
            'id' => (string) $r['id'],
            'nombre' => (string) $r['nombre'],
            'cliente' => (string) $r['cliente'],
            'plazas' => (int) $r['num_guardias_requeridos'],
            'ocupadas' => (int) $r['ocupadas'],
        ], $rows);
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function crear(array $data): void
    {
        $this->tenantInsert($data);
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function actualizar(string $id, array $data): int
    {
        return $this->tenantUpdate($id, $data);
    }

    /**
     * KPIs para el dashboard del listado.
     *
     * @return array{
     *   total: int,
     *   activas: int,
     *   finalizadas: int,
     *   por_vencer: int,
     *   plazas_total: int,
     *   plazas_cubiertas: int,
     * }
     */
    public function kpis(): array
    {
        $tid = $this->tenantContext->requireTenantId();

        $plazasTotal = (int) $this->db->fetchScalar(
            "SELECT COALESCE(SUM(num_guardias_requeridos), 0) FROM puestos
             WHERE tenant_id = :tid AND estado = 'activo'",
            ['tid' => $tid],
        );

        $plazasCubiertas = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM asignaciones
             WHERE tenant_id = :tid AND estado = 'activa'",
            ['tid' => $tid],
        );

        $porVencer = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM asignaciones
             WHERE tenant_id = :tid AND estado = 'activa'
               AND fecha_fin IS NOT NULL
               AND fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)",
            ['tid' => $tid],
        );

        return [
            'total'            => $this->tenantCount(),
            'activas'          => $this->tenantCount("estado = 'activa'"),
            'finalizadas'      => $this->tenantCount("estado = 'finalizada'"),
            'por_vencer'       => $porVencer,
            'plazas_total'     => $plazasTotal,
            'plazas_cubiertas' => $plazasCubiertas,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $where = [];
        $bindings = [];

        if (!empty($filters['estado'])) {
            $where[] = 'a.estado = :estado';
            $bindings['estado'] = (string) $filters['estado'];
        }
        if (!empty($filters['ficha_id'])) {
            $where[] = 'a.ficha_id = :ficha_id';
            $bindings['ficha_id'] = (string) $filters['ficha_id'];
        }
        if (!empty($filters['puesto_id'])) {
            $where[] = 'a.puesto_id = :puesto_id';
            $bindings['puesto_id'] = (string) $filters['puesto_id'];
        }
        if (!empty($filters['cliente_id'])) {
            $where[] = 'p.cliente_id = :cliente_id';
            $bindings['cliente_id'] = (string) $filters['cliente_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(f.apellidos LIKE :q_apellidos OR f.nombres LIKE :q_nombres OR f.correlativo LIKE :q_correlativo OR p.nombre LIKE :q_puesto OR c.razon_social LIKE :q_cliente)';
            $q = '%' . (string) $filters['q'] . '%';
            $bindings['q_apellidos'] = $q;
            $bindings['q_nombres'] = $q;
            $bindings['q_correlativo'] = $q;
            $bindings['q_puesto'] = $q;
            $bindings['q_cliente'] = $q;
        }

        return [implode(' AND ', $where), $bindings];
    }
}
