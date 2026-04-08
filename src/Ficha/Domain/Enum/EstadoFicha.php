<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Domain\Enum;

enum EstadoFicha: string
{
    case BORRADOR = 'BORRADOR';
    case EN_REVISION = 'EN_REVISION';
    case COMPLETA = 'COMPLETA';
    case ANULADA = 'ANULADA';

    public function label(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::EN_REVISION => 'En revisión',
            self::COMPLETA => 'Completa',
            self::ANULADA => 'Anulada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::BORRADOR => 'bg-secondary',
            self::EN_REVISION => 'bg-warning text-dark',
            self::COMPLETA => 'bg-success',
            self::ANULADA => 'bg-danger',
        };
    }
}
