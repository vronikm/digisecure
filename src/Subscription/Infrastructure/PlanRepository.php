<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Infrastructure;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Subscription\Domain\Plan;

/**
 * Repositorio del catálogo global de planes.
 *
 * La tabla `planes` NO tiene tenant_id — todos los tenants comparten el catálogo.
 * Solo lectura desde la UI; los cambios al catálogo se hacen vía migración o
 * por super-admin (fuera del alcance de Fase 11).
 */
final class PlanRepository
{
    private const COLUMNS = 'id, codigo, nombre, descripcion, nivel,
                             precio_mensual_usd, precio_anual_usd,
                             max_usuarios, max_guardias, max_storage_mb,
                             caracteristicas, es_publico, activo, orden';

    public function __construct(
        private readonly PdoConnection $db,
    ) {
    }

    public function findByCodigo(string $codigo): ?Plan
    {
        $row = $this->db->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM planes WHERE codigo = :codigo LIMIT 1',
            ['codigo' => $codigo],
        );

        return $row !== null ? Plan::fromRow($row) : null;
    }

    /**
     * @return list<Plan>
     */
    public function listarActivos(bool $soloPublicos = true): array
    {
        $where = 'activo = 1';
        if ($soloPublicos) {
            $where .= ' AND es_publico = 1';
        }

        $rows = $this->db->fetchAll(
            'SELECT ' . self::COLUMNS . " FROM planes WHERE {$where} ORDER BY orden ASC, nivel ASC",
        );

        return array_map(static fn(array $r) => Plan::fromRow($r), $rows);
    }

    /**
     * Lista el catalogo global de planes (activos e inactivos) para superadmin.
     *
     * @return list<array<string, mixed>>
     */
    public function listarCatalogoGlobal(?string $busqueda = null, ?string $estado = null, ?string $publico = null): array
    {
        $where = [];
        $bindings = [];

        if ($busqueda !== null && $busqueda !== '') {
            $where[] = '(codigo LIKE :q OR nombre LIKE :q2 OR descripcion LIKE :q3)';
            $bindings['q'] = '%' . $busqueda . '%';
            $bindings['q2'] = '%' . $busqueda . '%';
            $bindings['q3'] = '%' . $busqueda . '%';
        }

        if ($estado !== null && $estado !== '') {
            if ($estado === 'activos') {
                $where[] = 'activo = 1';
            } elseif ($estado === 'inactivos') {
                $where[] = 'activo = 0';
            }
        }

        if ($publico !== null && $publico !== '') {
            if ($publico === 'publicos') {
                $where[] = 'es_publico = 1';
            } elseif ($publico === 'privados') {
                $where[] = 'es_publico = 0';
            }
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        return $this->db->fetchAll(
            'SELECT ' . self::COLUMNS . ', created_at, updated_at
               FROM planes
               ' . $whereSql . '
              ORDER BY orden ASC, nivel ASC, nombre ASC',
            $bindings,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRawByCodigo(string $codigo): ?array
    {
        return $this->db->fetchOne(
            'SELECT ' . self::COLUMNS . ', created_at, updated_at
               FROM planes
              WHERE codigo = :codigo
              LIMIT 1',
            ['codigo' => $codigo],
        );
    }

    public function existsCodigo(string $codigo, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM planes WHERE codigo = :codigo';
        $bindings = ['codigo' => $codigo];

        if ($excludeId !== null && $excludeId !== '') {
            $sql .= ' AND id <> :exclude_id';
            $bindings['exclude_id'] = $excludeId;
        }

        return (int) $this->db->fetchScalar($sql, $bindings) > 0;
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function create(array $data): string
    {
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            throw new \InvalidArgumentException('El id del plan es obligatorio.');
        }

        $this->db->execute(
            'INSERT INTO planes
                (id, codigo, nombre, descripcion, nivel, precio_mensual_usd, precio_anual_usd,
                 max_usuarios, max_guardias, max_storage_mb, caracteristicas, es_publico, activo, orden,
                 created_at, updated_at)
             VALUES
                (:id, :codigo, :nombre, :descripcion, :nivel, :precio_mensual_usd, :precio_anual_usd,
                 :max_usuarios, :max_guardias, :max_storage_mb, :caracteristicas, :es_publico, :activo, :orden,
                 :created_at, :updated_at)',
            $data,
        );

        return $id;
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function updateById(string $id, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $data['id'] = $id;

        return $this->db->affectedRows(
            'UPDATE planes SET ' . implode(', ', $set) . ' WHERE id = :id',
            $data,
        ) > 0;
    }

    public function cambiarEstado(string $codigo, bool $activo, string $updatedAt): bool
    {
        return $this->db->affectedRows(
            'UPDATE planes
                SET activo = :activo, updated_at = :updated_at
              WHERE codigo = :codigo',
            [
                'activo' => $activo ? 1 : 0,
                'updated_at' => $updatedAt,
                'codigo' => $codigo,
            ],
        ) > 0;
    }
}
