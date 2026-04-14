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
}
