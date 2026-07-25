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
            self::A => 'Documentos de Admisibilidad',
            self::B => 'Documentos de Vinculación Laboral (post-contratación)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::A => 'Documentos de Admisibilidad',
            self::B => 'Documentos de Vinculación Laboral',
        };
    }
}
