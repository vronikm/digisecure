<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Domain\Enum;

enum PrioridadNotificacion: string
{
    case BAJA = 'BAJA';
    case NORMAL = 'NORMAL';
    case ALTA = 'ALTA';
    case URGENTE = 'URGENTE';

    public function label(): string
    {
        return match ($this) {
            self::BAJA => 'Baja',
            self::NORMAL => 'Normal',
            self::ALTA => 'Alta',
            self::URGENTE => 'Urgente',
        };
    }
}
