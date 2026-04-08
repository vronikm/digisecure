<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Config;

/**
 * Cargador minimalista de archivos .env (formato KEY=VALUE).
 *
 * NO usa vlucas/phpdotenv para mantener "vanilla". Soporta:
 *   - comentarios con # al inicio o tras espacio
 *   - valores entre comillas simples o dobles
 *   - escape de \n, \t, \" en comillas dobles
 *   - claves duplicadas (la última gana)
 *
 * NO soporta: interpolación ${VAR}, multilineas, herencia de archivos.
 * Si en el futuro necesitamos eso, evaluamos.
 */
final class EnvLoader
{
    /**
     * @return array<string, string>
     */
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $vars = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Strip inline comment if value is unquoted
            if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
                $hashPos = strpos($value, ' #');
                if ($hashPos !== false) {
                    $value = trim(substr($value, 0, $hashPos));
                }
            }

            $vars[$key] = self::parseValue($value);
        }

        return $vars;
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // Comillas dobles: procesar escapes
        if (str_starts_with($value, '"') && str_ends_with($value, '"') && strlen($value) >= 2) {
            $inner = substr($value, 1, -1);
            return strtr($inner, [
                '\\n' => "\n",
                '\\t' => "\t",
                '\\"' => '"',
                '\\\\' => '\\',
            ]);
        }

        // Comillas simples: literal
        if (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) >= 2) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
