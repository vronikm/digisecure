<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Middleware que establece el TenantContext a partir del usuario autenticado.
 *
 * Debe ejecutarse DESPUÉS de AuthenticationMiddleware (que inyecta el auth_user
 * en el request). Si hay usuario autenticado, establece el tenant_id en el
 * TenantContext para que BaseRepository filtre automáticamente.
 *
 * Si el tenant está suspendido o cancelado, cortocircuita con error 403.
 */
final class TenantContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        /** @var AuthenticatedUser|null $user */
        $user = $request->attribute('auth_user');

        if ($user === null) {
            // Sin usuario autenticado, no hay tenant que establecer.
            // Las rutas públicas no necesitan contexto de tenant.
            return $next->handle($request);
        }

        // Verificar que el tenant esté activo.
        // El superadmin de sistema no se bloquea aquí para conservar acceso global.
        if ($user->tenantEstado !== 'activo' && !$user->esSuperAdmin()) {
            return Response::html(
                '<h1>Cuenta suspendida</h1><p>La cuenta de tu empresa está suspendida. Contacta a soporte.</p>',
                403,
            );
        }

        // Establecer el contexto de tenant (solo se puede hacer una vez por request)
        if (!$this->tenantContext->isSet()) {
            $this->tenantContext->set(
                $user->tenantId,
                $user->tenantNombre,
                $user->tenantEstado,
            );
        }

        return $next->handle($request);
    }
}
