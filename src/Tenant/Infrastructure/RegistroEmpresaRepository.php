<?php

declare(strict_types=1);

namespace DigiSecure\Tenant\Infrastructure;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;

/**
 * Repositorio para registros_empresa: audit trail de intentos de registro
 * público. A diferencia de la tabla `auditoria`, aquí el tenant_id puede
 * ser NULL porque en la primera fase del alta el tenant aún no existe.
 */
final class RegistroEmpresaRepository
{
    public function __construct(
        private readonly PdoConnection $db,
    ) {
    }

    /**
     * @param array<string, mixed>|null $detalles
     */
    public function registrar(
        string $evento,
        string $emailAdmin,
        string $ruc,
        string $razonSocial,
        ?string $tenantId = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?array $detalles = null,
    ): void {
        $ipBinary = null;
        if ($ip !== null) {
            $packed = @inet_pton($ip);
            $ipBinary = $packed !== false ? $packed : null;
        }

        $this->db->execute(
            'INSERT INTO registros_empresa
                (tenant_id, email_admin, ruc, razon_social, ip, user_agent, evento, detalles_json, created_at)
             VALUES
                (:tid, :em, :ruc, :rs, :ip, :ua, :ev, :det, :ca)',
            [
                'tid' => $tenantId,
                'em'  => $emailAdmin,
                'ruc' => $ruc,
                'rs'  => $razonSocial,
                'ip'  => $ipBinary,
                'ua'  => $userAgent,
                'ev'  => $evento,
                'det' => $detalles !== null ? json_encode($detalles, JSON_UNESCAPED_UNICODE) : null,
                'ca'  => Clock::nowString(),
            ],
        );
    }

    /**
     * Cuenta intentos recientes desde una IP o email. Útil para rate limit.
     */
    public function contarIntentosRecientes(string $ip, int $ventanaMinutos = 60): int
    {
        $ipBinary = @inet_pton($ip);
        if ($ipBinary === false) {
            return 0;
        }

        $desde = Clock::now()->modify("-{$ventanaMinutos} minutes")->format('Y-m-d H:i:s.u');

        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM registros_empresa
              WHERE ip = :ip
                AND evento = 'creado'
                AND created_at >= :desde",
            ['ip' => $ipBinary, 'desde' => $desde],
        );
    }

    public function existeRucActivo(string $ruc): bool
    {
        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM tenants
              WHERE ruc = :ruc
                AND estado IN ('activo','pendiente','suspendido')",
            ['ruc' => $ruc],
        ) > 0;
    }

    public function existeEmailAdmin(string $emailNormalizado): bool
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM usuarios WHERE email_normalizado = :em',
            ['em' => $emailNormalizado],
        ) > 0;
    }
}
