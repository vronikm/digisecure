<?php

declare(strict_types=1);

namespace DigiSecure\Puesto\Infrastructure;

use DigiSecure\Infrastructure\Persistence\BaseRepository;
use DigiSecure\Infrastructure\Persistence\BusquedaTexto;

/**
 * Repositorio de la tabla `puestos` (tenant-aware).
 * Hace JOIN a `clientes` para mostrar la razon social en listados/detalle.
 */
final class PuestoRepository extends BaseRepository
{
    /** Columnas donde busca el listado (cada palabra debe aparecer en alguna). */
    private const COLUMNAS_BUSQUEDA = [
        'p.nombre', 'p.codigo', 'c.razon_social', 'c.nombre_comercial',
        'p.direccion', 'p.ciudad', 'p.tipo', 'p.modalidad',
    ];

    protected function tableName(): string
    {
        return 'puestos';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        // No usamos tenantQueryOne(): su filtro auto-inyectado (`tenant_id = ...`)
        // queda sin calificar y resulta ambiguo por el JOIN con `clientes`.
        // Calificamos explícitamente con p.tenant_id, igual que listar()/contar().
        return $this->db->fetchOne(
            'SELECT p.*, c.razon_social AS cliente_razon_social, c.nombre_comercial AS cliente_nombre_comercial
             FROM puestos p
             INNER JOIN clientes c ON c.id = p.cliente_id AND c.tenant_id = p.tenant_id
             WHERE p.tenant_id = :__tenant_id AND p.id = :id',
            [
                '__tenant_id' => $this->tenantContext->requireTenantId(),
                'id' => $id,
            ],
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

        [$busquedaSql, $busquedaParams] = BusquedaTexto::condicion((string) ($filters['q'] ?? ''), self::COLUMNAS_BUSQUEDA, 'q');
        if ($busquedaSql !== null) {
            $where[] = $busquedaSql;
            $bindings += $busquedaParams;
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
        [$busquedaSql, $busquedaParams] = BusquedaTexto::condicion((string) ($filters['q'] ?? ''), self::COLUMNAS_BUSQUEDA, 'q');
        if ($busquedaSql !== null) {
            $where[] = $busquedaSql;
            $bindings += $busquedaParams;
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
