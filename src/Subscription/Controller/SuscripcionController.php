<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Controller;

use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Subscription\Application\ConsultarSuscripcionService;
use DigiSecure\Subscription\Application\RegistrarCicloService;

/**
 * Controller de gestión de la suscripción del tenant.
 *
 * Rutas:
 *   GET  /suscripcion                 → Dashboard (plan actual, uso, historial, comparativa)
 *   GET  /suscripcion/ciclo/nuevo     → Formulario para registrar renovación/upgrade
 *   POST /suscripcion/ciclo/nuevo     → Procesar registro de ciclo
 */
final class SuscripcionController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly ConsultarSuscripcionService $consultar,
        private readonly RegistrarCicloService $registrar,
        private readonly FlashBag $flash,
    ) {
    }

    public function show(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $data = $this->consultar->execute();

        return $this->view->response('suscripcion/show', [
            'user'        => $user,
            'csrfToken'   => $csrf->token(),
            'currentPath' => $request->path,
            'tenant'      => $data['tenant'],
            'plan'        => $data['plan'],
            'catalogo'    => $data['catalogo'],
            'uso'         => $data['uso'],
            'cicloVigente'=> $data['ciclo_vigente'],
            'historial'   => $data['historial'],
            'diasRestantes' => $data['dias_restantes'],
            'vencido'     => $data['vencido'],
        ]);
    }

    public function nuevoCicloForm(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $data = $this->consultar->execute();

        return $this->view->response('suscripcion/nuevo-ciclo', [
            'user'        => $user,
            'csrfToken'   => $csrf->token(),
            'currentPath' => $request->path,
            'tenant'      => $data['tenant'],
            'plan'        => $data['plan'],
            'catalogo'    => $data['catalogo'],
            'old'         => [],
        ]);
    }

    public function nuevoCiclo(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $this->registrar->execute($request->post, $user->id, $request->clientIp());
            $this->flash->success('Ciclo de suscripcion registrado correctamente.');
            return Response::redirect('/suscripcion');
        } catch (DomainException $e) {
            $data = $this->consultar->execute();
            return $this->view->response('suscripcion/nuevo-ciclo', [
                'user'        => $user,
                'csrfToken'   => $csrf->token(),
                'currentPath' => '/suscripcion/ciclo/nuevo',
                'tenant'      => $data['tenant'],
                'plan'        => $data['plan'],
                'catalogo'    => $data['catalogo'],
                'old'         => $request->post,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
