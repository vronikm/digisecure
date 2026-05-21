<?php

declare(strict_types=1);

namespace DigiSecure\Puesto\Infrastructure;

use DigiSecure\Infrastructure\Persistence\BaseRepository;

/**
 * Repositorio de la tabla `puestos` (tenant-aware).
 * Hace JOIN a `clientes` para mostrar la razon social en listados/detalle.
 */
final class PuestoRepository extends BaseRepository
{
    protected function tableName(): string
    {
        return 'puestos';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        return $this->tenantQueryOne(
            'SELECT p.*, c.razon_social AS cliente_razon_social, c.nombre_comercial AS cliente_nombre_comercial
             FROM puestos p
             INNER JOIN clientes c ON c.id = p.cliente_id AND c.tenant_id = p.tenant_id
             WHERE p.id = :id',
            ['id' => $id],
        );
    }

    /**
     * @param array{q?: string|null, estado?: string|null, tipo?: string|null, cliente_id?: string|null} $filters
     * @return list<array<string, mixed>>
     */
    public function listar(array $filters, int $limit, int $offset): array
    {
        $where = [];
        $bindings = [];

        if (!empty($filters['estado'])) {
            $where[] = 'p.estado = :estado';
            $bindings['estado'] = (string) $filters['estado'];
        } else {
            $where[] = "p.estado <> 'archivado'";
        }

        if (!empty($filters['tipo'])) {
            $where[] = 'p.tipo = :tipo';
            $bindings['tipo'] = (string) $filters['tipo'];
        }

        if (!empty($filters['cliente_id'])) {
            $where[] = 'p.cliente_id = :cliente_id';
            $bindings['cliente_id'] = (string) $filters['cliente_id'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(p.nombre LIKE :q_nombre OR p.codigo LIKE :q_codigo OR c.razon_social LIKE :q_cliente OR p.direccion LIKE :q_direccion)';
            $q = '%' . (string) $filters['q'] . '%';
            $bindings['q_nombre'] = $q;
            $bindings['q_codigo'] = $q;
            $bindings['q_cliente'] = $q;
            $bindings['q_direccion'] = $q;
        }

        $sql = 'SELECT p.id, p.codigo, p.nombre, p.tipo, p.modalidad,
                       p.ciudad, p.num_guardias_requeridos, p.estado,
                       p.archivado_at, p.created_at,
                       p.cliente_id, c.razon_social AS cliente_razon_social
                FROM puestos p
                INNER JOIN clientes c ON c.id = p.cliente_id AND c.tenant_id = p.tenant_id
                WHERE p.tenant_id = :__tenant_id';
        if ($where !== []) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.nombre ASC LIMIT :limit OFFSET :offset';
        $bindings['__tenant_id'] = $this->tenantContext->requireTenantId();
        $bindings['limit'] = $limit;
        $bindings['offset'] = $offset;

        return $this->db->fetchAll($sql, $bindings);
    }

    /**
     * @param array{q?: string|null, estado?: string|null, tipo?: string|null, cliente_id?: string|null} $filters
     */
    public function contar(array $filters): int
    {
        $where = [];
        $bindings = [];

        if (!empty($filters['estado'])) {
            $where[] = 'p.estado = :estado';
            $bindings['estado'] = (string) $filters['estado'];
        } else {
            $where[] = "p.estado <> 'archivado'";
        }
        if (!empty($filters['tipo'])) {
            $where[] = 'p.tipo = :tipo';
            $bindings['tipo'] = (string) $filters['tipo'];
        }
        if (!empty($filters['cliente_id'])) {
            $where[] = 'p.cliente_id = :cliente_id';
            $bindings['cliente_id'] = (string) $filters['cliente_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(p.nombre LIKE :q_nombre OR p.codigo LIKE :q_codigo OR c.razon_social LIKE :q_cliente OR p.direccion LIKE :q_direccion)';
            $q = '%' . (string) $filters['q'] . '%';
            $bindings['q_nombre'] = $q;
            $bindings['q_codigo'] = $q;
            $bindings['q_cliente'] = $q;
            $bindings['q_direccion'] = $q;
        }

        $sql = 'SELECT COUNT(*) FROM puestos p
                INNER JOIN clientes c ON c.id = p.cliente_id AND c.tenant_id = p.tenant_id
                WHERE p.tenant_id = :__tenant_id';
        if ($where !== []) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        $bindings['__tenant_id'] = $this->tenantContext->requireTenantId();

        return (int) $this->db->fetchScalar($sql, $bindings);
    }

    /**
     * Lista puestos de un cliente especifico (para mostrar en su detalle).
     * @return list<array<string, mixed>>
     */
    public function listarPorCliente(string $clienteId): array
    {
        return $this->tenantQuery(
            "SELECT id, codigo, nombre, tipo, modalidad, num_guardias_requeridos, estado, ciudad
             FROM puestos
             WHERE cliente_id = :cid AND estado <> 'archivado'
             ORDER BY nombre ASC",
            ['cid' => $clienteId],
        );
    }

    /**
     * Valida que el cliente exista y este activo en el tenant actual.
     */
    public function clienteEsValido(string $clienteId): bool
    {
        $row = $this->tenantQueryOne(
            "SELECT id FROM clientes WHERE id = :id AND estado <> 'archivado'",
            ['id' => $clienteId],
        );
        return $row !== null;
    }

    public function existeCodigo(string $codigo, ?string $excluyendoId = null): bool
    {
        $sql = 'codigo = :codigo';
        $bindings = ['codigo' => $codigo];
        if ($excluyendoId !== null) {
            $sql .= ' AND id <> :id';
            $bindings['id'] = $excluyendoId;
        }
        return $this->tenantCount($sql, $bindings) > 0;
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
     * @return array{total: int, activos: int, inactivos: int, archivados: int, plazas: int}
     */
    public function kpis(): array
    {
        $plazas = (int) $this->tenantScalar(
            "SELECT COALESCE(SUM(num_guardias_requeridos), 0) FROM puestos WHERE estado = 'activo'",
        );

        return [
            'total'      => $this->tenantCount(),
            'activos'    => $this->tenantCount("estado = 'activo'"),
            'inactivos'  => $this->tenantCount("estado = 'inactivo'"),
            'archivados' => $this->tenantCount("estado = 'archivado'"),
            'plazas'     => $plazas,
        ];
    }

    /**
     * @return list<array{id: string, razon_social: string}>
     */
    public function clientesActivosParaSelect(): array
    {
        return $this->tenantQuery(
            "SELECT id, razon_social FROM clientes WHERE estado = 'activo' ORDER BY razon_social ASC",
        );
    }
}
