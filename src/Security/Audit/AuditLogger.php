<?php

declare(strict_types=1);

namespace DigiSecure\Security\Audit;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Logger de auditoría para acciones sensibles.
 *
 * Escribe directamente a la tabla `auditoria`. NO usa BaseRepository
 * porque debe funcionar incluso sin contexto de tenant (ej: login fallido
 * de un email que no existe en ningún tenant).
 *
 * Requisito LOPDP: toda acción sensible se registra con quién, qué, cuándo e IP.
 * Los registros son INMUTABLES — no se borran, no se editan.
 */
final class AuditLogger
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Registra un evento de auditoría.
     *
     * @param string      $evento       Código del evento (ej: 'auth.login_ok', 'ficha.crear')
     * @param string|null $usuarioId    ID del usuario que ejecutó la acción
     * @param string|null $entidadTipo  Tipo de entidad afectada (ej: 'usuario', 'ficha')
     * @param string|null $entidadId    ID de la entidad afectada
     * @param array<string, mixed>|null $detalles  Datos adicionales (se serializa a JSON)
     * @param string      $nivel        'info', 'warning', 'critical'
     */
    public function log(
        string $evento,
        ?string $usuarioId = null,
        ?string $entidadTipo = null,
        ?string $entidadId = null,
        ?array $detalles = null,
        string $nivel = 'info',
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        $tenantId = $this->tenantContext->currentTenantId();

        $ipBinary = null;
        if ($ip !== null) {
            $packed = inet_pton($ip);
            $ipBinary = $packed !== false ? $packed : null;
        }

        $this->db->execute(
            'INSERT INTO auditoria
                (tenant_id, usuario_id, evento, entidad_tipo, entidad_id, ip, user_agent, detalles_json, nivel, created_at)
             VALUES
                (:tenant_id, :usuario_id, :evento, :entidad_tipo, :entidad_id, :ip, :user_agent, :detalles_json, :nivel, :created_at)',
            [
                'tenant_id' => $tenantId,
                'usuario_id' => $usuarioId,
                'evento' => $evento,
                'entidad_tipo' => $entidadTipo,
                'entidad_id' => $entidadId,
                'ip' => $ipBinary,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
                'detalles_json' => $detalles !== null ? json_encode($detalles, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
                'nivel' => $nivel,
                'created_at' => Clock::nowString(),
            ],
        );
    }

    /**
     * Atajos para eventos frecuentes.
     */
    public function loginExitoso(string $usuarioId, string $ip, string $userAgent): void
    {
        $this->log('auth.login_ok', $usuarioId, 'usuario', $usuarioId, ip: $ip, userAgent: $userAgent);
    }

    public function loginFallido(string $emailIntentado, string $motivo, string $ip, string $userAgent): void
    {
        $this->log(
            'auth.login_fallido',
            detalles: ['email' => $emailIntentado, 'motivo' => $motivo],
            nivel: 'warning',
            ip: $ip,
            userAgent: $userAgent,
        );
    }

    public function logout(string $usuarioId, string $ip): void
    {
        $this->log('auth.logout', $usuarioId, 'usuario', $usuarioId, ip: $ip);
    }

    public function accesoProhibido(string $usuarioId, string $permiso, string $ip): void
    {
        $this->log(
            'auth.acceso_prohibido',
            $usuarioId,
            detalles: ['permiso_requerido' => $permiso],
            nivel: 'warning',
            ip: $ip,
        );
    }
}
