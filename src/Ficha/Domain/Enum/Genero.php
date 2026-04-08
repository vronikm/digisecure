<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Domain\Enum;

enum Genero: string
{
    case MASCULINO = 'MASCULINO';
    case FEMENINO = 'FEMENINO';
    case OTRO = 'OTRO';
    case PREFIERO_NO_DECIR = 'PREFIERO_NO_DECIR';

    public function label(): string
    {
        return match ($this) {
            self::MASCULINO => 'Masculino',
            self::FEMENINO => 'Femenino',
            self::OTRO => 'Otro',
            self::PREFIERO_NO_DECIR => 'Prefiero no decir',
        };
    }

    /**
     * Estatura mínima en metros según género.
     * Decisión #12: MASCULINO=1.65, FEMENINO=1.50.
     * Para OTRO/PREFIERO_NO_DECIR se retorna null (requiere confirmación manual).
     */
    public function estaturaMinima(): ?float
    {
        return match ($this) {
            self::MASCULINO => 1.65,
            self::FEMENINO => 1.50,
            self::OTRO, self::PREFIERO_NO_DECIR => null,
        };
    }
}
