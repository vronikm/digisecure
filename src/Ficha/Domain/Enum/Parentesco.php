<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Domain\Enum;

enum Parentesco: string
{
    case PADRE = 'PADRE';
    case MADRE = 'MADRE';
    case HERMANO = 'HERMANO';
    case OTRO = 'OTRO';

    public function label(): string
    {
        return match ($this) {
            self::PADRE => 'Padre',
            self::MADRE => 'Madre',
            self::HERMANO => 'Hermano/a',
            self::OTRO => 'Otro',
        };
    }
}
