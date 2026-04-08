<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticationException;
use DigiSecure\Security\Auth\Authenticator;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Controller de login.
 *
 * GET  /login → muestra formulario
 * POST /login → procesa credenciales
 */
final class LoginController
{
    public function __construct(
        private readonly ViewRenderer $viewRenderer,
    ) {
    }

    public function showForm(CsrfGuard $csrf, Request $request, Authenticator $auth): Response
    {
        if ($auth->isAuthenticated()) {
            return Response::redirect('/dashboard');
        }

        return $this->renderLogin($csrf->token(), $request->query('return_to'));
    }

    public function processLogin(Request $request, Authenticator $auth, CsrfGuard $csrf): Response
    {
        $email = trim((string)$request->post('email', ''));
        $password = (string)$request->post('password', '');
        $returnTo = trim((string)$request->post('return_to', ''));

        if ($email === '' || $password === '') {
            return $this->renderLogin(
                $csrf->token(),
                $returnTo ?: null,
                'Ingresa tu email y contraseña.',
            );
        }

        try {
            $auth->login($email, $password, $request->clientIp(), $request->userAgent());
        } catch (AuthenticationException $e) {
            return $this->renderLogin(
                $csrf->token(),
                $returnTo ?: null,
                $e->getMessage(),
                $email,
            );
        }

        $target = $this->sanitizeReturnTo($returnTo);
        return Response::redirect($target);
    }

    private function renderLogin(
        string $csrfToken,
        ?string $returnTo = null,
        ?string $error = null,
        string $email = '',
    ): Response {
        return $this->viewRenderer->response('auth/login', [
            'csrfToken' => $csrfToken,
            'returnTo' => $returnTo,
            'error' => $error,
            'email' => $email,
        ]);
    }

    /**
     * Sanitiza la URL de retorno para evitar open redirect.
     */
    private function sanitizeReturnTo(string $returnTo): string
    {
        if ($returnTo === '') {
            return '/dashboard';
        }

        if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return '/dashboard';
        }

        if (preg_match('#[a-z]+://#i', $returnTo)) {
            return '/dashboard';
        }

        return $returnTo;
    }
}
