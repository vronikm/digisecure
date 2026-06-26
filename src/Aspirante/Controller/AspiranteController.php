<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Controller;

use DigiSecure\Aspirante\Application\RegistrarAspiranteService;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Notification\Application\EncolarNotificacionService;
use DigiSecure\Notification\Domain\Enum\CanalNotificacion;
use DigiSecure\Notification\Domain\Enum\PrioridadNotificacion;
use DigiSecure\Security\Captcha\RecaptchaVerifier;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Registro publico de aspirantes (postulantes a guardia).
 *
 * Ruta:
 *   POST /aspirantes  → procesa el modal de "Registro" de la pantalla de login.
 *
 * Flujo: valida (CSRF + reCAPTCHA + reglas de negocio), persiste el aspirante
 * en estado pendiente con email sin verificar, y envia un correo con enlace
 * magico de verificacion (/verificar?token=...). En exito muestra una pantalla
 * "revisa tu correo"; en error re-renderiza el login reabriendo el modal.
 */
final class AspiranteController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly RegistrarAspiranteService $registrar,
        private readonly RecaptchaVerifier $recaptcha,
        private readonly EncolarNotificacionService $encolar,
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

        // Anti-bot: validar reCAPTCHA antes de cualquier trabajo en la BD.
        if (!$this->recaptcha->verify($request->post('g-recaptcha-response'), $request->clientIp())) {
            return $this->reRenderLogin($csrf, $form, 'Confirma que no eres un robot e inténtalo de nuevo.');
        }

        try {
            $resultado = $this->registrar->execute(
                $form,
                $request->clientIp(),
                $request->userAgent(),
            );
        } catch (DomainException $e) {
            return $this->reRenderLogin($csrf, $form, $e->getMessage());
        }

        $this->enviarCorreoVerificacion($resultado, $request->clientIp());

        return $this->view->response('auth/aspirante-gracias', [
            'nombre' => $resultado['nombre_completo'],
            'correo' => $resultado['correo'],
        ]);
    }

    /**
     * Encola el correo de verificacion con el enlace magico. Un fallo aqui no
     * debe romper la experiencia: el aspirante ya quedo persistido.
     *
     * @param array{id: string, nombre_completo: string, correo: string, verificacion_token: string} $r
     */
    private function enviarCorreoVerificacion(array $r, string $ip): void
    {
        $enlace = rtrim($this->config->string('app.url', ''), '/') . '/verificar?token=' . $r['verificacion_token'];

        try {
            $this->encolar->execute([
                'evento'              => 'aspirante.email.verificacion',
                'canal'               => CanalNotificacion::EMAIL,
                'prioridad'           => PrioridadNotificacion::ALTA,
                'destinatario_email'  => $r['correo'],
                'destinatario_nombre' => $r['nombre_completo'],
                'asunto'              => 'GEMESEG · Confirma tu correo electrónico',
                'cuerpo_texto'        => $this->cuerpoTexto($r['nombre_completo'], $enlace),
                'cuerpo_html'         => $this->cuerpoHtml($r['nombre_completo'], $enlace),
                'entidad_tipo'        => 'aspirante',
                'entidad_id'          => $r['id'],
                'dedupe_key'          => 'aspirante_verif:' . $r['id'],
                'max_intentos'        => 3,
            ], 'SYSTEM', $ip);
        } catch (\Throwable) {
            // Silencioso a proposito: el registro fue exitoso; el correo se
            // puede reintentar desde la cola de notificaciones.
        }
    }

    private function cuerpoTexto(string $nombre, string $enlace): string
    {
        $saludo = $nombre !== '' ? "Hola {$nombre}," : 'Hola,';

        return <<<TEXT
{$saludo}

Bienvenido. Para continuar con el proceso de habilitación, por favor confirma
que este es tu correo electrónico abriendo el siguiente enlace:

{$enlace}

Este enlace expira en 48 horas. Si tú no solicitaste este registro, ignora este mensaje.

— GEMESEG Seguridad · Plataforma DigiSecure
TEXT;
    }

    private function cuerpoHtml(string $nombre, string $enlace): string
    {
        $nombreEsc = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $enlaceEsc = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');
        $saludo = $nombreEsc !== '' ? "Hola <strong>{$nombreEsc}</strong>," : 'Hola,';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Confirma tu correo · GEMESEG</title></head>
<body style="font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:24px;color:#16324f">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden">
  <div style="background:#1a1a1a;color:#fff;padding:28px 24px;text-align:center">
    <h2 style="margin:0;font-size:22px;letter-spacing:1px">GEMESEG</h2>
    <div style="opacity:.85;font-size:13px;margin-top:4px">Plataforma DigiSecure</div>
  </div>
  <div style="padding:28px 24px">
    <p>{$saludo}</p>
    <p>Bienvenido. Para continuar con el proceso de habilitación, confirma que este es tu correo electrónico.</p>
    <p style="margin:28px 0;text-align:center">
      <a href="{$enlaceEsc}" style="display:inline-block;background:#b91c1c;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-weight:bold;font-size:15px">
        ✔ Confirmar mi correo
      </a>
    </p>
    <p style="color:#6c757d;font-size:13px">Este enlace expira en <strong>48 horas</strong>.</p>
    <p style="color:#6c757d;font-size:12px">Si el botón no funciona, copia y pega esta URL en tu navegador:</p>
    <p style="word-break:break-all;font-size:12px;background:#f8f9fa;padding:8px 12px;border-radius:4px;color:#495057">{$enlaceEsc}</p>
    <hr style="border:none;border-top:1px solid #e9ecef;margin:24px 0">
    <p style="color:#6c757d;font-size:12px">Si tú no solicitaste este registro, ignora este mensaje.</p>
  </div>
  <div style="background:#f8f9fa;padding:12px 24px;font-size:11px;color:#6c757d;text-align:center">
    — GEMESEG Seguridad · Plataforma DigiSecure
  </div>
</div>
</body>
</html>
HTML;
    }

    /**
     * Re-renderiza el login reabriendo el modal con el error. Nunca devuelve
     * las contrasenas al cliente.
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
