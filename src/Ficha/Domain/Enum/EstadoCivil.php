<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Domain\Enum;

enum EstadoCivil: string
{
    case SOLTERO = 'SOLTERO';
    case CASADO = 'CASADO';
    case UNION_LIBRE = 'UNION_LIBRE';
    case DIVORCIADO = 'DIVORCIADO';
    case VIUDO = 'VIUDO';

    public function label(): string
    {
        return match ($this) {
            self::SOLTERO => 'Soltero/a',
            self::CASADO => 'Casado/a',
            self::UNION_LIBRE => 'Unión libre',
            self::DIVORCIADO => 'Divorciado/a',
            self::VIUDO => 'Viudo/a',
        };
    }
}
