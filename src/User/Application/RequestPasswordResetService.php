<?php

declare(strict_types=1);

namespace DigiSecure\User\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Notification\Application\Mailer;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Solicita el envío de un email de recuperación de contraseña.
 *
 * Reglas de seguridad (Fase 10):
 *  - No revelar si el email existe o no (siempre responde igual)
 *  - Token de 32 bytes (64 hex chars) generado con random_bytes
 *  - Se almacena el hash SHA256 del token (nunca el token plano)
 *  - Expiración configurable, por defecto 60 min
 *  - Invalida cualquier token anterior del usuario
 *  - El envío se hace a través del EncolarNotificacionService (cola)
 *  - Rate limit por IP: máximo 5 solicitudes/hora
 */
final class RequestPasswordResetService
{
    private const TOKEN_BYTES = 32;
    private const DEFAULT_EXPIRATION_MIN = 60;
    private const RATE_LIMIT_PER_HOUR = 5;

    public function __construct(
        private readonly PdoConnection $db,
        private readonly Mailer $mailer,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
        private readonly Config $config,
    ) {
    }

    /**
     * Ejecuta la solicitud. Siempre devuelve el mismo resultado,
     * independientemente de si el email existe, para evitar user enumeration.
     */
    public function execute(string $email, string $ip, string $userAgent): void
    {
        $emailNorm = $this->normalizeEmail($email);

        if ($emailNorm === '' || filter_var($emailNorm, FILTER_VALIDATE_EMAIL) === false) {
            // Auditar intento inválido pero no lanzar excepción
            $this->audit->log(
                'password_reset.invalid_email',
                null,
                'usuario',
                null,
                detalles: ['email' => $emailNorm],
                nivel: 'warning',
                ip: $ip,
                userAgent: $userAgent,
            );
            return;
        }

        // Rate limit por IP (ventana de 1 hora)
        if ($this->rateLimited($ip)) {
            $this->audit->log(
                'password_reset.rate_limited',
                null,
                null,
                null,
                detalles: ['email' => $emailNorm],
                nivel: 'warning',
                ip: $ip,
                userAgent: $userAgent,
            );
            return;
        }

        $usuario = $this->db->fetchOne(
            'SELECT u.id, u.tenant_id, u.email, u.nombres, u.apellidos, u.estado,
                    t.razon_social AS tenant_nombre, t.estado AS tenant_estado
             FROM usuarios u
             INNER JOIN tenants t
                     ON t.id COLLATE utf8mb4_unicode_ci = u.tenant_id COLLATE utf8mb4_unicode_ci
             WHERE u.email_normalizado = :email',
            ['email' => $emailNorm],
        );

        // Si el usuario no existe o está bloqueado, no enviar email pero registrar
        if ($usuario === null) {
            $this->audit->log(
                'password_reset.email_no_registrado',
                null,
                null,
                null,
                detalles: ['email' => $emailNorm],
                nivel: 'info',
                ip: $ip,
                userAgent: $userAgent,
            );
            return;
        }

        if ($usuario['estado'] !== 'activo' || $usuario['tenant_estado'] !== 'activo') {
            $this->audit->log(
                'password_reset.cuenta_inactiva',
                $usuario['id'],
                'usuario',
                $usuario['id'],
                detalles: ['estado' => $usuario['estado'], 'tenant_estado' => $usuario['tenant_estado']],
                nivel: 'warning',
                ip: $ip,
                userAgent: $userAgent,
            );
            return;
        }

        // Generar token y guardar hash
        $tokenPlano = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash = hash('sha256', $tokenPlano);
        $expira = Clock::now()
            ->modify('+' . self::DEFAULT_EXPIRATION_MIN . ' minutes')
            ->format('Y-m-d H:i:s.u');

        $this->db->execute(
            'UPDATE usuarios SET
                token_recuperacion_hash = :hash,
                token_recuperacion_expira_at = :exp,
                updated_at = :now
             WHERE id = :id',
            [
                'hash' => $tokenHash,
                'exp' => $expira,
                'now' => Clock::nowString(),
                'id' => $usuario['id'],
            ],
        );

        // Encolar notificación email en el tenant del usuario
        $this->tenantContext->set(
            $usuario['tenant_id'],
            $usuario['tenant_nombre'],
            $usuario['tenant_estado'],
        );

        $appUrl = rtrim($this->config->string('app.url', ''), '/');
        $resetLink = $appUrl . '/password/reset/' . $tokenPlano;
        $nombreCompleto = trim(($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));
        if ($nombreCompleto === '') {
            $nombreCompleto = $usuario['email'];
        }

        $cuerpoHtml = $this->buildHtmlBody($nombreCompleto, $resetLink, self::DEFAULT_EXPIRATION_MIN);
        $cuerpoTexto = $this->buildTextBody($nombreCompleto, $resetLink, self::DEFAULT_EXPIRATION_MIN);

        // Envío síncrono (la cola tenant-aware solo se despacha por cron).
        $emailEnviado = $this->mailer->enviar(
            (string) $usuario['email'],
            $nombreCompleto,
            'DigiSecure · Recuperación de contraseña',
            $cuerpoTexto,
            $cuerpoHtml,
        );

        if (!$emailEnviado) {
            $this->audit->log(
                'password_reset.mail_failed',
                $usuario['id'],
                'usuario',
                $usuario['id'],
                detalles: ['email' => $usuario['email'], 'expira_at' => $expira],
                nivel: 'critical',
                ip: $ip,
                userAgent: $userAgent,
            );
            return;
        }

        $this->audit->log(
            'password_reset.solicitado',
            $usuario['id'],
            'usuario',
            $usuario['id'],
            detalles: ['email' => $usuario['email'], 'expira_at' => $expira],
            nivel: 'warning',
            ip: $ip,
            userAgent: $userAgent,
        );
    }

