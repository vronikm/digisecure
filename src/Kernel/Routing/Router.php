<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Routing;

use DigiSecure\Kernel\Http\Request;

/**
 * Router minimalista pero estricto.
 *
 * - Solo acepta rutas registradas explícitamente.
 * - Sin auto-discovery de archivos (Regla #11 de seguridad).
 * - Diferencia 404 (no match path) de 405 (path existe, método no permitido).
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    public function get(string $path, array|\Closure $handler, array $middleware = [], ?string $name = null, ?string $permission = null): Route
    {
        return $this->add(new Route('GET', $path, $handler, $middleware, $name, $permission));
    }

    public function post(string $path, array|\Closure $handler, array $middleware = [], ?string $name = null, ?string $permission = null): Route
    {
        return $this->add(new Route('POST', $path, $handler, $middleware, $name, $permission));
    }

    public function put(string $path, array|\Closure $handler, array $middleware = [], ?string $name = null, ?string $permission = null): Route
    {
        return $this->add(new Route('PUT', $path, $handler, $middleware, $name, $permission));
    }

    public function patch(string $path, array|\Closure $handler, array $middleware = [], ?string $name = null, ?string $permission = null): Route
    {
        return $this->add(new Route('PATCH', $path, $handler, $middleware, $name, $permission));
    }

    public function delete(string $path, array|\Closure $handler, array $middleware = [], ?string $name = null, ?string $permission = null): Route
    {
        return $this->add(new Route('DELETE', $path, $handler, $middleware, $name, $permission));
    }

    private function add(Route $route): Route
    {
        $this->routes[] = $route;
        return $route;
    }

    /**
     * Resuelve una Request a una RouteMatch.
     *
     * @throws RouteNotFoundException si ningún path coincide
     * @throws MethodNotAllowedException si el path coincide pero no el método
     */
    public function match(Request $request): RouteMatch
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $params = $route->match($request->method, $request->path);
            if ($params !== null) {
                return new RouteMatch($route, $params);
            }

            // Detectar si el path coincide con otra ruta de método distinto
            // (para devolver 405 en vez de 404).
            $altRoute = new Route($request->method, $route->path, $route->handler);
            if ($altRoute->match($request->method, $request->path) !== null) {
                // No debería pasar (ya lo habríamos pillado arriba),
                // pero por completitud.
                continue;
            }
            // Probar si el path coincide con cualquier método
            $sameRoute = new Route($route->method, $route->path, $route->handler);
            if ($sameRoute->match($route->method, $request->path) !== null) {
                $allowedMethods[] = $route->method;
            }
        }

        if ($allowedMethods !== []) {
            throw new MethodNotAllowedException(
                "Method {$request->method} not allowed for {$request->path}",
                array_values(array_unique($allowedMethods)),
            );
        }

        throw new RouteNotFoundException("No route matched {$request->method} {$request->path}");
    }

    /**
     * @return list<Route>
     */
    public function routes(): array
    {
        return $this->routes;
    }
}
