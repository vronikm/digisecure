<?php

declare(strict_types=1);

namespace DigiSecure\Cliente\Infrastructure;

use DigiSecure\Infrastructure\Persistence\BaseRepository;

/**
 * Repositorio de la tabla `clientes` (tenant-aware).
 *
 * Lectura/escritura siempre filtrada por tenant_id via BaseRepository.
 */
final class ClienteRepository extends BaseRepository
{
    protected function tableName(): string
    {
        return 'clientes';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        return parent::findById($id);
    }

    /**
     * Lista clientes con filtros opcionales y paginacion.
     *
     * @param array{q?: string|null, estado?: string|null} $filters
     * @return list<array<string, mixed>>
     */
    public function listar(array $filters, int $limit, int $offset): array
    {
        $where = [];
        $bindings = [];

        if (!empty($filters['estado'])) {
            $where[] = 'estado = :estado';
            $bindings['estado'] = (string) $filters['estado'];
        } else {
            // Por defecto excluir archivados, salvo que se pida explicitamente
            $where[] = "estado <> 'archivado'";
        }

        if (!empty($filters['q'])) {
            $where[] = '(razon_social LIKE :q_razon OR nombre_comercial LIKE :q_nombre OR documento LIKE :q_doc OR contacto_nombre LIKE :q_contacto)';
            $q = '%' . (string) $filters['q'] . '%';
            $bindings['q_razon'] = $q;
            $bindings['q_nombre'] = $q;
            $bindings['q_doc'] = $q;
            $bindings['q_contacto'] = $q;
        }

        $sql = 'SELECT id, razon_social, nombre_comercial, tipo_documento, documento,
                       contacto_nombre, contacto_email, contacto_telefono,
                       ciudad, sector, estado, archivado_at, created_at
                FROM clientes';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY razon_social ASC LIMIT :limit OFFSET :offset';
        $bindings['limit'] = $limit;
        $bindings['offset'] = $offset;

        return $this->tenantQuery($sql, $bindings);
    }

    /**
     * Cuenta clientes para paginacion.
     *
     * @param array{q?: string|null, estado?: string|null} $filters
     */
    public function contar(array $filters): int
    {
        $where = [];
        $bindings = [];

        if (!empty($filters['estado'])) {
            $where[] = 'estado = :estado';
            $bindings['estado'] = (string) $filters['estado'];
        } else {
            $where[] = "estado <> 'archivado'";
        }

        if (!empty($filters['q'])) {
            $where[] = '(razon_social LIKE :q_razon OR nombre_comercial LIKE :q_nombre OR documento LIKE :q_doc OR contacto_nombre LIKE :q_contacto)';
            $q = '%' . (string) $filters['q'] . '%';
            $bindings['q_razon'] = $q;
            $bindings['q_nombre'] = $q;
            $bindings['q_doc'] = $q;
            $bindings['q_contacto'] = $q;
        }

        return $this->tenantCount(implode(' AND ', $where), $bindings);
    }

    /**
     * Devuelve true si ya existe otro cliente con el mismo documento en el tenant.
     */
    public function existeDocumento(string $documento, ?string $excluyendoId = null): bool
    {
        $sql = 'documento = :documento';
        $bindings = ['documento' => $documento];
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
     * KPIs simples para el dashboard del listado.
     *
     * @return array{total: int, activos: int, inactivos: int, archivados: int}
     */
    public function kpis(): array
    {
        return [
            'total'      => $this->tenantCount(),
            'activos'    => $this->tenantCount("estado = 'activo'"),
            'inactivos'  => $this->tenantCount("estado = 'inactivo'"),
            'archivados' => $this->tenantCount("estado = 'archivado'"),
        ];
    }
}
