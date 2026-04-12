<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Infrastructure\Channel;

/**
 * Resultado de un intento de envío en cualquier canal.
 */
final readonly class ChannelResult
{
    public function __construct(
        public bool $success,
        public ?string $codigo,
        public ?string $mensaje,
        public int $duracionMs,
    ) {
    }

    public static function ok(?string $codigo = null, ?string $mensaje = null, int $duracionMs = 0): self
    {
        return new self(true, $codigo, $mensaje, $duracionMs);
    }

    public static function fail(string $mensaje, ?string $codigo = null, int $duracionMs = 0): self
    {
        return new self(false, $codigo, $mensaje, $duracionMs);
    }
}

/**
 * Contrato común para canales de envío. Cada implementación recibe la fila
 * de notificación tal como está en BD y devuelve un ChannelResult.
 */
interface NotificationChannel
{
    /**
     * @param array<string, mixed> $notificacion
     */
    public function send(array $notificacion): ChannelResult;
}
