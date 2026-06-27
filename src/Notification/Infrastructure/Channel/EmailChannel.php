<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Infrastructure\Channel;

/**
 * Canal de envío por email.
 *
 * Implementación dual:
 *  - Si la clase \PHPMailer\PHPMailer\PHPMailer está disponible (vendor instalado),
 *    se usa SMTP autenticado con TLS.
 *  - Si no, se cae al wrapper PHP mail() con cabeceras manuales.
 *
 * Esta dualidad permite arrancar Fase 4 sin obligar a `composer install`
 * inmediato, pero usa SMTP en cuanto el vendor esté disponible.
 */
final class EmailChannel implements NotificationChannel
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $encryption,
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly string $driver = 'smtp',
    ) {
    }

    public function send(array $notificacion): ChannelResult
    {
        $to = (string) ($notificacion['destinatario_email'] ?? '');
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return ChannelResult::fail('Destinatario email vacío o inválido', 'INVALID_RECIPIENT');
        }

        $subject = (string) ($notificacion['asunto'] ?? '(sin asunto)');
        $bodyHtml = $notificacion['cuerpo_html'] ?? null;
        $bodyText = $notificacion['cuerpo_texto'] ?? null;

        if ($bodyHtml === null && $bodyText === null) {
            return ChannelResult::fail('Notificación sin cuerpo', 'EMPTY_BODY');
        }

        $start = (int) (microtime(true) * 1000);

        // driver='mail' fuerza la funcion nativa mail() de PHP (igual que otros
        // proyectos del servidor que usan el MTA del hosting via php.ini), sin
        // requerir credenciales SMTP. Si no, se usa SMTP/PHPMailer cuando esta
        // disponible; como ultimo recurso, mail() nativo.
        $usarNativo = $this->driver === 'mail' || !class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');

        if ($usarNativo) {
            $result = $this->sendWithMailFunction($to, $subject, $bodyHtml, $bodyText);
        } else {
            $result = $this->sendWithPhpMailer($to, (string) ($notificacion['destinatario_nombre'] ?? ''), $subject, $bodyHtml, $bodyText);
        }

        $elapsed = (int) (microtime(true) * 1000) - $start;
        return new ChannelResult(
            success: $result['ok'],
            codigo: $result['code'] ?? null,
            mensaje: $result['msg'] ?? null,
            duracionMs: $elapsed,
        );
    }

    /**
     * @return array{ok:bool, code?:string, msg?:string}
     */
    private function sendWithPhpMailer(string $to, string $toName, string $subject, ?string $bodyHtml, ?string $bodyText): array
    {
        try {
            /** @psalm-suppress UndefinedClass */
            $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
            $mail = new $mailerClass(true);

            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->SMTPAuth = $this->username !== '';
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            if ($this->encryption !== '') {
                $mail->SMTPSecure = $this->encryption;
            }
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($to, $toName);
            $mail->Subject = $subject;

            if ($bodyHtml !== null) {
                $mail->isHTML(true);
                $mail->Body = $bodyHtml;
                $mail->AltBody = $bodyText ?? strip_tags($bodyHtml);
            } else {
                $mail->isHTML(false);
                $mail->Body = $bodyText ?? '';
            }

            $mail->send();
            return ['ok' => true, 'code' => 'SMTP_OK', 'msg' => 'Sent via PHPMailer'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'code' => 'SMTP_ERROR', 'msg' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool, code?:string, msg?:string}
     */
    private function sendWithMailFunction(string $to, string $subject, ?string $bodyHtml, ?string $bodyText): array
    {
        $isHtml = $bodyHtml !== null;
        $body = $isHtml ? $bodyHtml : (string) $bodyText;

        $boundary = bin2hex(random_bytes(16));
        $fromHeader = $this->fromName !== ''
            ? sprintf('%s <%s>', $this->encodeHeader($this->fromName), $this->fromAddress)
            : $this->fromAddress;

        $headers = [
            'MIME-Version: 1.0',
            "From: {$fromHeader}",
            'X-Mailer: DigiSecure-Notify',
        ];

        if ($isHtml && $bodyText !== null) {
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $body = $this->buildMultipartBody($bodyText, (string) $bodyHtml, $boundary);
        } elseif ($isHtml) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }

        $subjectEncoded = $this->encodeHeader($subject);

        $ok = @mail($to, $subjectEncoded, $body, implode("\r\n", $headers));
        if (!$ok) {
            $err = error_get_last();
            return [
                'ok' => false,
                'code' => 'MAIL_FN_ERROR',
                'msg' => $err['message'] ?? 'mail() devolvió false',
            ];
        }
        return ['ok' => true, 'code' => 'MAIL_FN_OK', 'msg' => 'Sent via mail()'];
    }

    private function buildMultipartBody(string $textPart, string $htmlPart, string $boundary): string
    {
        return implode("\r\n", [
            "--{$boundary}",
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $textPart,
            '',
            "--{$boundary}",
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $htmlPart,
            '',
            "--{$boundary}--",
            '',
        ]);
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[\x80-\xff]/', $value) === 1) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
