<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Audit\AuditLogger;

/**
 * Middleware de autorización basado en permisos de ruta.
 *
 * Verifica que el usuario autenticado tenga los permisos necesarios
 * para acceder a la ruta solicitada. Los permisos se definen como
 * atributo de la ruta ('_route_permission').
 *
 * Si la ruta no tiene permiso asignado, pasa (rutas públicas o de uso general).
 * Si el usuario no tiene el permiso, devuelve 403.
 */
final class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ViewRenderer $viewRenderer,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $requiredPermission = $request->attribute('_route_permission');

        if ($requiredPermission === null) {
            return $next->handle($request);
        }

        /** @var AuthenticatedUser|null $user */
        $user = $request->attribute('auth_user');

        if ($user === null) {
            return Response::redirect('/login');
        }

        if (!$user->tienePermiso($requiredPermission)) {
            $this->audit->accesoProhibido($user->id, $requiredPermission, $request->clientIp());

            if ($request->isAjax()) {
                return Response::json(['error' => 'Acceso denegado'], 403);
            }

            return $this->viewRenderer->response('errors/403', [
                'permiso' => $requiredPermission,
                'userName' => $user->nombreCompleto(),
            ], 403);
        }

        return $next->handle($request);
    }
}
