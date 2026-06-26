<?php

declare(strict_types=1);

namespace DigiSecure\Security\Captcha;

/**
 * Verificador de Google reCAPTCHA v2 ("No soy un robot").
 *
 * Diseno (mismo criterio que WhatsAppChannel):
 *  - Si siteKey/secret estan vacios, el captcha se considera DESHABILITADO:
 *    verify() devuelve true y la vista no renderiza el widget. Asi el registro
 *    funciona en desarrollo sin obligar a configurar claves.
 *  - Cuando esta habilitado, valida el token contra el endpoint de Google.
 *  - Fail-closed: ante token vacio, error de red o respuesta no exitosa,
 *    devuelve false (rechaza) para no dejar pasar bots.
 *  - Sin dependencias externas: cURL nativo (ext-curl).
 *
 * Config en .env:
 *   RECAPTCHA_SITE_KEY=...   (publica, va en el HTML)
 *   RECAPTCHA_SECRET_KEY=... (privada, solo servidor)
 */
final class RecaptchaVerifier
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(
        private readonly string $siteKey = '',
        private readonly string $secret = '',
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->siteKey !== '' && $this->secret !== '';
    }

    public function siteKey(): string
    {
        return $this->siteKey;
    }

    /**
     * @param string|null $response Token `g-recaptcha-response` enviado por el form.
     */
    public function verify(?string $response, string $ip = ''): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $token = trim((string) $response);
        if ($token === '') {
            return false;
        }

        $payload = [
            'secret'   => $this->secret,
            'response' => $token,
        ];
        if ($ip !== '') {
            $payload['remoteip'] = $ip;
        }

        $ch = curl_init(self::VERIFY_URL);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            curl_close($ch);
            return false;
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            return false;
        }

        $decoded = json_decode((string) $body, true);

        return is_array($decoded) && ($decoded['success'] ?? false) === true;
    }
}
