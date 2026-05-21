<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Comparte contexto base para el layout principal.
 *
 * Evita que cada controlador deba enviar manualmente:
 *  - user
 *  - csrfToken
 *  - currentPath
 */
final class LayoutContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly CsrfGuard $csrf,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        /** @var AuthenticatedUser|null $user */
        $user = $request->attribute('auth_user');

        if ($user !== null) {
            $this->view->share('user', $user);
        }

        $this->view->share('csrfToken', $this->csrf->token());
        $this->view->share('currentPath', $request->path);

        return $next->handle($request);
    }
}

