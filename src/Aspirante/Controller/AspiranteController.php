<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Controller;

use DigiSecure\Aspirante\Application\RegistrarAspiranteService;
use DigiSecure\Aspirante\Infrastructure\AspiranteEventLogger;
use DigiSecure\Aspirante\Infrastructure\VerificationMailer;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Captcha\RecaptchaVerifier;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Registro público de aspirantes (postulantes a guardia).
 *
 * POST /aspirantes — procesa el modal de "Registro" de la pantalla de login.
 *
 * Flujo: valida (CSRF + reCAPTCHA + reglas de negocio), persiste el aspirante en
 * estado 'nuevo' (email sin verificar) y envía el correo con enlace mágico de
 * verificación. Audita registro_creado y link_enviado. En éxito muestra "revisa
 * tu correo"; en error re-renderiza el login reabriendo el modal.
 */
final class AspiranteController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly RegistrarAspiranteService $registrar,
        private readonly RecaptchaVerifier $recaptcha,
        private readonly VerificationMailer $mailer,
        private readonly AspiranteEventLogger $eventos,
        private readonly Config $config,
    ) {
    }

    public function registrar(Request $request, CsrfGuard $csrf): Response
    {
        $form = [
            'tipo_documento'   => (string) $request->post('tipo_documento', ''),
            'documento'        => (string) $request->post('documento', ''),
            'apellidos'        => (string) $request->post('apellidos', ''),
            'nombres'          => (string) $request->post('nombres', ''),
            'celular'          => (string) $request->post('celular', ''),
            'correo'           => (string) $request->post('correo', ''),
            'estatura'         => (string) $request->post('estatura', ''),
            'fecha_nacimiento' => (string) $request->post('fecha_nacimiento', ''),
            'password'              => (string) $request->post('password', ''),
            'password_confirmacion' => (string) $request->post('password_confirmacion', ''),
            'acepta_politicas' => $request->post('acepta_politicas') !== null,
        ];

        $ip = $request->clientIp();
        $ua = $request->userAgent();

        // Anti-bot: validar reCAPTCHA antes de cualquier trabajo en la BD.
        if (!$this->recaptcha->verify($request->post('g-recaptcha-response'), $ip)) {
            return $this->reRenderLogin($csrf, $form, 'Confirma que no eres un robot e inténtalo de nuevo.');
        }

        try {
            $resultado = $this->registrar->execute($form, $ip, $ua);
        } catch (DomainException $e) {
            return $this->reRenderLogin($csrf, $form, $e->getMessage());
        }

        $this->eventos->registrar(AspiranteEventLogger::REGISTRO_CREADO, $resultado['correo'], $resultado['id'], $ip, $ua);

        $enlace = rtrim($this->config->string('app.url', ''), '/') . '/verificar?token=' . $resultado['verificacion_token'];
        $this->mailer->enviarVerificacion($resultado['correo'], $resultado['nombre_completo'], $enlace);
        $this->eventos->registrar(AspiranteEventLogger::LINK_ENVIADO, $resultado['correo'], $resultado['id'], $ip, $ua);

        return $this->view->response('auth/aspirante-gracias', [
            'nombre' => $resultado['nombre_completo'],
            'correo' => $resultado['correo'],
        ]);
    }

    /**
     * Re-renderiza el login reabriendo el modal con el error. Nunca devuelve
     * las contraseñas al cliente.
     *
     * @param array<string, mixed> $form
     */
    private function reRenderLogin(CsrfGuard $csrf, array $form, string $error): Response
    {
        unset($form['password'], $form['password_confirmacion']);

        return $this->view->response('auth/login', [
            'csrfToken'         => $csrf->token(),
            'returnTo'          => null,
            'error'             => null,
            'email'             => '',
            'recaptchaSiteKey'  => $this->recaptcha->siteKey(),
            'registroError'     => $error,
            'registroForm'      => $form,
            'registroOpen'      => true,
        ], Response::STATUS_UNPROCESSABLE);
    }
}
