<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Domain\Enum;

enum EstadoExpediente: string
{
    case INCOMPLETO = 'INCOMPLETO';
    case BLOQUE_A_COMPLETO = 'BLOQUE_A_COMPLETO';
    case COMPLETO = 'COMPLETO';

    public function label(): string
    {
        return match ($this) {
            self::INCOMPLETO => 'Incompleto',
            self::BLOQUE_A_COMPLETO => 'Bloque A completo',
            self::COMPLETO => 'Completo',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::INCOMPLETO => 'bg-secondary',
            self::BLOQUE_A_COMPLETO => 'bg-info',
            self::COMPLETO => 'bg-success',
        };
    }
}
