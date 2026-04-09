<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Domain\Enum;

enum EstadoDocumento: string
{
    case PENDIENTE = 'PENDIENTE';
    case EN_REVISION = 'EN_REVISION';
    case ACEPTADO = 'ACEPTADO';
    case RECHAZADO = 'RECHAZADO';
    case VENCIDO = 'VENCIDO';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::EN_REVISION => 'En revisión',
            self::ACEPTADO => 'Aceptado',
            self::RECHAZADO => 'Rechazado',
            self::VENCIDO => 'Vencido',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDIENTE => 'bg-secondary',
            self::EN_REVISION => 'bg-warning text-dark',
            self::ACEPTADO => 'bg-success',
            self::RECHAZADO => 'bg-danger',
            self::VENCIDO => 'bg-dark',
        };
    }

    public function iconClass(): string
    {
        return match ($this) {
            self::PENDIENTE => 'bi-clock',
            self::EN_REVISION => 'bi-search',
            self::ACEPTADO => 'bi-check-circle-fill',
            self::RECHAZADO => 'bi-x-circle-fill',
            self::VENCIDO => 'bi-calendar-x',
        };
    }
}
