<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Infrastructure;

use DigiSecure\Notification\Application\RegistroEnvioFallido;
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
 * Siempre intenta enviar vía EmailChannel (que por defecto usa mail() nativo si
 * no hay SMTP configurado). Si el envío falla lo registra en
 * var/logs/verificacion.log; el enlace solo fuera de producción, ver
 * RegistroEnvioFallido.
 */
final class VerificationMailer
{
    /** Content-ID del logo embebido en el encabezado del correo. */
    private const LOGO_CID = 'logoGemeseg';

    private readonly RegistroEnvioFallido $fallos;

    public function __construct(
        private readonly EmailChannel $email,
        string $logPath,
        private readonly string $logoPath = '',
        bool $registrarContenido = false,
    ) {
        $this->fallos = new RegistroEnvioFallido($logPath, $registrarContenido, 'VERIFICACION');
    }

    /**
     * @return array{success: bool, code: ?string, message: ?string}
     */
    public function enviar(
        string $correo,
        string $nombre,
        string $asunto,
        string $cuerpoTexto,
        string $cuerpoHtml,
        string $enlace,
    ): array {
        $notificacion = [
            'destinatario_email'  => $correo,
            'destinatario_nombre' => $nombre,
            'asunto'              => $asunto,
            'cuerpo_texto'        => $cuerpoTexto,
            'cuerpo_html'         => $cuerpoHtml,
        ];

        // El logo viaja embebido (cid:) para que se vea sin depender de una URL
        // pública alcanzable por el cliente (localhost, proxy de Gmail, etc.).
        if ($this->tieneLogo()) {
            $notificacion['imagenes_embebidas'] = [
                ['cid' => self::LOGO_CID, 'ruta' => $this->logoPath],
            ];
        }

        $resultado = $this->email->send($notificacion);

        if (!$resultado->success) {
            $this->fallos->registrar($correo, $resultado->codigo, $resultado->mensaje, $asunto, "Enlace: {$enlace}");
        }

        return [
            'success' => $resultado->success,
            'code' => $resultado->codigo,
            'message' => $resultado->mensaje,
        ];
    }

    private function tieneLogo(): bool
    {
        return $this->logoPath !== '' && is_file($this->logoPath);
    }

    /**
     * Construye y envía el correo de verificación con el enlace mágico.
     * Usado tanto en el registro inicial como en el reenvío.
     */
    /**
     * @return array{success: bool, code: ?string, message: ?string}
     */
    public function enviarVerificacion(string $correo, string $nombre, string $enlace): array
    {
        return $this->enviar(
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
        $anio = date('Y');

        // El encabezado usa el mismo azul marino del login (ds-auth.css) y muestra
        // el logo corporativo embebido (cid:). Se fija color:#fff en el <img> para
        // que, si el logo no cargara, el texto alternativo "GEMESEG Seguridad"
        // aparezca en blanco (legible sobre el azul del encabezado).
        if ($this->tieneLogo()) {
            $marca = '<img src="cid:' . self::LOGO_CID . '" alt="GEMESEG Seguridad" width="210" '
                . 'style="display:inline-block;width:210px;max-width:80%;height:auto;border:0;outline:none;'
                . 'text-decoration:none;color:#ffffff;font-size:20px;font-weight:bold">';
        } else {
            $marca = '<h2 style="margin:0;font-size:22px;letter-spacing:1px;color:#fff">GEMESEG</h2>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Confirma tu correo · GEMESEG</title></head>
<body style="font-family:'Segoe UI',system-ui,Arial,sans-serif;background:#0b1133;margin:0;padding:24px;color:#0f172a">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;box-shadow:0 30px 80px rgba(5,16,30,.35);overflow:hidden">
  <div style="background:#0d1f4f;padding:30px 24px;text-align:center">
    {$marca}
    <div style="color:rgba(255,255,255,.7);font-size:12px;margin-top:10px;letter-spacing:.08em;text-transform:uppercase">Plataforma DigiSecure</div>
  </div>
  <div style="padding:28px 24px;color:#0f172a">
    <p style="margin:0 0 12px">{$saludo}</p>
    <p style="margin:0 0 12px">Bienvenido. Para continuar con el proceso de habilitación, confirma que este es tu correo electrónico.</p>
    <p style="margin:28px 0;text-align:center">
      <a href="{$enlaceEsc}" style="display:inline-block;background:#e3b51b;color:#111;text-decoration:none;padding:14px 34px;border-radius:10px;font-weight:bold;font-size:15px;box-shadow:0 6px 18px rgba(133,94,0,.35)">
        ✔ Confirmar mi correo
      </a>
    </p>
    <p style="color:#5f7288;font-size:13px;margin:0 0 12px">Este enlace expira en <strong>48 horas</strong>.</p>
    <p style="color:#5f7288;font-size:12px;margin:0 0 6px">Si el botón no funciona, copia y pega esta URL en tu navegador:</p>
    <p style="word-break:break-all;font-size:12px;background:#f1f5fb;padding:8px 12px;border-radius:6px;color:#1d4ed8;margin:0">{$enlaceEsc}</p>
    <hr style="border:none;border-top:1px solid #e9ecef;margin:24px 0">
    <p style="color:#5f7288;font-size:12px;margin:0">Si tú no solicitaste este registro, ignora este mensaje.</p>
  </div>
  <div style="background:#f6f8fb;padding:14px 24px;font-size:11px;color:#5f7288;text-align:center">
    &copy; {$anio} GEMESEG Seguridad · Plataforma DigiSecure
  </div>
</div>
</body>
</html>
HTML;
    }
}
