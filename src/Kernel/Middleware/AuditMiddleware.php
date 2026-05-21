<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\Routing\Route;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Audit\AuditLogger;

/**
 * Middleware que registra acciones HTTP sensibles en la auditoría.
 *
 * Solo loguea operaciones de escritura (POST, PUT, DELETE, PATCH).
 * Las lecturas (GET) no se auditan a nivel HTTP (se auditan en el dominio
 * cuando corresponde, ej: acceso a datos personales para LOPDP).
 */
final class AuditMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $response = $next->handle($request);

        // Solo auditar mutaciones
        if (!in_array($request->method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return $response;
        }

        /** @var AuthenticatedUser|null $user */
        $user = $request->attribute('auth_user');

        // No auditar requests sin usuario (login se audita desde Authenticator)
        if ($user === null) {
            return $response;
        }

        $routeName = $request->attribute('_route_name');
        if (!is_string($routeName) || $routeName === '') {
            $route = $request->attribute('route');
            if ($route instanceof Route) {
                $routeName = $route->name ?? $route->path;
            } else {
                $routeName = $request->path;
            }
        }

        $this->audit->log(
            evento: "http.{$request->method}.{$routeName}",
            usuarioId: $user->id,
            detalles: [
                'method' => $request->method,
                'path' => $request->path,
                'status' => $response->status,
            ],
            ip: $request->clientIp(),
            userAgent: $request->userAgent(),
        );

        return $response;
    }
}
