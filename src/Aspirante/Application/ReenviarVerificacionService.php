<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Application;

use DigiSecure\Aspirante\Infrastructure\AspiranteEventLogger;
use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Aspirante\Infrastructure\VerificationMailer;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Shared\Infrastructure\Clock;

/**
 * Reenvía el enlace de verificación a un aspirante que aún no validó su correo.
 *
 * Seguridad (OWASP / LOPDP):
 *  - NO revela si el correo existe (anti-enumeración): el resultado es siempre
 *    neutro; el controlador muestra el mismo mensaje exista o no.
 *  - Rate-limit por correo e IP (anti-abuso).
 *  - Token nuevo con random_bytes(); solo se guarda su hash SHA-256; un solo uso;
 *    vigencia 48h.
 *  - Audita reenvio_link y link_enviado.
 */
final class ReenviarVerificacionService
{
    private const TOKEN_BYTES = 32;
    private const TOKEN_EXP_HORAS = 48;
    private const RATE_VENTANA_MIN = 60;
    private const RATE_MAX = 3;

    public function __construct(
        private readonly AspiranteRepository $repo,
        private readonly AspiranteEventLogger $eventos,
        private readonly VerificationMailer $mailer,
        private readonly Config $config,
    ) {
    }

    public function execute(string $correoRaw, string $ip, string $userAgent): void
    {
        $correoNorm = mb_strtolower(trim($correoRaw), 'UTF-8');
        if ($correoNorm === '' || filter_var($correoNorm, FILTER_VALIDATE_EMAIL) === false) {
            return; // Neutral: entrada inválida no revela nada.
        }

        // Rate-limit anti-abuso (silencioso para no dar señal).
        if ($this->eventos->contarReenviosRecientes($correoNorm, $ip, self::RATE_VENTANA_MIN) >= self::RATE_MAX) {
            return;
        }

        $aspirante = $this->repo->findPendientePorCorreo($correoNorm);
        if ($aspirante === null) {
            return; // No existe o ya validó: no revelar (anti-enumeración).
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $now = Clock::now();
        $this->repo->regenerarTokenVerificacion(
            $aspirante['id'],
            hash('sha256', $token),
            $now->modify('+' . self::TOKEN_EXP_HORAS . ' hours')->format('Y-m-d H:i:s.u'),
            $now->format('Y-m-d H:i:s.u'),
        );

        $this->eventos->registrar(AspiranteEventLogger::REENVIO_LINK, $correoNorm, $aspirante['id'], $ip, $userAgent);

        $enlace = rtrim($this->config->string('app.url', ''), '/') . '/verificar?token=' . $token;
        $nombre = trim($aspirante['nombres'] . ' ' . $aspirante['apellidos']);
        $this->mailer->enviarVerificacion($aspirante['correo'], $nombre, $enlace);

        $this->eventos->registrar(AspiranteEventLogger::LINK_ENVIADO, $correoNorm, $aspirante['id'], $ip, $userAgent);
    }
}
