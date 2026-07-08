<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Controller;

use DigiSecure\Aspirante\Application\ReenviarVerificacionService;
use DigiSecure\Aspirante\Application\VerificarEmailService;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Verificación de identidad del aspirante (enlace mágico de email) y reenvío.
 *
 * Rutas públicas:
 *   GET  /verificar?token=XXXX     muestra pantalla segura de confirmacion
 *   POST /verificar                confirma el correo y completa el registro
 *   GET  /verificar/reenviar       formulario para solicitar un nuevo enlace
 *   POST /verificar/reenviar       procesa el reenvío (anti-enumeración + rate-limit)
 */
final class VerificacionController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly VerificarEmailService $verificar,
        private readonly ReenviarVerificacionService $reenviar,
    ) {
    }

    public function verificar(Request $request, CsrfGuard $csrf): Response
    {
        $token = (string) $request->query('token', '');

        try {
            $resultado = $this->verificar->preview($token, $request->clientIp(), $request->userAgent());
        } catch (DomainException $e) {
            return $this->view->response('auth/aspirante-verificacion-fallida', [
                'mensaje'   => $e->getMessage(),
                'csrfToken' => $csrf->token(),
            ], Response::STATUS_BAD_REQUEST);
        }

        return $this->view->response('auth/aspirante-confirmar-verificacion', [
            'csrfToken' => $csrf->token(),
            'token' => $resultado['token'],
            'nombre' => $resultado['nombre'],
            'correo' => $resultado['correo'],
        ]);
    }

    public function confirmar(Request $request, CsrfGuard $csrf): Response
    {
        $token = (string) $request->post('token', '');

        try {
            $resultado = $this->verificar->execute($token, $request->clientIp(), $request->userAgent());
        } catch (DomainException $e) {
            return $this->view->response('auth/aspirante-verificacion-fallida', [
                'mensaje'   => $e->getMessage(),
                'csrfToken' => $csrf->token(),
            ], Response::STATUS_BAD_REQUEST);
        }

        return $this->view->response('auth/aspirante-verificado', [
            'nombre'       => $resultado['nombre'],
            'yaVerificado' => $resultado['status'] === 'ya_verificado',
        ]);
    }

    public function reenviarForm(Request $request, CsrfGuard $csrf): Response
    {
        return $this->view->response('auth/aspirante-reenviar', [
            'csrfToken' => $csrf->token(),
            'sent'      => false,
            'email'     => '',
        ]);
    }

    public function reenviar(Request $request, CsrfGuard $csrf): Response
    {
        $email = (string) $request->post('email', '');

        // Respuesta SIEMPRE neutra (no revela si el correo existe).
        $this->reenviar->execute($email, $request->clientIp(), $request->userAgent());

        return $this->view->response('auth/aspirante-reenviar', [
            'csrfToken' => $csrf->token(),
            'sent'      => true,
            'email'     => $email,
        ]);
    }
}
