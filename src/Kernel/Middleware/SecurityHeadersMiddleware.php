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

        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'geolocation=(self), microphone=(), camera=()')
            ->withHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->withHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->withHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->withHeader('X-XSS-Protection', '0')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');

        if ($this->isHttps($request)) {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    private function isHttps(Request $request): bool
    {
        $https = strtolower((string)($request->server['HTTPS'] ?? ''));
        if ($https === 'on' || $https === '1') {
            return true;
        }

        return strtolower((string)($request->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
