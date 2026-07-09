<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Auth\AuthenticationException;
use DigiSecure\Security\Auth\Authenticator;
use DigiSecure\Security\Auth\LoginRedirectGuard;
use DigiSecure\Security\Captcha\RecaptchaVerifier;
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
        private readonly RecaptchaVerifier $recaptcha,
        private readonly LoginRedirectGuard $redirectGuard = new LoginRedirectGuard(),
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
        $acceptPolicies = $request->post('accept_policies', '') === '1' || $request->post('accept_policies', false) === true;

        if ($email === '' || $password === '') {
            return $this->renderLogin(
                $csrf->token(),
                $returnTo ?: null,
                'Ingresa tu email y contraseña.',
            );
        }

        try {
            $user = $auth->login($email, $password, $request->clientIp(), $request->userAgent());
        } catch (AuthenticationException $e) {
            return $this->renderLogin(
                $csrf->token(),
                $returnTo ?: null,
                $e->getMessage(),
                $email,
            );
        }

        // Re-emitir token CSRF al cambiar de contexto anonimo -> autenticado.
        $csrf->rotateToken();

        $target = $this->redirectGuard->resolve($returnTo, $user);
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
            'recaptchaSiteKey' => $this->recaptcha->siteKey(),
        ]);
    }

}
