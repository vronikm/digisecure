<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Audit\AuditLogger;

/**
 * Middleware de autorización basado en permisos de ruta.
 *
 * Verifica que el usuario autenticado tenga los permisos necesarios
 * para acceder a la ruta solicitada. Los permisos se definen como
 * atributo de la ruta ('permission').
 *
 * Si la ruta no tiene permiso asignado, pasa (rutas públicas o de uso general).
 * Si el usuario no tiene el permiso, devuelve 403.
 */
final class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        // Si la ruta tiene un permiso requerido, verificarlo
        $requiredPermission = $request->attribute('_route_permission');

        if ($requiredPermission === null) {
            // Ruta sin permiso específico → pasa
            return $next->handle($request);
        }

        /** @var AuthenticatedUser|null $user */
        $user = $request->attribute('auth_user');

        if ($user === null) {
            // Sin usuario, debería haber sido atrapado por AuthenticationMiddleware
            return Response::redirect('/login');
        }

        // Verificar permiso
        if (!$user->tienePermiso($requiredPermission)) {
            $this->audit->accesoProhibido($user->id, $requiredPermission, $request->clientIp());

            if ($request->isAjax()) {
                return Response::json(['error' => 'Acceso denegado'], 403);
            }

            return Response::html(
                $this->render403($user, $requiredPermission),
                403,
            );
        }

        return $next->handle($request);
    }

    private function render403(AuthenticatedUser $user, string $permiso): string
    {
        $nombre = htmlspecialchars($user->nombreCompleto(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $permisoEsc = htmlspecialchars($permiso, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<HTML
        <!doctype html>
        <html lang="es">
        <head><meta charset="utf-8"><title>403 - Acceso denegado</title></head>
        <body>
            <h1>403 — Acceso denegado</h1>
            <p>No tienes permiso para acceder a esta sección.</p>
            <p><small>Usuario: {$nombre} | Permiso requerido: {$permisoEsc}</small></p>
            <p><a href="/dashboard">Volver al inicio</a></p>
        </body>
        </html>
        HTML;
    }
}
