<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;

/**
 * Extrae los mensajes flash pendientes (depositados en el request anterior via
 * redirect) y los comparte con el layout para que se rendericen como toasts.
 *
 * Solo corre en GETs: un POST que genere un flash no debe consumirlo ahi mismo,
 * sino en el GET posterior al redirect (patron PRG).
 */
final class FlashMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly FlashBag $flash,
        private readonly ViewRenderer $view,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if ($request->method === 'GET') {
            $this->view->share('flashes', $this->flash->pull());
        }

        return $next->handle($request);
    }
}
