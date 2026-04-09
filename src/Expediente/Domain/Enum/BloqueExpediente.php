<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Domain\Enum;

enum BloqueExpediente: string
{
    case A = 'A';
    case B = 'B';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Bloque A — Admisibilidad (pre-empleo)',
            self::B => 'Bloque B — Vinculación laboral (post-contratación)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::A => 'Bloque A',
            self::B => 'Bloque B',
        };
    }
}
