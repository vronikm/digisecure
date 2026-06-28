<?php

declare(strict_types=1);

namespace DigiSecure\User\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\User\Application\RequestPasswordResetService;
use DigiSecure\User\Application\ResetPasswordService;

/**
 * Flujo público de recuperación de contraseña (Fase 10).
 *
 * Rutas:
 *   GET  /password/forgot          → formulario para solicitar recuperación
 *   POST /password/forgot          → procesa solicitud y envía email
 *   GET  /password/reset/{token}   → formulario para nueva contraseña
 *   POST /password/reset/{token}   → aplica el reset
 *
 * Todas las rutas son públicas (no requieren autenticación) y están
 * registradas como tales en AuthenticationMiddleware.
 */
final class PasswordResetController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly RequestPasswordResetService $requestService,
        private readonly ResetPasswordService $resetService,
    ) {
    }

    // ======================== GET /password/forgot ========================

    public function showForgotForm(Request $request, CsrfGuard $csrf): Response
    {
        return $this->view->response('auth/forgot-password', [
            'csrfToken' => $csrf->token(),
            'sent' => false,
            'email' => '',
            'error' => null,
        ]);
    }

    // ======================== POST /password/forgot ========================

    public function processForgot(Request $request, CsrfGuard $csrf): Response
    {
        $email = trim((string) $request->post('email', ''));

        if ($email === '') {
            return $this->view->response('auth/forgot-password', [
                'csrfToken' => $csrf->token(),
                'sent' => false,
                'email' => '',
                'error' => 'Por favor ingresa tu correo electrónico.',
            ]);
        }

        $this->requestService->execute($email, $request->clientIp(), $request->userAgent());

        // Siempre mostramos el mismo mensaje (no revelar si el email existe)
        return $this->view->response('auth/forgot-password', [
            'csrfToken' => $csrf->token(),
            'sent' => true,
            'email' => $email,
            'error' => null,
        ]);
    }

    // ======================== GET /password/reset/{token} ========================

    public function showResetForm(Request $request, CsrfGuard $csrf, string $token): Response
    {
        try {
            $usuario = $this->resetService->validateToken($token);
        } catch (\Throwable $e) {
            error_log('[DIGISECURE][PASSWORD_RESET] Error validando enlace de recuperacion: ' . $e->getMessage());
            $usuario = null;
        }

        if ($usuario === null) {
            return $this->view->response('auth/reset-password', [
                'csrfToken' => $csrf->token(),
                'token' => '',
                'email' => '',
                'valid' => false,
                'error' => 'El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.',
                'success' => false,
            ]);
        }

        return $this->view->response('auth/reset-password', [
            'csrfToken' => $csrf->token(),
            'token' => $token,
            'email' => $usuario['email'],
            'valid' => true,
            'error' => null,
            'success' => false,
        ]);
    }

    // ======================== POST /password/reset/{token} ========================

    public function processReset(Request $request, CsrfGuard $csrf, string $token): Response
    {
        $passwordNuevo = (string) $request->post('password', '');
        $passwordConfirm = (string) $request->post('password_confirm', '');

        try {
            $this->resetService->execute(
                $token,
                $passwordNuevo,
                $passwordConfirm,
                $request->clientIp(),
                $request->userAgent(),
            );
        } catch (DomainException $e) {
            $usuario = $this->resetService->validateToken($token);

            return $this->view->response('auth/reset-password', [
                'csrfToken' => $csrf->token(),
                'token' => $usuario !== null ? $token : '',
                'email' => $usuario['email'] ?? '',
                'valid' => $usuario !== null,
                'error' => $e->getMessage(),
                'success' => false,
            ]);
        }

        return $this->view->response('auth/reset-password', [
            'csrfToken' => $csrf->token(),
            'token' => '',
            'email' => '',
            'valid' => false,
            'error' => null,
            'success' => true,
        ]);
    }
}
