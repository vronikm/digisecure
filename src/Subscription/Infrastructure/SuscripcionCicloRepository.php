<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Infrastructure;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio del historial de ciclos de suscripción del tenant actual.
 *
 * Tabla tenant-aware (todas las consultas filtran por tenant_id del contexto).
 */
final class SuscripcionCicloRepository
{
    private const COLUMNS = 'id, tenant_id, plan_codigo, tipo, fecha_inicio, fecha_fin,
                             precio_usd, estado_pago, metodo_pago, referencia_pago,
                             pagado_at, notas, created_by, created_at, updated_at';

    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historial(int $limit = 50): array
    {
        $tid = $this->tenantContext->requireTenantId();

        return $this->db->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM suscripcion_ciclos
              WHERE tenant_id = :tid
              ORDER BY fecha_inicio DESC, created_at DESC
              LIMIT :lim',
            ['tid' => $tid, 'lim' => $limit],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cicloVigente(): ?array
    {
        $tid = $this->tenantContext->requireTenantId();

        return $this->db->fetchOne(
            'SELECT ' . self::COLUMNS . " FROM suscripcion_ciclos
              WHERE tenant_id = :tid
                AND fecha_inicio <= CURDATE()
                AND fecha_fin >= CURDATE()
              ORDER BY fecha_inicio DESC
              LIMIT 1",
            ['tid' => $tid],
        );
    }

    /**
     * @param array{plan_codigo:string, tipo:string, fecha_inicio:string, fecha_fin:string,
     *              precio_usd:float|int|string, estado_pago?:string, metodo_pago?:?string,
     *              referencia_pago?:?string, pagado_at?:?string, notas?:?string,
     *              created_by?:?string} $data
     */
    public function insertar(array $data): string
    {
        $tid = $this->tenantContext->requireTenantId();
        $id = IdGenerator::ulid();
        $now = Clock::nowString();

        $this->db->affectedRows(
            'INSERT INTO suscripcion_ciclos
                (id, tenant_id, plan_codigo, tipo, fecha_inicio, fecha_fin,
                 precio_usd, estado_pago, metodo_pago, referencia_pago,
                 pagado_at, notas, created_by, created_at, updated_at)
             VALUES
                (:id, :tid, :plan, :tipo, :ini, :fin,
                 :precio, :estado, :metodo, :ref,
                 :pagado, :notas, :creador, :ca, :ua)',
            [
                'id'      => $id,
                'tid'     => $tid,
                'plan'    => $data['plan_codigo'],
                'tipo'    => $data['tipo'],
                'ini'     => $data['fecha_inicio'],
                'fin'     => $data['fecha_fin'],
                'precio'  => $data['precio_usd'],
                'estado'  => $data['estado_pago'] ?? 'pendiente',
                'metodo'  => $data['metodo_pago'] ?? null,
                'ref'     => $data['referencia_pago'] ?? null,
                'pagado'  => $data['pagado_at'] ?? null,
                'notas'   => $data['notas'] ?? null,
                'creador' => $data['created_by'] ?? null,
                'ca'      => $now,
                'ua'      => $now,
            ],
        );

        return $id;
    }
}
