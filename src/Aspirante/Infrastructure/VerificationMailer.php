<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Infrastructure;

use DigiSecure\Notification\Infrastructure\Channel\EmailChannel;

/**
 * Envia el correo de verificacion del aspirante de forma SINCRONA.
 *
 * Por que no usa la cola de notificaciones (EncolarNotificacionService):
 *   esa cola es tenant-aware (tenantInsert exige tenant_id), pero el registro
 *   de aspirantes es PUBLICO y sin tenant -> el enqueue lanzaba excepcion y se
 *   perdia silenciosamente. Ademas, la cola solo se despacha por un cron, no en
 *   el flujo web. Para un correo transaccional de verificacion lo correcto es
 *   enviarlo en el momento.
 *
 * Si el SMTP no esta configurado (MAIL_HOST vacio) -> modo desarrollo: el enlace
 * se escribe en var/logs/verificacion.log (y en el error_log) para poder probar
 * el flujo sin servidor de correo.
 */
final class VerificationMailer
{
    public function __construct(
        private readonly EmailChannel $email,
        private readonly bool $mailConfigurado,
        private readonly string $devLogPath,
    ) {
    }

    public function enviar(
        string $correo,
        string $nombre,
        string $asunto,
        string $cuerpoTexto,
        string $cuerpoHtml,
        string $enlace,
    ): void {
        if (!$this->mailConfigurado) {
            $this->logDev("MAIL no configurado. Enlace de verificación para {$correo}: {$enlace}");
            return;
        }

        $resultado = $this->email->send([
            'destinatario_email'  => $correo,
            'destinatario_nombre' => $nombre,
            'asunto'              => $asunto,
            'cuerpo_texto'        => $cuerpoTexto,
            'cuerpo_html'         => $cuerpoHtml,
        ]);

        if (!$resultado->success) {
            $this->logDev(sprintf(
                'Fallo enviando verificación a %s (%s): %s. Enlace: %s',
                $correo,
                $resultado->codigo ?? 'ERROR',
                $resultado->mensaje ?? 'sin detalle',
                $enlace,
            ));
        }
    }

    private function logDev(string $mensaje): void
    {
        $linea = '[' . date('Y-m-d H:i:s') . "] {$mensaje}\n";
        @file_put_contents($this->devLogPath, $linea, FILE_APPEND);
        error_log("[DIGISECURE][VERIFICACION] {$mensaje}");
    }

    /**
     * Construye y envía el correo de verificación con el enlace mágico.
     * Usado tanto en el registro inicial como en el reenvío.
     */
    public function enviarVerificacion(string $correo, string $nombre, string $enlace): void
    {
        $this->enviar(
            $correo,
            $nombre,
            'GEMESEG · Confirma tu correo electrónico',
            $this->cuerpoTexto($nombre, $enlace),
            $this->cuerpoHtml($nombre, $enlace),
            $enlace,
        );
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
}
