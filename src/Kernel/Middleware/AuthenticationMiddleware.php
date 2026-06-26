<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Auth\Authenticator;

/**
 * Middleware de autenticación.
 *
 * Si hay sesión activa con usuario autenticado:
 *  - Hidrata el AuthenticatedUser desde la sesión
 *  - Lo inyecta como atributo del Request ('auth_user')
 *
 * Si no hay sesión o no hay usuario:
 *  - Rutas públicas pasan sin user
 *  - Rutas protegidas se redirigen a /login
 *
 * Las rutas públicas se definen aquí para mantener la seguridad centralizada.
 * DENY BY DEFAULT: si una ruta no está en la lista, requiere autenticación.
 */
final class AuthenticationMiddleware implements MiddlewareInterface
{
    /** Rutas que NO requieren autenticación */
    private const PUBLIC_ROUTES = [
        '/login',
        '/password/forgot',
        '/registro',
        '/aspirantes',
        '/verificar',
        '/healthcheck',
        '/_smoke/session',
        '/lopdp/consentimientos/PoliticaPrivacidad_TDP.pdf',
    ];

    /** Prefijos públicos (si la ruta empieza con estos) */
    private const PUBLIC_PREFIXES = [
        '/assets/',
        '/password/reset/',
        '/activar/',
    ];

    public function __construct(
        private readonly Authenticator $authenticator,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $user = $this->authenticator->currentUser();

        if ($user !== null) {
            $user = $this->authenticator->syncSessionUser($user);
            // Usuario autenticado: inyectar en request
            $request = $request->withAttribute('auth_user', $user);
            return $next->handle($request);
        }

        // Sin usuario: verificar si la ruta es pública
        if ($this->isPublicRoute($request->path)) {
            return $next->handle($request);
        }

        // Ruta protegida sin autenticación → redirect a login
        $returnTo = $request->path !== '/' ? '?return_to=' . urlencode($request->path) : '';
        return Response::redirect("/login{$returnTo}");
    }

    private function isPublicRoute(string $path): bool
    {
        // Ruta exacta
        if (in_array($path, self::PUBLIC_ROUTES, true)) {
            return true;
        }

        // Prefijos
        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        // Ruta raíz: redirigirá a dashboard o login según contexto
        return $path === '/';
    }
}
