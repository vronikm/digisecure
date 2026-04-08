<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Routing;

use DigiSecure\Kernel\Container\Container;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\Middleware\RequestHandlerInterface;

/**
 * Adaptador que ejecuta el handler de una ruta (callable o [Class, 'method']).
 *
 * Resuelve el controlador vía Container y le inyecta:
 * - El Request como primer argumento (si lo declara con type hint Request)
 * - Los parámetros de ruta por nombre
 * - Cualquier dependencia tipada por constructor del controlador
 */
final class RouteHandlerInvoker implements RequestHandlerInterface
{
    /**
     * @param array{0: class-string, 1: string}|\Closure $handler
     * @param array<string, string> $params
     */
    public function __construct(
        private readonly array|\Closure $handler,
        private readonly array $params,
        private readonly Container $container,
    ) {
    }

    public function handle(Request $request): Response
    {
        $extra = ['request' => $request, ...$this->params];
        $result = $this->container->call($this->handler, $extra);

        if (!$result instanceof Response) {
            throw new \RuntimeException(
                'El handler de la ruta debe devolver una instancia de DigiSecure\Kernel\Http\Response.'
            );
        }

        return $result;
    }
}
