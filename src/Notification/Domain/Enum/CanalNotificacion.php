<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Domain\Enum;

/**
 * Canales de entrega soportados por el sistema de notificaciones.
 *
 * EMAIL y WHATSAPP ya cuentan con channel handlers.
 * SMS y PUSH quedan como extensiones futuras.
 */
enum CanalNotificacion: string
{
    case EMAIL = 'EMAIL';
    case SMS = 'SMS';
    case WHATSAPP = 'WHATSAPP';
    case PUSH = 'PUSH';

    public function label(): string
    {
        return match ($this) {
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::WHATSAPP => 'WhatsApp',
            self::PUSH => 'Push',
        };
    }
}
