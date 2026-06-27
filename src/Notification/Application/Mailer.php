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
 * no hay SMTP configurado). Si el envío falla (p. ej. sin MTA en localhost),
 * registra el cuerpo (con su enlace) en var/logs/mail.log para continuar el flujo.
 */
final class Mailer
{
    public function __construct(
        private readonly EmailChannel $email,
        private readonly string $devLogPath,
    ) {
    }

    public function enviar(
        string $correo,
        string $nombre,
        string $asunto,
        string $cuerpoTexto,
        string $cuerpoHtml,
    ): bool {
        $resultado = $this->email->send([
            'destinatario_email'  => $correo,
            'destinatario_nombre' => $nombre,
            'asunto'              => $asunto,
            'cuerpo_texto'        => $cuerpoTexto,
            'cuerpo_html'         => $cuerpoHtml,
        ]);

        if (!$resultado->success) {
            // Se registra el cuerpo (con su enlace) para poder continuar el flujo
            // en entornos sin MTA local (mail() a localhost:25 falla en dev).
            $this->logDev(sprintf(
                "Fallo enviando a %s (%s): %s\n  Asunto: %s\n  --- Cuerpo (texto) ---\n%s",
                $correo,
                $resultado->codigo ?? 'ERROR',
                $resultado->mensaje ?? 'sin detalle',
                $asunto,
                $cuerpoTexto,
            ));
        }

        return $resultado->success;
    }

    private function logDev(string $mensaje): void
    {
        @file_put_contents($this->devLogPath, '[' . date('Y-m-d H:i:s') . "] {$mensaje}\n", FILE_APPEND);
        error_log('[DIGISECURE][MAIL] ' . str_replace("\n", ' | ', $mensaje));
    }
}
