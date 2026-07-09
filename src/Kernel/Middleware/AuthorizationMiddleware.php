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
            if ($this->permiteFlujoAspirante($user, (string) $requiredPermission, $request->path)) {
                return $next->handle($request);
            }

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

    private function permiteFlujoAspirante(AuthenticatedUser $user, string $requiredPermission, string $path): bool
    {
        if (!$user->tieneRol('aspirante')) {
            return false;
        }

        return match ($requiredPermission) {
            'guardias.crear' => \in_array($path, ['/guardias/crear', '/guardias/prefill/cedula'], true),
            'guardias.ver' => $path === '/guardias'
                || \preg_match('#^/guardias/[A-Za-z0-9_-]+$#', $path) === 1
                || \preg_match('#^/guardias/[A-Za-z0-9_-]+/(croquis|foto)$#', $path) === 1,
            'guardias.editar' => \preg_match('#^/guardias/[A-Za-z0-9_-]+/editar$#', $path) === 1,
            'expedientes.ver' => \preg_match('#^/guardias/[A-Za-z0-9_-]+/expediente$#', $path) === 1
                || \preg_match('#^/expedientes/documento/[A-Za-z0-9_-]+$#', $path) === 1
                || \preg_match('#^/expedientes/documento/[A-Za-z0-9_-]+/descargar$#', $path) === 1,
            'expedientes.cargar' => \preg_match('#^/expedientes/[A-Za-z0-9_-]+/documento/[A-Za-z0-9_-]+$#', $path) === 1,
            default => false,
        };
    }
}
