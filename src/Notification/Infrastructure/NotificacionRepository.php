<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Infrastructure;

use DigiSecure\Infrastructure\Persistence\BaseRepository;
use DigiSecure\Infrastructure\Persistence\BusquedaTexto;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Notification\Domain\Enum\EstadoNotificacion;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio de la cola de notificaciones (tenant-aware) y del log de envíos.
 *
 * Operaciones críticas:
 *  - enqueue(): inserta una notificación en la cola con dedupe_key opcional.
 *  - claimPending(): selecciona y bloquea filas pendientes para procesar.
 *  - markSent() / markFailed(): cierran el ciclo de vida.
 *  - logAttempt(): registra cada intento (éxito o fallo) en notificaciones_log.
 */
final class NotificacionRepository extends BaseRepository
{
    public function __construct(PdoConnection $db, TenantContext $tenantContext)
    {
        parent::__construct($db, $tenantContext);
    }

    protected function tableName(): string
    {
        return 'notificaciones';
    }

    /**
     * Encola una notificación. Si dedupe_key ya existe en el tenant, no inserta
     * (idempotente) y devuelve null. Si inserta, devuelve el ID generado.
     *
     * @param array<string, scalar|null> $data
     */
    public function enqueue(array $data): ?string
    {
        $id = IdGenerator::ulid();
        $now = Clock::nowString();

        $row = [
            'id' => $id,
            'evento' => $data['evento'],
            'canal' => $data['canal'] ?? 'EMAIL',
            'prioridad' => $data['prioridad'] ?? 'NORMAL',
            'destinatario_usuario_id' => $data['destinatario_usuario_id'] ?? null,
            'destinatario_email' => $data['destinatario_email'] ?? null,
            'destinatario_telefono' => $data['destinatario_telefono'] ?? null,
            'destinatario_nombre' => $data['destinatario_nombre'] ?? null,
            'asunto' => $data['asunto'],
            'cuerpo_texto' => $data['cuerpo_texto'] ?? null,
            'cuerpo_html' => $data['cuerpo_html'] ?? null,
            'payload_json' => isset($data['payload']) ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE) : null,
            'entidad_tipo' => $data['entidad_tipo'] ?? null,
            'entidad_id' => $data['entidad_id'] ?? null,
            'dedupe_key' => $data['dedupe_key'] ?? null,
            'estado' => EstadoNotificacion::PENDIENTE->value,
            'scheduled_at' => $data['scheduled_at'] ?? $now,
            'enviada_at' => null,
            'intentos' => 0,
            'max_intentos' => $data['max_intentos'] ?? 3,
            'ultimo_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Dedupe explícito en código (la UNIQUE en BD es la red de seguridad)
        if ($row['dedupe_key'] !== null && $this->existsByDedupeKey((string) $row['dedupe_key'])) {
            return null;
        }

        try {
            $this->tenantInsert($row);
        } catch (\DigiSecure\Infrastructure\Persistence\DatabaseException $e) {
            // Race condition con la UNIQUE: otro proceso ya insertó la misma dedupe_key
            if (str_contains($e->getMessage(), 'uq_notif_dedupe')) {
                return null;
            }
            throw $e;
        }

        return $id;
    }

    public function existsByDedupeKey(string $dedupeKey): bool
    {
        return $this->tenantCount('dedupe_key = :dedupe_key', ['dedupe_key' => $dedupeKey]) > 0;
    }

    /**
     * Devuelve hasta $limit notificaciones pendientes con scheduled_at <= ahora.
     *
     * @return list<array<string, mixed>>
     */
    public function claimPending(int $limit = 50): array
    {
        return $this->tenantQuery(
            'SELECT * FROM notificaciones
             WHERE estado = :estado
               AND scheduled_at <= :now
               AND intentos < max_intentos
             ORDER BY prioridad DESC, scheduled_at ASC
             LIMIT :limit',
            [
                'estado' => EstadoNotificacion::PENDIENTE->value,
                'now' => Clock::nowString(),
                'limit' => $limit,
            ],
        );
    }

