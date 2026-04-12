<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Domain\Enum;

enum EstadoNotificacion: string
{
    case PENDIENTE = 'PENDIENTE';
    case ENVIADA = 'ENVIADA';
    case FALLIDA = 'FALLIDA';
    case CANCELADA = 'CANCELADA';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::ENVIADA => 'Enviada',
            self::FALLIDA => 'Fallida',
            self::CANCELADA => 'Cancelada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDIENTE => 'bg-secondary',
            self::ENVIADA => 'bg-success',
            self::FALLIDA => 'bg-danger',
            self::CANCELADA => 'bg-warning text-dark',
        };
    }
}
