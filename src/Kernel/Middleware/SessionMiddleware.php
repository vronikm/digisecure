<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Security\Session\SessionManager;

/**
 * Inicia la sesión segura al comienzo de cada request.
 *
 * Debe ejecutarse ANTES de cualquier middleware que necesite leer/escribir
 * la sesión (CSRF, Authentication, TenantContext...). Si la sesión ya está
 * iniciada (idempotencia), SessionManager::start() es no-op.
 *
 * IMPORTANTE: este middleware NO envía cookies por sí mismo — eso lo hace
 * session_start() internamente. Por eso debe ejecutarse antes de cualquier
 * output (los middlewares posteriores y el handler).
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionManager $session,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $this->session->start();
        return $next->handle($request);
    }
}
