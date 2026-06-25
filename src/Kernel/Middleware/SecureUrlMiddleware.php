<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Container\Container;
use DigiSecure\Kernel\Error\ErrorHandler;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\Routing\Route;
use DigiSecure\Kernel\Routing\RouteMatch;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Session\SessionManager;
use DigiSecure\Security\Url\SecureRouteCatalog;
use DigiSecure\Security\Url\SecureUrlTokenService;
use DigiSecure\Shared\Infrastructure\IdGenerator;

/**
 * Capa de protección de IDs en URL:
 *  1. Tokeniza links/forms sensibles al generar URL en vistas.
 *  2. Decodifica tokens de parámetros antes del controlador.
 *
 * Se ejecuta después de RouteResolver para poder leer nombre de ruta/parámetros.
 */
final class SecureUrlMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Container $container,
        private readonly ErrorHandler $errorHandler,
        private readonly ViewRenderer $view,
        private readonly SessionManager $session,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        // Evita fugas de estado entre requests (ViewRenderer es singleton).
        $this->view->setUrlTransformer(null);

        $passthrough = static fn(string $path): string => $path;
        $request = $request->withAttribute('secure_url', $passthrough);

        /** @var AuthenticatedUser|null $user */
        $user = $request->attribute('auth_user');
        if ($user === null) {
            return $next->handle($request);
        }

        $sessionId = $this->session->id();
        if ($sessionId === '') {
            return $next->handle($request);
        }

        try {
            $tokens = $this->container->get(SecureUrlTokenService::class);
        } catch (\Throwable $e) {
            $this->errorHandler->report($e);
            return $next->handle($request);
        }

        $secureUrl = function (string $path) use ($tokens, $user, $sessionId): string {
            try {
                return $tokens->tokenizePath($path, $user, $sessionId);
            } catch (\Throwable) {
                // Disponibilidad primero: si falla tokenización, mantener URL original.
                return $path;
            }
        };

        $this->view->setUrlTransformer($secureUrl);
        $request = $request->withAttribute('secure_url', $secureUrl);

        $decoded = $this->decodeRouteParams($request, $tokens, $user, $sessionId);
        if ($decoded instanceof Response) {
            return $decoded;
        }

        $decoded = $this->decodeQueryParams($decoded, $tokens, $user, $sessionId);
        return $next->handle($decoded);
    }

    /**
     * @return Request|Response
     */
    private function decodeRouteParams(
        Request $request,
        SecureUrlTokenService $tokens,
        AuthenticatedUser $user,
        string $sessionId,
    ): Request|Response {
        $route = $request->attribute('route');
        if (!$route instanceof Route) {
            return $request;
        }

        $scopes = SecureRouteCatalog::routeParamScopes($route->name);
        if ($scopes === []) {
            return $request;
        }

        /** @var RouteMatch|null $match */
        $match = $request->attribute('_resolved_route');
        $params = $match instanceof RouteMatch
            ? $match->params
            : (is_array($request->attribute('route.params')) ? $request->attribute('route.params') : []);

        if ($params === []) {
            return $request;
        }

        $changed = false;
        foreach ($scopes as $paramName => $scope) {
            if (!isset($params[$paramName])) {
                continue;
            }

            $value = (string)$params[$paramName];
            if ($value === '') {
                return $this->invalidTokenResponse();
            }

            // Rollout gradual: ULID plano se acepta temporalmente.
            if (IdGenerator::isValid($value)) {
                continue;
            }

            if (!$tokens->isToken($value)) {
                return $this->invalidTokenResponse();
            }

            try {
                $decodedId = $tokens->decodeResourceId(
                    $value,
                    $scope,
                    $paramName,
                    $user,
                    $sessionId,
                );
            } catch (\Throwable) {
                return $this->invalidTokenResponse();
            }

            if (!IdGenerator::isValid($decodedId)) {
                return $this->invalidTokenResponse();
            }

            $params[$paramName] = $decodedId;
            $changed = true;
        }

        if (!$changed) {
            return $request;
        }

        foreach ($params as $name => $value) {
            $request = $request->withAttribute("route.{$name}", $value);
        }
        $request = $request->withAttribute('route.params', $params);

        if ($match instanceof RouteMatch) {
            $request = $request->withAttribute('_resolved_route', new RouteMatch($match->route, $params));
        }

        return $request;
    }

    private function decodeQueryParams(
        Request $request,
        SecureUrlTokenService $tokens,
        AuthenticatedUser $user,
        string $sessionId,
    ): Request {
        $route = $request->attribute('route');
        if (!$route instanceof Route) {
            return $request;
        }

        $scopes = SecureRouteCatalog::routeQueryScopes($route->name);
        if ($scopes === []) {
            return $request;
        }

        foreach ($scopes as $paramName => $scope) {
            $rawValue = $request->query($paramName);

            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            if (!\is_string($rawValue)) {
                // Saneamiento: no aceptar arrays/objetos en filtros por ID.
                $request = $request->withAttribute("query.{$paramName}", null);
                continue;
            }

            if (IdGenerator::isValid($rawValue)) {
                $request = $request->withAttribute("query.{$paramName}", $rawValue);
                continue;
            }

            if (!$tokens->isToken($rawValue)) {
                $request = $request->withAttribute("query.{$paramName}", null);
                continue;
            }

            try {
                $decodedId = $tokens->decodeResourceId(
                    $rawValue,
                    $scope,
                    $paramName,
                    $user,
                    $sessionId,
                );
            } catch (\Throwable) {
                // No bloquear listados/filtros por token inválido en query.
                $request = $request->withAttribute("query.{$paramName}", null);
                continue;
            }

            $request = $request->withAttribute(
                "query.{$paramName}",
                IdGenerator::isValid($decodedId) ? $decodedId : null,
            );
        }

        return $request;
    }

    private function invalidTokenResponse(): Response
    {
        return $this->view->response('errors/404', [], 404);
    }
}
