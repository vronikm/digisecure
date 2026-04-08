<?php

declare(strict_types=1);

namespace DigiSecure\Shared\Infrastructure;

/**
 * Generador de ULID (Universally Unique Lexicographically Sortable Identifier).
 *
 * Formato: 26 caracteres Crockford Base32 (10 timestamp + 16 random).
 * Compatible con CHAR(26) en MySQL. Ordenable por tiempo (mejora B-Tree).
 * No requiere dependencias externas.
 */
final class IdGenerator
{
    private const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function ulid(): string
    {
        $time = (int)(microtime(true) * 1000);
        $timeChars = '';

        for ($i = 9; $i >= 0; $i--) {
            $mod = $time % 32;
            $timeChars = self::ENCODING[$mod] . $timeChars;
            $time = intdiv($time, 32);
        }

        $randomBytes = random_bytes(10);
        $randomChars = '';

        for ($i = 0; $i < 10; $i++) {
            $byte = ord($randomBytes[$i]);
            // Cada byte genera ~1.6 chars; usamos pares de bytes para 16 chars random
            $randomChars .= self::ENCODING[$byte >> 3];
            if (strlen($randomChars) < 16) {
                $randomChars .= self::ENCODING[$byte & 0x1F];
            }
        }

        // Asegurar exactamente 16 chars random
        $randomChars = substr($randomChars, 0, 16);

        return $timeChars . $randomChars;
    }

    /**
     * Valida que un string tenga formato ULID válido.
     */
    public static function isValid(string $id): bool
    {
        if (strlen($id) !== 26) {
            return false;
        }

        // Crockford Base32: 0-9, A-H, J-K, M-N, P-T, V-W, X-Y, Z (sin I, L, O, U)
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id) === 1;
    }
}
