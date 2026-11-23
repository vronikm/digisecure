<?php

declare(strict_types=1);

namespace DigiSecure\Security\Lopdp\Infrastructure;

use DigiSecure\Infrastructure\Persistence\BaseRepository;
use DigiSecure\Infrastructure\Persistence\BusquedaTexto;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio tenant-aware para solicitudes de derechos ARCO-P.
 *
 * Todas las queries pasan por BaseRepository → filtro tenant automático.
 */
final class SolicitudArcopRepository extends BaseRepository
{
    public function __construct(PdoConnection $db, TenantContext $tenantContext)
    {
        parent::__construct($db, $tenantContext);
    }

    protected function tableName(): string
    {
        return 'lopdp_solicitudes_arcop';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        return parent::findById($id);
    }

    /**
     * @param array{estado?: string, tipo?: string, busqueda?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function listar(int $limit, int $offset, array $filters = []): array
    {
        $conditions = [];
        $bindings = [];

        if (!empty($filters['estado'])) {
            $conditions[] = 'estado = :estado';
            $bindings['estado'] = $filters['estado'];
        }
        if (!empty($filters['tipo'])) {
            $conditions[] = 'tipo = :tipo';
            $bindings['tipo'] = $filters['tipo'];
        }
        [$busquedaSql, $busquedaParams] = BusquedaTexto::condicion(
            (string) ($filters['busqueda'] ?? ''),
            ['titular_nombre', 'titular_cedula', 'titular_email', 'tipo', 'estado'],
            'q',
        );
        if ($busquedaSql !== null) {
            $conditions[] = $busquedaSql;
            $bindings += $busquedaParams;
        }

        $where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT id, tipo, estado, titular_tipo, titular_nombre, titular_cedula, titular_email,
                       fecha_recepcion, fecha_limite, respondida_at, created_at
                FROM lopdp_solicitudes_arcop{$where}
                ORDER BY fecha_limite ASC, created_at DESC
                LIMIT :lim OFFSET :off";

        $bindings['lim'] = $limit;
        $bindings['off'] = $offset;

        return $this->tenantQuery($sql, $bindings);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function contar(array $filters = []): int
    {
        $conditions = [];
        $bindings = [];
        if (!empty($filters['estado'])) {
            $conditions[] = 'estado = :estado';
            $bindings['estado'] = $filters['estado'];
        }
        if (!empty($filters['tipo'])) {
            $conditions[] = 'tipo = :tipo';
            $bindings['tipo'] = $filters['tipo'];
        }
        [$busquedaSql, $busquedaParams] = BusquedaTexto::condicion(
            (string) ($filters['busqueda'] ?? ''),
            ['titular_nombre', 'titular_cedula', 'titular_email', 'tipo', 'estado'],
            'q',
        );
        if ($busquedaSql !== null) {
            $conditions[] = $busquedaSql;
            $bindings += $busquedaParams;
        }
        return $this->tenantCount(implode(' AND ', $conditions), $bindings);
    }

    /**
     * Cuenta solicitudes pendientes (RECIBIDA o EN_PROCESO) con fecha límite
     * próxima o vencida, agrupadas por nivel de urgencia.
     *
     * @return array{por_vencer: int, vencidas: int, abiertas: int}
     */
    public function estadisticasUrgencia(): array
    {
        $row = $this->tenantQueryOne(
            "SELECT
                SUM(CASE WHEN estado IN ('RECIBIDA','EN_PROCESO') AND fecha_limite < CURDATE() THEN 1 ELSE 0 END) AS vencidas,
                SUM(CASE WHEN estado IN ('RECIBIDA','EN_PROCESO') AND fecha_limite BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END) AS por_vencer,
                SUM(CASE WHEN estado IN ('RECIBIDA','EN_PROCESO') THEN 1 ELSE 0 END) AS abiertas
             FROM lopdp_solicitudes_arcop",
        );

        return [
            'vencidas' => (int)($row['vencidas'] ?? 0),
            'por_vencer' => (int)($row['por_vencer'] ?? 0),
            'abiertas' => (int)($row['abiertas'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function crear(array $data): string
    {
        $id = IdGenerator::ulid();
        $now = Clock::nowString();
        $data['id'] = $id;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $this->tenantInsert($data);
        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function actualizar(string $id, array $data): bool
    {
        $data['updated_at'] = Clock::nowString();
        return $this->tenantUpdate($id, $data) > 0;
    }
}
