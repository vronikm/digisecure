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

        // Imágenes embebidas (logo, etc.) que viajan DENTRO del correo (cid:), para
        // que se vean sin depender de una URL pública alcanzable por el cliente.
        $imagenes = $this->prepararImagenesEmbebidas(
            is_array($notificacion['imagenes_embebidas'] ?? null) ? $notificacion['imagenes_embebidas'] : [],
        );

        $start = (int) (microtime(true) * 1000);

        // Usa la funcion nativa mail() de PHP (MTA del hosting via php.ini, igual
        // que otros proyectos del servidor) cuando: el driver es 'mail', NO hay
        // host SMTP configurado, o PHPMailer no esta disponible. Solo usa SMTP
        // autenticado si hay host configurado y driver != 'mail'.
        $usarNativo = $this->driver === 'mail'
            || $this->host === ''
            || !class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');

        if ($usarNativo) {
            $result = $this->sendWithMailFunction($to, $subject, $bodyHtml, $bodyText, $imagenes);
        } else {
            $result = $this->sendWithPhpMailer($to, (string) ($notificacion['destinatario_nombre'] ?? ''), $subject, $bodyHtml, $bodyText, $imagenes);
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
     * @param list<array{cid:string, mime:string, nombre:string, data:string}> $imagenes
     * @return array{ok:bool, code?:string, msg?:string}
     */
    private function sendWithPhpMailer(string $to, string $toName, string $subject, ?string $bodyHtml, ?string $bodyText, array $imagenes = []): array
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

            foreach ($imagenes as $img) {
                $mail->addStringEmbeddedImage($img['data'], $img['cid'], $img['nombre'], 'base64', $img['mime']);
            }

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
     * @param list<array{cid:string, mime:string, nombre:string, data:string}> $imagenes
     * @return array{ok:bool, code?:string, msg?:string}
     */
    private function sendWithMailFunction(string $to, string $subject, ?string $bodyHtml, ?string $bodyText, array $imagenes = []): array
    {
        $isHtml = $bodyHtml !== null;
        $body = $isHtml ? $bodyHtml : (string) $bodyText;

        $boundary = bin2hex(random_bytes(16));

        $headers = [
            'MIME-Version: 1.0',
            'X-Mailer: DigiSecure-Notify',
        ];
        // Solo añadimos From si hay dirección configurada; si no, dejamos que
        // PHP use sendmail_from del php.ini (evita un "From: Nombre <>" inválido).
        if ($this->fromAddress !== '') {
            $headers[] = 'From: ' . ($this->fromName !== ''
                ? sprintf('%s <%s>', $this->encodeHeader($this->fromName), $this->fromAddress)
                : $this->fromAddress);
        }

        if ($isHtml && $imagenes !== []) {
            // multipart/related: HTML (+texto alterno) junto con las imágenes cid:.
            $relBoundary = bin2hex(random_bytes(16));
            $headers[] = "Content-Type: multipart/related; boundary=\"{$relBoundary}\"";
            $body = $this->buildRelatedBody($bodyText, (string) $bodyHtml, $imagenes, $relBoundary, $boundary);
        } elseif ($isHtml && $bodyText !== null) {
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

    /**
     * Lee y normaliza las imágenes a embeber. Cada entrada de entrada es
     * ['cid' => string, 'ruta' => string]. Devuelve solo las legibles.
     *
     * @param array<int, mixed> $imagenes
     * @return list<array{cid:string, mime:string, nombre:string, data:string}>
     */
    private function prepararImagenesEmbebidas(array $imagenes): array
    {
        $out = [];
        foreach ($imagenes as $img) {
            if (!is_array($img)) {
                continue;
            }
            $cid = (string) ($img['cid'] ?? '');
            $ruta = (string) ($img['ruta'] ?? '');
            if ($cid === '' || $ruta === '' || !is_file($ruta)) {
                continue;
            }

            $data = @file_get_contents($ruta);
            if ($data === false || $data === '') {
                continue;
            }

            $mime = 'application/octet-stream';
            if (class_exists('\\finfo')) {
                $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }

            $out[] = [
                'cid' => $cid,
                'mime' => $mime,
                'nombre' => basename($ruta),
                'data' => $data,
            ];
        }

        return $out;
    }

    /**
     * Cuerpo multipart/related: una parte multipart/alternative (texto + HTML)
     * seguida de las imágenes embebidas referenciadas por cid:.
     *
     * @param list<array{cid:string, mime:string, nombre:string, data:string}> $imagenes
     */
    private function buildRelatedBody(?string $textPart, string $htmlPart, array $imagenes, string $relBoundary, string $altBoundary): string
    {
        $lines = ["--{$relBoundary}"];

        if ($textPart !== null) {
            $lines[] = "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"";
            $lines[] = '';
            $lines[] = "--{$altBoundary}";
            $lines[] = 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: 8bit';
            $lines[] = '';
            $lines[] = $textPart;
            $lines[] = '';
            $lines[] = "--{$altBoundary}";
            $lines[] = 'Content-Type: text/html; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: 8bit';
            $lines[] = '';
            $lines[] = $htmlPart;
            $lines[] = '';
            $lines[] = "--{$altBoundary}--";
        } else {
            $lines[] = 'Content-Type: text/html; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: 8bit';
            $lines[] = '';
            $lines[] = $htmlPart;
        }
        $lines[] = '';

        foreach ($imagenes as $img) {
            $lines[] = "--{$relBoundary}";
            $lines[] = "Content-Type: {$img['mime']}; name=\"{$img['nombre']}\"";
            $lines[] = 'Content-Transfer-Encoding: base64';
            $lines[] = "Content-ID: <{$img['cid']}>";
            $lines[] = "Content-Disposition: inline; filename=\"{$img['nombre']}\"";
            $lines[] = '';
            $lines[] = rtrim(chunk_split(base64_encode($img['data'])));
            $lines[] = '';
        }

        $lines[] = "--{$relBoundary}--";
        $lines[] = '';

        return implode("\r\n", $lines);
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
