<?php

declare(strict_types=1);

namespace DigiSecure\Infrastructure\Persistence;

use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio base para todas las tablas tenant-aware.
 *
 * PIEZA MÁS CRÍTICA DEL SISTEMA MULTI-TENANT.
 *
 * Toda query a una tabla tenant-aware DEBE pasar por los métodos de esta clase.
 * El filtro tenant_id se inyecta automáticamente en cada operación, haciendo
 * imposible que un tenant acceda a datos de otro por error de programación.
 *
 * Invariantes:
 *  1. Si no hay TenantContext establecido, lanza excepción (nunca falla silenciosamente).
 *  2. El tenant_id se bindea como parámetro preparado (nunca concatenado).
 *  3. Las subclases NO pueden saltarse el filtro (métodos protegidos, no públicos).
 */
abstract class BaseRepository
{
    public function __construct(
        protected readonly PdoConnection $db,
        protected readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Nombre de la tabla que maneja esta subclase.
     */
    abstract protected function tableName(): string;

    /**
     * Busca un registro por ID dentro del tenant actual.
     *
     * @return array<string, mixed>|null
     */
    protected function findById(string $id): ?array
    {
        $table = $this->tableName();
        $tenantId = $this->tenantContext->requireTenantId();

        return $this->db->fetchOne(
            "SELECT * FROM {$table} WHERE id = :id AND tenant_id = :tenant_id",
            ['id' => $id, 'tenant_id' => $tenantId],
        );
    }

    /**
     * Ejecuta una query SELECT con filtro de tenant automático.
     *
     * La subclase proporciona la query SIN el filtro de tenant. Este método
     * añade `AND tenant_id = :__tenant_id` al WHERE existente, o
     * `WHERE tenant_id = :__tenant_id` si no hay WHERE.
     *
     * @param array<string, scalar|null> $bindings
     * @return list<array<string, mixed>>
     */
    protected function tenantQuery(string $sql, array $bindings = []): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $sql = $this->injectTenantFilter($sql);
        $bindings['__tenant_id'] = $tenantId;

        return $this->db->fetchAll($sql, $bindings);
    }

    /**
     * Ejecuta una query SELECT que devuelve una sola fila, con filtro de tenant.
     *
     * @param array<string, scalar|null> $bindings
     * @return array<string, mixed>|null
     */
    protected function tenantQueryOne(string $sql, array $bindings = []): ?array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $sql = $this->injectTenantFilter($sql);
        $bindings['__tenant_id'] = $tenantId;

        return $this->db->fetchOne($sql, $bindings);
    }

    /**
     * Ejecuta una query escalar con filtro de tenant.
     *
     * @param array<string, scalar|null> $bindings
     */
    protected function tenantScalar(string $sql, array $bindings = []): mixed
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $sql = $this->injectTenantFilter($sql);
        $bindings['__tenant_id'] = $tenantId;

        return $this->db->fetchScalar($sql, $bindings);
    }

    /**
     * Inserta un registro asegurando que tenant_id está presente.
     *
     * @param array<string, scalar|null> $data  Columnas → valores (SIN tenant_id)
     */
    protected function tenantInsert(array $data): void
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $data['tenant_id'] = $tenantId;

        $table = $this->tableName();
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(
            static fn(string $col): string => ":{$col}",
            array_keys($data),
        ));

        $this->db->execute(
            "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})",
            $data,
        );
    }

    /**
     * Actualiza un registro por ID dentro del tenant actual.
     *
     * @param array<string, scalar|null> $data  Columnas → valores a actualizar
     * @return int Filas afectadas (0 o 1)
     */
    protected function tenantUpdate(string $id, array $data): int
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $table = $this->tableName();

        $setClauses = [];
        foreach (array_keys($data) as $col) {
            $setClauses[] = "{$col} = :{$col}";
        }
        $setString = implode(', ', $setClauses);

        $data['id'] = $id;
        $data['tenant_id'] = $tenantId;

        return $this->db->affectedRows(
            "UPDATE {$table} SET {$setString} WHERE id = :id AND tenant_id = :tenant_id",
            $data,
        );
    }

    /**
     * Cuenta registros dentro del tenant con condiciones opcionales.
     *
     * @param string $whereExtra  Condiciones extra (sin incluir tenant_id)
     * @param array<string, scalar|null> $bindings
     */
    protected function tenantCount(string $whereExtra = '', array $bindings = []): int
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $table = $this->tableName();

        $where = "tenant_id = :__tenant_id";
        if ($whereExtra !== '') {
            $where .= " AND ({$whereExtra})";
        }

        $bindings['__tenant_id'] = $tenantId;
        $result = $this->db->fetchScalar(
            "SELECT COUNT(*) FROM {$table} WHERE {$where}",
            $bindings,
        );

        return (int)$result;
    }

    /**
     * Inyecta el filtro de tenant en una query SQL.
     *
     * Busca la cláusula WHERE existente y añade AND tenant_id = :__tenant_id.
     * Si no hay WHERE, lo añade después del FROM/JOIN.
     */
    private function injectTenantFilter(string $sql): string
    {
        $filter = 'tenant_id = :__tenant_id';

        // Si ya tiene WHERE, añadir con AND
        if (preg_match('/\bWHERE\b/i', $sql)) {
            return preg_replace(
                '/\bWHERE\b/i',
                "WHERE {$filter} AND",
                $sql,
                1,
            );
        }

        // Si tiene ORDER BY, GROUP BY, LIMIT, HAVING → insertar WHERE antes
        if (preg_match('/\b(ORDER\s+BY|GROUP\s+BY|LIMIT|HAVING)\b/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            return substr($sql, 0, $pos) . "WHERE {$filter} " . substr($sql, $pos);
        }

        // Sin WHERE ni cláusulas finales: añadir al final
        return $sql . " WHERE {$filter}";
    }
}
