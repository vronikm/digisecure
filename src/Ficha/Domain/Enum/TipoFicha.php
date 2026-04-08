<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Domain\Enum;

enum TipoFicha: string
{
    case INGRESO = 'INGRESO';
    case REINGRESO = 'REINGRESO';
    case ACTUALIZACION = 'ACTUALIZACION';

    public function label(): string
    {
        return match ($this) {
            self::INGRESO => 'Ingreso',
            self::REINGRESO => 'Reingreso',
            self::ACTUALIZACION => 'Actualización',
        };
    }
}
