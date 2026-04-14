<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Routing;

/**
 * Una ruta registrada en el Router.
 *
 * `path` puede contener parámetros con sintaxis `{nombre}` o `{nombre:regex}`.
 * Ejemplo: /fichas/{id} o /fichas/{id:\d+}
 *
 * `handler` es un callable o un array [ControllerClass::class, 'method'].
 * El Router resolverá el ControllerClass vía el Container.
 *
 * `middleware` es una lista de FQCN de middlewares específicos de esta ruta,
 * que se ejecutan después del pipeline global.
 */
final class Route
{
    private readonly string $regex;
    /** @var list<string> */
    private readonly array $paramNames;

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<class-string> $middleware
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array|\Closure $handler,
        public readonly array $middleware = [],
        public readonly ?string $name = null,
        public readonly ?string $permission = null,
    ) {
        [$this->regex, $this->paramNames] = $this->compile($path);
    }

    /**
     * Convierte un path con parámetros a una regex y extrae los nombres.
     *
     * /fichas/{id:\d+}/docs/{docId}
     *   →  #^/fichas/(\d+)/docs/([^/]+)$#
     *   →  ['id', 'docId']
     *
     * @return array{0: string, 1: list<string>}
     */
    private function compile(string $path): array
    {
        $paramNames = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            static function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];
                $pattern = $matches[2] ?? '[^/]+';
                return '(' . $pattern . ')';
            },
            $path,
        );

        return ['#^' . $regex . '$#', $paramNames];
    }

    /**
     * Intenta hacer match contra un path. Si coincide, devuelve los parámetros.
     * Si no, devuelve null.
     *
     * @return array<string, string>|null
     */
    public function match(string $method, string $path): ?array
    {
        if ($method !== $this->method) {
            return null;
        }

        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($this->paramNames as $i => $name) {
            $params[$name] = $matches[$i + 1];
        }
        return $params;
    }
}
