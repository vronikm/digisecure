<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Application;

use DigiSecure\Notification\Domain\Enum\CanalNotificacion;
use DigiSecure\Notification\Infrastructure\Channel\NotificationChannel;
use DigiSecure\Notification\Infrastructure\NotificacionRepository;
use DigiSecure\Security\Audit\AuditLogger;

/**
 * Caso de uso: despachar notificaciones pendientes.
 *
 * Diseñado para correr como cron (bin/notify-expirations.php es uno de los
 * llamadores; un futuro bin/notify-dispatch.php sería el genérico).
 *
 * Lee la cola, intenta enviar por el canal correspondiente, y registra
 * cada intento en notificaciones_log. Marca como ENVIADA o (después de
 * agotar reintentos) como FALLIDA.
 */
final class DespacharNotificacionesService
{
    /**
     * @param array<string, NotificationChannel> $channels  Mapa canal → handler
     */
    public function __construct(
        private readonly NotificacionRepository $repo,
        private readonly AuditLogger $audit,
        private readonly array $channels,
    ) {
    }

    /**
     * @return array{procesadas:int, enviadas:int, fallidas:int}
     */
    public function execute(int $batchSize = 50, string $ip = '0.0.0.0'): array
    {
        $pendientes = $this->repo->claimPending($batchSize);

        $enviadas = 0;
        $fallidas = 0;

        foreach ($pendientes as $notif) {
            $intentos = $this->repo->incrementIntentos((string) $notif['id']);
            $canal = (string) $notif['canal'];
            $handler = $this->channels[$canal] ?? null;

            if ($handler === null) {
                $this->repo->logAttempt(
                    (string) $notif['id'],
                    $canal,
                    (string) ($notif['destinatario_email'] ?? $notif['destinatario_telefono'] ?? ''),
                    'ERROR',
                    'NO_HANDLER',
                    "No hay handler registrado para canal {$canal}",
                    0,
                );
                $this->repo->markFailed((string) $notif['id'], "Canal {$canal} no soportado", $intentos, (int) $notif['max_intentos']);
                $fallidas++;
                continue;
            }

            $result = $handler->send($notif);

            $destinatario = $canal === CanalNotificacion::EMAIL->value
                ? (string) ($notif['destinatario_email'] ?? '')
                : (string) ($notif['destinatario_telefono'] ?? '');

            $this->repo->logAttempt(
                (string) $notif['id'],
                $canal,
                $destinatario,
                $result->success ? 'OK' : 'ERROR',
                $result->codigo,
                $result->mensaje,
                $result->duracionMs,
            );

            if ($result->success) {
                $this->repo->markSent((string) $notif['id']);
                $this->audit->log(
                    'notificacion.enviada',
                    'SYSTEM',
                    'notificacion',
                    (string) $notif['id'],
                    detalles: [
                        'canal' => $canal,
                        'destinatario' => $destinatario,
                        'duracion_ms' => $result->duracionMs,
                    ],
                    nivel: 'info',
                    ip: $ip,
                );
                $enviadas++;
            } else {
                $this->repo->markFailed((string) $notif['id'], $result->mensaje ?? 'unknown', $intentos, (int) $notif['max_intentos']);
                $this->audit->log(
                    'notificacion.fallida',
                    'SYSTEM',
                    'notificacion',
                    (string) $notif['id'],
                    detalles: [
                        'canal' => $canal,
                        'error' => $result->mensaje,
                        'intento' => $intentos,
                    ],
                    nivel: 'warning',
                    ip: $ip,
                );
                $fallidas++;
            }
        }

        return [
            'procesadas' => count($pendientes),
            'enviadas' => $enviadas,
            'fallidas' => $fallidas,
        ];
    }
}
