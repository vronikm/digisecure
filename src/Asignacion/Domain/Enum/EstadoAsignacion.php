<?php

declare(strict_types=1);

namespace DigiSecure\Asignacion\Domain\Enum;

enum EstadoAsignacion: string
{
    case ACTIVA      = 'activa';
    case FINALIZADA  = 'finalizada';
    case CANCELADA   = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVA     => 'Activa',
            self::FINALIZADA => 'Finalizada',
            self::CANCELADA  => 'Cancelada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ACTIVA     => 'tone-success',
            self::FINALIZADA => 'tone-muted',
            self::CANCELADA  => 'tone-danger',
        };
    }
}
