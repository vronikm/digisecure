<?php

declare(strict_types=1);

namespace DigiSecure\Puesto\Domain\Enum;

enum ModalidadPuesto: string
{
    case FIJO      = 'fijo';
    case ROTATIVO  = 'rotativo';
    case EVENTUAL  = 'eventual';

    public function label(): string
    {
        return match ($this) {
            self::FIJO     => 'Fijo (24/7)',
            self::ROTATIVO => 'Rotativo (turnos)',
            self::EVENTUAL => 'Eventual (puntual)',
        };
    }
}
