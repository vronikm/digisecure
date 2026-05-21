<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\Routing\MethodNotAllowedException;
use DigiSecure\Kernel\Routing\RouteNotFoundException;
use DigiSecure\Kernel\Routing\Router;
use DigiSecure\Kernel\View\ViewRenderer;

/**
 * Resuelve la ruta temprano en el pipeline para que:
 *   1. AuthorizationMiddleware pueda leer `_route_permission`.
 *   2. RouteDispatcher reutilice el match sin resolver dos veces.
 *
 * Debe ejecutarse ANTES de AuthorizationMiddleware.
 */
final class RouteResolverMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Router $router,
        private readonly ViewRenderer $view,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        try {
            $match = $this->router->match($request);
        } catch (RouteNotFoundException) {
            return $this->view->response('errors/404', [], 404);
        } catch (MethodNotAllowedException $e) {
            return Response::json(
                ['error' => 'Método no permitido', 'allowed' => $e->allowedMethods],
                405,
            );
        }

        $request = $request->withAttribute('_resolved_route', $match);
        $request = $request->withAttribute('route', $match->route);
        if ($match->route->name !== null && $match->route->name !== '') {
            $request = $request->withAttribute('_route_name', $match->route->name);
        }
        foreach ($match->params as $name => $value) {
            $request = $request->withAttribute("route.{$name}", $value);
        }
        $request = $request->withAttribute('route.params', $match->params);

        if ($match->route->permission !== null) {
            $request = $request->withAttribute('_route_permission', $match->route->permission);
        }

        return $next->handle($request);
    }
}
