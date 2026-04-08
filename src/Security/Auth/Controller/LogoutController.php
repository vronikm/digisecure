<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Security\Auth\Authenticator;

/**
 * Controller de logout.
 *
 * POST /logout → destruye sesión y redirige a login.
 * Solo acepta POST (protegido por CSRF) para evitar logout por GET/img tag.
 */
final class LogoutController
{
    public function logout(Request $request, Authenticator $auth): Response
    {
        $auth->logout($request->clientIp());

        return Response::redirect('/login');
    }
}
