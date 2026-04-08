<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;

/**
 * Inyecta cabeceras de seguridad en cada respuesta.
 *
 * Complementa los headers definidos en public/.htaccess: si Apache
 * está mal configurado o se sirve por otra vía (built-in server),
 * estos headers siguen aplicándose.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $response = $next->handle($request);

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'geolocation=(self), microphone=(), camera=()')
            ->withHeader('X-XSS-Protection', '0');
    }
}
