<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Infrastructure\Channel;

/**
 * Canal de envío por WhatsApp vía Meta Cloud API.
 *
 * Reference: https://developers.facebook.com/docs/whatsapp/cloud-api
 *
 * Diseño:
 *  - Si phoneNumberId o accessToken están vacíos, el canal devuelve fail
 *    inmediatamente sin lanzar excepción. Esto permite registrar el canal
 *    en DI sin obligar a tener proveedor configurado.
 *  - Sin dependencias externas: usa cURL nativo (ext-curl).
 *  - Por diseño Cloud API, en cuentas no business-verified solo se pueden
 *    enviar plantillas pre-aprobadas. Para el MVP enviamos texto libre,
 *    el cliente debe haber iniciado conversación o estar dentro de la
 *    ventana de 24 h. Cambiar a plantillas cuando sea necesario.
 */
final class WhatsAppChannel implements NotificationChannel
{
    private const API_VERSION = 'v20.0';
    private const BASE_URL = 'https://graph.facebook.com';

    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $accessToken,
        private readonly int $timeoutSeconds = 15,
    ) {
    }

    public function send(array $notificacion): ChannelResult
    {
        if ($this->phoneNumberId === '' || $this->accessToken === '') {
            return ChannelResult::fail(
                'WhatsApp no configurado (phoneNumberId/accessToken vacíos)',
                'NOT_CONFIGURED',
            );
        }

        $to = $this->normalizePhone((string) ($notificacion['destinatario_telefono'] ?? ''));
        if ($to === null) {
            return ChannelResult::fail('Destinatario telefono inválido', 'INVALID_RECIPIENT');
        }

        $body = (string) ($notificacion['cuerpo_texto'] ?? '');
        if ($body === '' && isset($notificacion['cuerpo_html'])) {
            $body = trim(strip_tags((string) $notificacion['cuerpo_html']));
        }
        if ($body === '') {
            return ChannelResult::fail('Notificación sin cuerpo de texto', 'EMPTY_BODY');
        }

        $url = sprintf('%s/%s/%s/messages', self::BASE_URL, self::API_VERSION, $this->phoneNumberId);
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => mb_substr($body, 0, 4096),
            ],
        ];

        $start = (int) (microtime(true) * 1000);
        $result = $this->httpPost($url, $payload);
        $elapsed = (int) (microtime(true) * 1000) - $start;

        return new ChannelResult(
            success: $result['ok'],
            codigo: $result['code'] ?? null,
            mensaje: $result['msg'] ?? null,
            duracionMs: $elapsed,
        );
    }

    private function normalizePhone(string $raw): ?string
    {
        // Meta Cloud API espera dígitos sin '+', con código país
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) < 8) {
            return null;
        }
        return $digits;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool, code?:string, msg?:string}
     */
    private function httpPost(string $url, array $payload): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'code' => 'CURL_INIT_FAIL', 'msg' => 'curl_init falló'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'code' => 'CURL_ERROR', 'msg' => $err];
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 200 && $status < 300) {
            $decoded = json_decode((string) $body, true);
            $msgId = is_array($decoded) && isset($decoded['messages'][0]['id'])
                ? (string) $decoded['messages'][0]['id']
                : 'OK';
            return ['ok' => true, 'code' => 'WA_' . $status, 'msg' => $msgId];
        }

        return [
            'ok' => false,
            'code' => 'WA_' . $status,
            'msg' => mb_substr((string) $body, 0, 480),
        ];
    }
}
