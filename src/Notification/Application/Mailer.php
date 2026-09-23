<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Application;

use DigiSecure\Notification\Infrastructure\Channel\EmailChannel;

/**
 * Envío de correo SÍNCRONO (en el flujo web), para correos transaccionales
 * que no deben depender de la cola tenant-aware ni de un cron.
 *
 * La cola de notificaciones (EncolarNotificacionService) exige tenant_id y solo
 * se despacha por cron; para correos como "recuperar contraseña" o verificación
 * de aspirante eso provocaba que el correo no se enviara nunca. Este mailer lo
 * envía en el momento vía EmailChannel.
 *
 * Siempre intenta enviar vía EmailChannel (que por defecto usa mail() nativo si
 * no hay SMTP configurado). Si el envío falla lo registra en var/logs/mail.log;
 * el cuerpo (con su enlace) solo fuera de producción, ver RegistroEnvioFallido.
 */
final class Mailer
{
    private readonly RegistroEnvioFallido $fallos;

    public function __construct(
        private readonly EmailChannel $email,
        string $logPath,
        bool $registrarContenido = false,
    ) {
        $this->fallos = new RegistroEnvioFallido($logPath, $registrarContenido, 'MAIL');
    }

    public function enviar(
        string $correo,
        string $nombre,
        string $asunto,
        string $cuerpoTexto,
        string $cuerpoHtml,
    ): bool {
        return $this->enviarDetallado($correo, $nombre, $asunto, $cuerpoTexto, $cuerpoHtml)['success'];
    }

    /**
     * @return array{success: bool, code: ?string, message: ?string}
     */
    public function enviarDetallado(
        string $correo,
        string $nombre,
        string $asunto,
        string $cuerpoTexto,
        string $cuerpoHtml,
    ): array {
        $resultado = $this->email->send([
            'destinatario_email'  => $correo,
            'destinatario_nombre' => $nombre,
            'asunto'              => $asunto,
            'cuerpo_texto'        => $cuerpoTexto,
            'cuerpo_html'         => $cuerpoHtml,
        ]);

        if (!$resultado->success) {
            $this->fallos->registrar($correo, $resultado->codigo, $resultado->mensaje, $asunto, $cuerpoTexto);
        }

        return [
            'success' => $resultado->success,
            'code' => $resultado->codigo,
            'message' => $resultado->mensaje,
        ];
    }
}
