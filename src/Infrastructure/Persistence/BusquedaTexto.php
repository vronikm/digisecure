<?php

declare(strict_types=1);

namespace DigiSecure\Infrastructure\Persistence;

/**
 * Condicion SQL de busqueda por texto, igual en todos los listados.
 *
 * El texto se divide en palabras y CADA palabra debe aparecer en alguna de
 * las columnas indicadas: "paladines nixon" encuentra apellido + nombre aunque
 * esten en columnas distintas. La collation utf8mb4_unicode_ci ignora tildes
 * y mayusculas. Los comodines % y _ que escriba el usuario se buscan
 * literalmente (no amplian la busqueda).
 *
 * Uso:
 *   [$sql, $params] = BusquedaTexto::condicion($filtros['q'] ?? null, ['c.razon_social', 'c.documento'], 'q');
 *   if ($sql !== null) { $where[] = $sql; $bindings += $params; }
 */
final class BusquedaTexto
{
    public const MAX_TERMINOS = 6;
    public const MAX_LONGITUD = 120;

    /**
     * @param list<string> $columnas Columnas (con alias de tabla si hace falta). Solo
     *                               nombres definidos en el codigo, nunca entrada del usuario.
     * @param string $prefijo        Prefijo de los parametros con nombre (unico en la consulta).
     * @return array{0: ?string, 1: array<string, string>} [condicion o null si no hay texto, parametros]
     */
    public static function condicion(?string $texto, array $columnas, string $prefijo = 'bq'): array
    {
        $terminos = self::terminos($texto);
        if ($terminos === [] || $columnas === []) {
            return [null, []];
        }

        $porTermino = [];
        $params = [];
        foreach ($terminos as $i => $termino) {
            $like = '%' . addcslashes($termino, '%_\\') . '%';
            $alternativas = [];
            foreach (array_values($columnas) as $j => $columna) {
                $nombre = "{$prefijo}{$i}_{$j}";
                $params[$nombre] = $like;
                $alternativas[] = "{$columna} LIKE :{$nombre}";
            }
            $porTermino[] = '(' . implode(' OR ', $alternativas) . ')';
        }

        return ['(' . implode(' AND ', $porTermino) . ')', $params];
    }

    /**
     * Palabras de busqueda (sin vacias, como maximo MAX_TERMINOS).
     *
     * @return list<string>
     */
    public static function terminos(?string $texto): array
    {
        $texto = mb_substr(trim((string) $texto), 0, self::MAX_LONGITUD);
        if ($texto === '') {
            return [];
        }
        $partes = preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice($partes, 0, self::MAX_TERMINOS);
    }
}
