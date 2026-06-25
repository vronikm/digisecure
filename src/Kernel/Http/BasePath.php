<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Http;

/**
 * Detecta y gestiona el base path de la aplicación.
 *
 * Cuando la app vive en un subdirectorio (ej: /digisecure/public/),
 * el base path se detecta automáticamente desde SCRIPT_NAME y se usa para:
 *  1. Stripear el prefijo del Request->path (router ve rutas limpias)
 *  2. Preponerlo a todas las URLs generadas (redirects, links)
 *
 * Si la app corre en la raíz del dominio, basePath es '' (cadena vacía).
 */
final class BasePath
{
    private static string $basePath = '';

    /**
     * Detecta el base path desde SCRIPT_NAME.
     * Ej: SCRIPT_NAME=/digisecure/public/index.php → basePath=/digisecure/public
     */
    public static function detect(): void
    {
        $scriptName = self::pathOnly((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $requestPath = self::pathOnly((string)($_SERVER['REQUEST_URI'] ?? '/'));
        $dir = str_replace('\\', '/', dirname($scriptName));
        $basePath = $dir === '/' || $dir === '\\' ? '' : rtrim($dir, '/');

        if ($basePath === '') {
            self::$basePath = '';
            return;
        }

        // En hosting compartido es comun apuntar el dominio a la raiz del
        // proyecto y reescribir internamente hacia public/index.php. En ese
        // caso /public no debe aparecer en links, formularios ni redirects.
        if (str_ends_with($basePath, '/public')) {
            $withoutPublic = substr($basePath, 0, -7) ?: '';
            $requestUsesPublic = $requestPath === $basePath || str_starts_with($requestPath, $basePath . '/');
            $requestUsesParent = $withoutPublic === ''
                || $requestPath === $withoutPublic
                || str_starts_with($requestPath, $withoutPublic . '/');

            if (!$requestUsesPublic && $requestUsesParent) {
                $basePath = $withoutPublic;
            }
        }

        self::$basePath = $basePath;
    }

    /**
     * Establece el base path manualmente (útil para tests).
     */
    public static function set(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');
    }

    /**
     * Retorna el base path detectado (sin trailing slash).
     * Ej: '/digisecure/public' o '' si es raíz.
     */
    public static function get(): string
    {
        return self::$basePath;
    }

    /**
     * Stripea el base path de una URI para obtener la ruta de la app.
     * Ej: /digisecure/public/login → /login
     */
    public static function stripFromPath(string $path): string
    {
        if (self::$basePath !== '' && str_starts_with($path, self::$basePath)) {
            $stripped = substr($path, strlen(self::$basePath));
            return $stripped === '' || $stripped === false ? '/' : $stripped;
        }
        return $path;
    }

    /**
     * Prepone el base path a una ruta de la app.
     * Ej: /login → /digisecure/public/login
     *
     * Solo prepone si la ruta empieza con / y no es una URL completa.
     */
    public static function prepend(string $path): string
    {
        if (self::$basePath === '') {
            return $path;
        }

        // No modificar URLs absolutas
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Solo preponer a rutas que empiezan con /
        if (str_starts_with($path, '/')) {
            return self::$basePath . $path;
        }

        return $path;
    }

    private static function pathOnly(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        return '/' . ltrim($path, '/');
    }
}
