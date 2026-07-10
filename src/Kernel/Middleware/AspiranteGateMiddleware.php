<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Security\Auth\AuthenticatedUser;

/**
 * Confina al usuario con rol 'aspirante' a su flujo propio:
 * crear/actualizar su ficha y cargar documentos del expediente.
 *
 * Debe ejecutarse DESPUES de AuthenticationMiddleware (necesita 'auth_user').
 */
final class AspiranteGateMiddleware implements MiddlewareInterface
{
    private const DESTINO = '/guardias';

    /** Rutas exactas permitidas al aspirante restringido. */
    private const PERMITIDAS = [
        '/guardias',
        '/guardias/crear',
        '/guardias/prefill/cedula',
        '/logout',
    ];

    /** Prefijos permitidos (recursos estáticos, etc.). */
    private const PREFIJOS = [
        '/assets/',
    ];

    /**
     * Rutas con IDs tokenizados o planos permitidas al aspirante. La propiedad
     * real de la ficha/documento se valida nuevamente en los controladores.
     */
    private const PATRONES = [
        '#^/guardias/[A-Za-z0-9_-]+$#',
        '#^/guardias/[A-Za-z0-9_-]+/editar$#',
        '#^/guardias/[A-Za-z0-9_-]+/expediente$#',
        '#^/guardias/[A-Za-z0-9_-]+/(croquis|foto)$#',
        '#^/expedientes/[A-Za-z0-9_-]+/documento/[A-Za-z0-9_-]+$#',
        '#^/expedientes/documento/[A-Za-z0-9_-]+$#',
        '#^/expedientes/documento/[A-Za-z0-9_-]+/descargar$#',
    ];

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $user = $request->attribute('auth_user');

        if (!$user instanceof AuthenticatedUser || !$user->esAspiranteGuardia()) {
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

        foreach (self::PATRONES as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
