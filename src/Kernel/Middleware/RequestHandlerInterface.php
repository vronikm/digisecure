<?php
declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;

/**
 * Handler que produce una Response a partir de una Request.
 *
 * El Pipeline implementa esta interfaz para encadenar middlewares.
 * El último handler es el dispatcher de rutas.
 */
interface RequestHandlerInterface
{
    public function handle(Request $request): Response;
}
