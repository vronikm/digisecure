<?php

declare(strict_types=1);

namespace DigiSecure\Security\Lopdp\Infrastructure;

use DigiSecure\Infrastructure\Persistence\BaseRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio tenant-aware de consentimientos LOPDP.
 *
 * La tabla `consentimientos_lopdp` se creó en la migración 001 y sirve para
 * registrar la aceptación explícita de cada titular sobre un propósito
 * específico. Un consentimiento activo es aquel que `aceptado=1` y
 * `revocado_at IS NULL`.
 */
final class ConsentimientoRepository extends BaseRepository
{
    public function __construct(PdoConnection $db, TenantContext $tenantContext)
    {
        parent::__construct($db, $tenantContext);
    }

    protected function tableName(): string
    {
        return 'consentimientos_lopdp';
    }

    /**
     * Obtiene un consentimiento por ID dentro del tenant actual.
     *
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        return parent::findById($id);
    }

    /**
     * Verifica si existe un consentimiento activo para titular+propósito.
     */
    public function consentimientoActivo(string $titularTipo, string $titularId, string $proposito): bool
    {
        $row = $this->tenantQueryOne(
            'SELECT id FROM consentimientos_lopdp
              WHERE titular_tipo = :tipo
                AND titular_id = :tid
                AND proposito = :prop
                AND aceptado = 1
                AND revocado_at IS NULL
              ORDER BY aceptado_at DESC
              LIMIT 1',
            [
                'tipo' => $titularTipo,
                'tid' => $titularId,
                'prop' => $proposito,
            ],
        );
        return $row !== null;
    }

    /**
     * Registra un nuevo consentimiento.
     *
     * @param array<string, mixed> $data
     */
    public function registrar(array $data): string
    {
        $id = IdGenerator::ulid();
        $now = Clock::nowString();
        $data['id'] = $id;
        $data['aceptado_at'] = $data['aceptado_at'] ?? $now;
        $data['created_at'] = $now;

        $this->tenantInsert($data);
        return $id;
    }

    /**
     * Revoca un consentimiento activo marcando `revocado_at`.
     */
    public function revocar(string $id, string $motivo): bool
    {
        return $this->tenantUpdate($id, [
            'revocado_at' => Clock::nowString(),
            'revocado_motivo' => $motivo,
        ]) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorTitular(string $titularTipo, string $titularId): array
    {
        return $this->tenantQuery(
            'SELECT id, proposito, base_legal, texto_legal_version, aceptado,
                    aceptado_at, revocado_at, revocado_motivo
             FROM consentimientos_lopdp
             WHERE titular_tipo = :tipo AND titular_id = :tid
             ORDER BY aceptado_at DESC',
            ['tipo' => $titularTipo, 'tid' => $titularId],
        );
    }

    /**
     * Cuenta consentimientos activos del tenant (para KPI del dashboard LOPDP).
     */
    public function contarActivos(): int
    {
        return $this->tenantCount(
            'aceptado = 1 AND revocado_at IS NULL',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarRecientes(int $limit = 20): array
    {
        return $this->tenantQuery(
            'SELECT id, titular_tipo, titular_id, proposito, aceptado, aceptado_at, revocado_at
             FROM consentimientos_lopdp
             ORDER BY aceptado_at DESC
             LIMIT :lim',
            ['lim' => $limit],
        );
    }

    /**
     * Lista consentimientos con filtros opcionales para la vista operativa.
     *
     * Filtros soportados:
     *  - estado: activo|revocado|rechazado
     *  - desde: YYYY-MM-DD (fecha aceptación)
     *  - hasta: YYYY-MM-DD (fecha aceptación)
     *
     * @param array{estado?: string, desde?: string, hasta?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function listarFiltrados(int $limit = 25, int $offset = 0, array $filters = []): array
    {
        $bindings = [
            'lim' => $limit,
            'off' => $offset,
        ];
        $where = $this->buildFilteredWhere($filters, $bindings);

        $sql = 'SELECT id, titular_tipo, titular_id, proposito, aceptado, aceptado_at, revocado_at
                FROM consentimientos_lopdp';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ' ORDER BY aceptado_at DESC LIMIT :lim OFFSET :off';

        return $this->tenantQuery($sql, $bindings);
    }

    /**
     * Cuenta consentimientos con filtros opcionales.
     *
     * @param array{estado?: string, desde?: string, hasta?: string} $filters
     */
    public function contarFiltrados(array $filters = []): int
    {
        $bindings = [];
        $where = $this->buildFilteredWhere($filters, $bindings);

        return $this->tenantCount($where, $bindings);
    }

    /**
     * @param array{estado?: string, desde?: string, hasta?: string} $filters
     * @param array<string, scalar|null> $bindings
     */
    private function buildFilteredWhere(array $filters, array &$bindings): string
    {
        $conditions = [];

        $estado = (string)($filters['estado'] ?? '');
        if ($estado === 'activo') {
            $conditions[] = 'aceptado = 1 AND revocado_at IS NULL';
        } elseif ($estado === 'revocado') {
            $conditions[] = 'revocado_at IS NOT NULL';
        } elseif ($estado === 'rechazado') {
            $conditions[] = 'aceptado = 0';
        }

        $desde = (string)($filters['desde'] ?? '');
        if ($desde !== '') {
            $conditions[] = 'DATE(aceptado_at) >= :desde';
            $bindings['desde'] = $desde;
        }

        $hasta = (string)($filters['hasta'] ?? '');
        if ($hasta !== '') {
            $conditions[] = 'DATE(aceptado_at) <= :hasta';
            $bindings['hasta'] = $hasta;
        }

        return implode(' AND ', $conditions);
    }
}
