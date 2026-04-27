<?php

declare(strict_types=1);

namespace DigiSecure\Kernel\Middleware;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Notification\Application\AlertasCentroService;
use DigiSecure\Security\Auth\AuthenticatedUser;
use Throwable;

/**
 * Inyecta el bundle de alertas del usuario en el ViewRenderer para que
 * el layout pueda renderizar el centro de notificaciones del topbar
 * sin que cada controlador tenga que consultarlo.
 *
 * Solo corre para requests autenticados de metodo GET (no gasta queries
 * en POSTs ni en endpoints JSON de rutas publicas).
 *
 * Si la consulta falla (p. ej. tablas ausentes en un entorno de test),
 * comparte un bundle vacio y continua. La cabecera es UX, no critica.
 */
final class AlertasLayoutMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AlertasCentroService $alertas,
        private readonly ViewRenderer $view,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        /** @var AuthenticatedUser|null $user */
        $user = $request->attribute('auth_user');

        if ($user !== null && $request->method === 'GET') {
            try {
                $this->view->share('alertas', $this->alertas->listarPara($user));
            } catch (Throwable) {
                $this->view->share('alertas', ['items' => [], 'total' => 0, 'criticas' => 0]);
            }
        }

        return $next->handle($request);
    }
}
