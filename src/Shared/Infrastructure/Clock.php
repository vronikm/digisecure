<?php

declare(strict_types=1);

namespace DigiSecure\Shared\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Reloj centralizado. En producción usa el reloj del sistema;
 * en tests se puede inyectar un momento fijo.
 */
final class Clock
{
    private static ?DateTimeImmutable $frozen = null;

    public static function now(): DateTimeImmutable
    {
        if (self::$frozen !== null) {
            return self::$frozen;
        }

        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
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
