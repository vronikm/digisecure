<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Infrastructure;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;

/**
 * Bitacora append-only del ciclo de registro del aspirante (sin tenant).
 *
 * A diferencia de `auditoria` (que exige tenant_id), estos eventos ocurren en
 * el flujo PUBLICO de registro, antes de que el aspirante tenga tenant. Por eso
 * viven en su propia tabla `aspirante_eventos`.
 *
 * Eventos: registro_creado, link_enviado, link_abierto, correo_validado,
 *          registro_completado, token_expirado, reenvio_link,
 *          datos_actualizados (corrección desde reclutamiento, migración 034).
 */
final class AspiranteEventLogger
{
    public const REGISTRO_CREADO = 'registro_creado';
    public const LINK_ENVIADO = 'link_enviado';
    public const LINK_ABIERTO = 'link_abierto';
    public const CORREO_VALIDADO = 'correo_validado';
    public const REGISTRO_COMPLETADO = 'registro_completado';
    public const TOKEN_EXPIRADO = 'token_expirado';
    public const REENVIO_LINK = 'reenvio_link';
    public const DATOS_ACTUALIZADOS = 'datos_actualizados';

    public function __construct(
        private readonly PdoConnection $db,
    ) {
    }

    /**
     * @param array<string, mixed>|null $detalles
     */
    public function registrar(
        string $evento,
        ?string $email = null,
        ?string $aspiranteId = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?array $detalles = null,
    ): void {
        $ipBinary = null;
        if ($ip !== null && $ip !== '') {
            $packed = @inet_pton($ip);
            $ipBinary = $packed !== false ? $packed : null;
        }

        try {
            $this->db->execute(
                'INSERT INTO aspirante_eventos
                    (aspirante_id, email, evento, ip, user_agent, detalles_json, created_at)
                 VALUES
                    (:aid, :email, :evento, :ip, :ua, :det, :ca)',
                [
                    'aid'    => $aspiranteId,
                    'email'  => $email !== null ? mb_substr($email, 0, 190) : null,
                    'evento' => $evento,
                    'ip'     => $ipBinary,
                    'ua'     => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
                    'det'    => $detalles !== null ? json_encode($detalles, JSON_UNESCAPED_UNICODE) : null,
                    'ca'     => Clock::nowString(),
                ],
            );
        } catch (\Throwable $e) {
            // La bitácora es best-effort: NUNCA debe romper el flujo del usuario
            // (p. ej. si la tabla aún no existe). Se reporta al error_log.
            error_log('[DIGISECURE][ASPIRANTE_EVENTO] No se pudo registrar "' . $evento . '": ' . $e->getMessage());
        }
    }

    /**
     * Cuenta reenvios recientes para un correo O una IP (rate-limit anti-abuso).
     */
    public function contarReenviosRecientes(string $emailNormalizado, string $ip, int $ventanaMinutos): int
    {
        $desde = Clock::now()->modify("-{$ventanaMinutos} minutes")->format('Y-m-d H:i:s.u');
        $ipBinary = @inet_pton($ip);

        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM aspirante_eventos
              WHERE evento = '" . self::REENVIO_LINK . "'
                AND created_at >= :desde
                AND (email = :email" . ($ipBinary !== false ? ' OR ip = :ip' : '') . ')',
            $ipBinary !== false
                ? ['desde' => $desde, 'email' => $emailNormalizado, 'ip' => $ipBinary]
                : ['desde' => $desde, 'email' => $emailNormalizado],
        );
    }
}
