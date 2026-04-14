<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Routing;

use DigiSecure\Kernel\Container\Container;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\Middleware\RequestHandlerInterface;

/**
 * Handler final del pipeline: resuelve la ruta y ejecuta el handler.
 *
 * Se invoca después de todo el middleware global. Si la ruta tiene
 * middleware específico, se ejecuta aquí (anidado).
 */
final class RouteDispatcher implements RequestHandlerInterface
{
    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
    ) {
    }

    public function handle(Request $request): Response
    {
        // Reutilizar el match si RouteResolverMiddleware ya lo resolvió
        $match = $request->attribute('_resolved_route');
        if ($match === null) {
            $match = $this->router->match($request);
            $request = $request->withAttribute('route', $match->route);
            foreach ($match->params as $name => $value) {
                $request = $request->withAttribute("route.{$name}", $value);
            }
            $request = $request->withAttribute('route.params', $match->params);
        }

        // Si la ruta tiene middlewares propios, encadenarlos
        if ($match->route->middleware !== []) {
            $innerPipeline = new \DigiSecure\Kernel\Middleware\Pipeline(
                $this->container,
                $match->route->middleware,
                new RouteHandlerInvoker($match->route->handler, $match->params, $this->container),
            );
            return $innerPipeline->handle($request);
        }

        return (new RouteHandlerInvoker($match->route->handler, $match->params, $this->container))
            ->handle($request);
    }
}
