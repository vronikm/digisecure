<?php

declare(strict_types=1);

namespace DigiSecure\Catalogo\Domain;

/**
 * Tipo de institución financiera. Define también el orden de agrupación:
 * primero bancos, luego cooperativas.
 */
enum TipoInstitucion: string
{
    case BANCO = 'BANCO';
    case COOPERATIVA = 'COOPERATIVA';

    public function label(): string
    {
        return match ($this) {
            self::BANCO => 'Banco',
            self::COOPERATIVA => 'Cooperativa',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::BANCO => 'Bancos',
            self::COOPERATIVA => 'Cooperativas',
        };
    }
}
