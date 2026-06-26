<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Controller;

use DigiSecure\Aspirante\Application\VerificarEmailService;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Verificacion de identidad del aspirante (enlace magico de email).
 *
 * Ruta publica:
 *   GET /verificar?token=XXXX
 *
 * Valida el token y marca el email como verificado. No autentica ni habilita
 * al aspirante; solo confirma que el correo es real.
 */
final class VerificacionController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly VerificarEmailService $verificar,
    ) {
    }

    public function verificar(Request $request): Response
    {
        $token = (string) $request->query('token', '');

        try {
            $resultado = $this->verificar->execute($token);
        } catch (DomainException $e) {
            return $this->view->response('auth/aspirante-verificacion-fallida', [
                'mensaje' => $e->getMessage(),
            ], Response::STATUS_BAD_REQUEST);
        }

        return $this->view->response('auth/aspirante-verificado', [
            'nombre'       => $resultado['nombre'],
            'yaVerificado' => $resultado['status'] === 'ya_verificado',
        ]);
    }
}
