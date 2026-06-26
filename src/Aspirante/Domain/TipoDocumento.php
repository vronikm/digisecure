<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Domain;

/**
 * Tipo de documento de identificacion de un aspirante.
 *
 * Backed enum (string) que coincide con la columna ENUM de la tabla
 * `aspirantes`. Centraliza el catalogo para que controlador, servicio,
 * vista y base de datos hablen el mismo idioma.
 */
enum TipoDocumento: string
{
    case CEDULA = 'cedula';
    case RUC = 'ruc';
    case PASAPORTE = 'pasaporte';

    public function label(): string
    {
        return match ($this) {
            self::CEDULA => 'Cédula',
            self::RUC => 'RUC',
            self::PASAPORTE => 'Pasaporte',
        };
    }

    /**
     * @return list<self>
     */
    public static function casos(): array
    {
        return [self::CEDULA, self::RUC, self::PASAPORTE];
    }
}
