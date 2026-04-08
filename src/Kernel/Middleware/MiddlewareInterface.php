<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;

/**
 * Contrato de middleware.
 *
 * Inspirado en PSR-15 pero simplificado:
 * - Recibe Request + RequestHandler (next).
 * - Devuelve Response.
 * - Puede:
 *     - decorar la Request antes de delegar a $next
 *     - decorar la Response después de $next
 *     - cortocircuitar devolviendo una Response sin llamar a $next
 */
interface MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $next): Response;
}
