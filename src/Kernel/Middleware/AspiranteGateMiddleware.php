<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Security\Auth\AuthenticatedUser;

/**
 * Confina al usuario con rol 'aspirante' a completar SOLO su ficha de guardia.
 *
 * Un aspirante (guardia que validó su correo) puede iniciar sesión, pero su
 * único objetivo es completar su registro en /guardias/crear. Cualquier otra
 * ruta lo redirige de vuelta a esa vista.
 *
 * Debe ejecutarse DESPUES de AuthenticationMiddleware (necesita 'auth_user').
 */
final class AspiranteGateMiddleware implements MiddlewareInterface
{
    private const DESTINO = '/guardias/crear';

    /** Rutas exactas permitidas al aspirante restringido. */
    private const PERMITIDAS = [
        '/guardias/crear',
        '/guardias/prefill/cedula',
        '/logout',
    ];

    /** Prefijos permitidos (recursos estáticos, etc.). */
    private const PREFIJOS = [
        '/assets/',
    ];

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $user = $request->attribute('auth_user');

        if (!$user instanceof AuthenticatedUser || !$user->tieneRol('aspirante')) {
            return $next->handle($request);
        }

        if ($this->permitida($request->path)) {
            return $next->handle($request);
        }

        return Response::redirect(self::DESTINO);
    }

    private function permitida(string $path): bool
    {
        if (in_array($path, self::PERMITIDAS, true)) {
            return true;
        }

        foreach (self::PREFIJOS as $prefijo) {
            if (str_starts_with($path, $prefijo)) {
                return true;
            }
        }

        return false;
    }
}
