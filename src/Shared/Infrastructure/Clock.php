<?php

declare(strict_types=1);

namespace DigiSecure\Shared\Infrastructure;

use DateTimeImmutable;

/**
 * Reloj centralizado. En producción usa el reloj del sistema en la zona horaria
 * configurada de la aplicación (APP_TIMEZONE = America/Guayaquil), de modo que
 * los registros de fecha/hora en la base de datos quedan en hora local de
 * Ecuador (UTC-5, sin horario de verano). En tests se inyecta un momento fijo.
 */
final class Clock
{
    private static ?DateTimeImmutable $frozen = null;

    public static function now(): DateTimeImmutable
    {
        if (self::$frozen !== null) {
            return self::$frozen;
        }

        // Sin zona explícita: usa la zona por defecto fijada en el bootstrap
        // (date_default_timezone_set con APP_TIMEZONE).
        return new DateTimeImmutable('now');
    }

    public static function nowString(): string
    {
        return self::now()->format('Y-m-d H:i:s.u');
    }

    /** Solo para tests. */
    public static function freeze(DateTimeImmutable $at): void
    {
        self::$frozen = $at;
    }

    /** Solo para tests. */
    public static function unfreeze(): void
    {
        self::$frozen = null;
    }
}
