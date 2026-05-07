<?php

declare(strict_types=1);

namespace DigiSecure\Puesto\Domain\Enum;

enum TipoPuesto: string
{
    case OFICINA     = 'oficina';
    case PLANTA      = 'planta';
    case RESIDENCIAL = 'residencial';
    case COMERCIAL   = 'comercial';
    case EVENTO      = 'evento';
    case OTRO        = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::OFICINA     => 'Oficina',
            self::PLANTA      => 'Planta industrial',
            self::RESIDENCIAL => 'Residencial',
            self::COMERCIAL   => 'Comercial',
            self::EVENTO      => 'Evento puntual',
            self::OTRO        => 'Otro',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OFICINA     => 'bi-building',
            self::PLANTA      => 'bi-gear-wide-connected',
            self::RESIDENCIAL => 'bi-houses',
            self::COMERCIAL   => 'bi-shop',
            self::EVENTO      => 'bi-calendar-event',
            self::OTRO        => 'bi-geo-alt',
        };
    }
}