    public function markSent(string $id): void
    {
        $now = Clock::nowString();
        $this->tenantUpdate($id, [
            'estado' => EstadoNotificacion::ENVIADA->value,
            'enviada_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function markFailed(string $id, string $error, int $intentos, int $maxIntentos): void
    {
        $now = Clock::nowString();
        // Si ya alcanzó el máximo, marcar como FALLIDA definitiva
        $estado = $intentos >= $maxIntentos
            ? EstadoNotificacion::FALLIDA->value
            : EstadoNotificacion::PENDIENTE->value;

        $this->tenantUpdate($id, [
            'estado' => $estado,
            'intentos' => $intentos,
            'ultimo_error' => mb_substr($error, 0, 500),
            'updated_at' => $now,
        ]);
    }

    public function incrementIntentos(string $id): int
    {
        $this->db->execute(
            'UPDATE notificaciones SET intentos = intentos + 1, updated_at = :now
             WHERE id = :id AND tenant_id = :tenant_id',
            [
                'id' => $id,
                'tenant_id' => $this->tenantContext->requireTenantId(),
                'now' => Clock::nowString(),
            ],
        );
        $row = $this->tenantQueryOne('SELECT intentos FROM notificaciones WHERE id = :id', ['id' => $id]);
        return (int) ($row['intentos'] ?? 0);
    }

    /**
     * Registra un intento (éxito o fallo) en notificaciones_log.
     */
    public function logAttempt(
        string $notificacionId,
        string $canal,
        string $destinatario,
        string $resultado,
        ?string $codigoRespuesta,
        ?string $mensaje,
        ?int $duracionMs,
    ): void {
        $this->db->execute(
            'INSERT INTO notificaciones_log
                (tenant_id, notificacion_id, canal, destinatario, resultado, codigo_respuesta, mensaje, duracion_ms, intentado_at)
             VALUES
                (:tenant_id, :notificacion_id, :canal, :destinatario, :resultado, :codigo_respuesta, :mensaje, :duracion_ms, :intentado_at)',
            [
                'tenant_id' => $this->tenantContext->requireTenantId(),
                'notificacion_id' => $notificacionId,
                'canal' => $canal,
                'destinatario' => $destinatario,
                'resultado' => $resultado,
                'codigo_respuesta' => $codigoRespuesta,
                'mensaje' => $mensaje !== null ? mb_substr($mensaje, 0, 500) : null,
                'duracion_ms' => $duracionMs,
                'intentado_at' => Clock::nowString(),
            ],
        );
    }

    /**
     * Lista notificaciones del tenant con filtros y paginación.
     *
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listByTenant(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $bindings] = $this->filtroListado($filters);

        $sql = 'SELECT id, evento, canal, prioridad, destinatario_email, asunto, estado,
                       scheduled_at, enviada_at, intentos, max_intentos, ultimo_error, created_at
                FROM notificaciones';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $bindings['limit'] = $limit;
        $bindings['offset'] = $offset;

        return $this->tenantQuery($sql, $bindings);
    }

    public function countByTenant(array $filters = []): int
    {
        [$where, $bindings] = $this->filtroListado($filters);
        return $this->tenantCount($where, $bindings);
    }

    /**
     * WHERE comun del listado y del conteo: estado, canal, evento y busqueda por texto.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filtroListado(array $filters): array
    {
        $where = [];
        $bindings = [];

        foreach (['estado', 'canal', 'evento'] as $campo) {
            if (!empty($filters[$campo])) {
                $where[] = "{$campo} = :{$campo}";
                $bindings[$campo] = $filters[$campo];
            }
        }
        [$busquedaSql, $busquedaParams] = BusquedaTexto::condicion(
            (string) ($filters['q'] ?? ''),
            ['asunto', 'destinatario_email', 'destinatario_nombre', 'evento', 'canal', 'entidad_tipo'],
            'q',
        );
        if ($busquedaSql !== null) {
            $where[] = $busquedaSql;
            $bindings += $busquedaParams;
        }

        return [implode(' AND ', $where), $bindings];
    }

    /**
     * Devuelve una notificación con su historial de intentos de envío.
     *
     * @return array{notificacion: array<string, mixed>, intentos: list<array<string, mixed>>}|null
     */
    public function findByIdWithLog(string $id): ?array
    {
        $notif = $this->tenantQueryOne(
            'SELECT * FROM notificaciones WHERE id = :id',
            ['id' => $id],
        );

        if ($notif === null) {
            return null;
        }

        $intentos = $this->db->fetchAll(
            'SELECT * FROM notificaciones_log
             WHERE notificacion_id = :nid AND tenant_id = :tid
             ORDER BY intentado_at DESC',
            [
                'nid' => $id,
                'tid' => $this->tenantContext->requireTenantId(),
            ],
        );

        return [
            'notificacion' => $notif,
            'intentos' => $intentos,
        ];
    }

    /**
     * Reinicia una notificación FALLIDA para que vuelva a procesarse.
     */
    public function resetForRetry(string $id): void
    {
        $now = Clock::nowString();
        $this->tenantUpdate($id, [
            'estado' => EstadoNotificacion::PENDIENTE->value,
            'intentos' => 0,
            'ultimo_error' => null,
            'updated_at' => $now,
        ]);
    }
}