    private function rateLimited(string $ip): bool
    {
        $since = Clock::now()->modify('-1 hour')->format('Y-m-d H:i:s.u');
        $ipBinary = inet_pton($ip) ?: '';

        $count = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM auditoria
             WHERE evento IN ('password_reset.solicitado','password_reset.email_no_registrado','password_reset.cuenta_inactiva','password_reset.invalid_email')
               AND ip = :ip
               AND created_at >= :since",
            ['ip' => $ipBinary, 'since' => $since],
        );

        return $count >= self::RATE_LIMIT_PER_HOUR;
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    private function buildTextBody(string $nombre, string $resetLink, int $minutos): string
    {
        return <<<TEXT
Hola {$nombre},

Recibimos una solicitud para restablecer la contraseña de tu cuenta en DigiSecure.

Para crear una nueva contraseña, haz clic en el siguiente enlace:
{$resetLink}

Este enlace expira en {$minutos} minutos y solo puede usarse una vez.

Si no solicitaste este cambio, ignora este mensaje. Tu contraseña no será modificada.

— Equipo DigiSecure
TEXT;
    }

    private function buildHtmlBody(string $nombre, string $resetLink, int $minutos): string
    {
        $nombreEsc = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $linkEsc = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Recuperación de contraseña</title></head>
<body style="font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:24px;color:#2c3e50">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.08);overflow:hidden">
  <div style="background:#0d6efd;color:#fff;padding:20px 24px">
    <h2 style="margin:0;font-size:20px">🔒 DigiSecure · Recuperación de contraseña</h2>
  </div>
  <div style="padding:24px">
    <p>Hola <strong>{$nombreEsc}</strong>,</p>
    <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en DigiSecure.</p>
    <p style="margin:24px 0;text-align:center">
      <a href="{$linkEsc}" style="display:inline-block;background:#0d6efd;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:bold">
        Restablecer mi contraseña
      </a>
    </p>
    <p style="color:#6c757d;font-size:13px">Este enlace expira en <strong>{$minutos} minutos</strong> y solo puede usarse una vez.</p>
    <p style="color:#6c757d;font-size:13px">Si el botón no funciona, copia y pega esta URL en tu navegador:</p>
    <p style="word-break:break-all;font-size:12px;background:#f8f9fa;padding:8px 12px;border-radius:4px;color:#495057">{$linkEsc}</p>
    <hr style="border:none;border-top:1px solid #e9ecef;margin:24px 0">
    <p style="color:#6c757d;font-size:12px">
      Si tú no solicitaste este cambio, ignora este mensaje. Tu contraseña no será modificada.
    </p>
  </div>
  <div style="background:#f8f9fa;padding:12px 24px;font-size:11px;color:#6c757d;text-align:center">
    — Equipo DigiSecure · Seguridad privada para Ecuador
  </div>
</div>
</body>
</html>
HTML;
    }
}
