<?php

declare(strict_types=1);

namespace DigiSecure\Tenant\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Tenant\Infrastructure\RegistroEmpresaRepository;

/**
 * Activa un tenant a partir del token de activación enviado por email.
 *
 * Pasos:
 *  1. Valida formato del token (64 hex chars)
 *  2. Hashea y busca el tenant con token_activacion_hash coincidente
 *  3. Verifica expiración
 *  4. Dentro de una transacción:
 *     - tenants.estado = 'activo', activado_at = NOW, limpia token
 *     - usuarios con tenant_id y estado 'pendiente_verificacion' → 'activo'
 *  5. Registra evento 'activado' en registros_empresa
 *
 * Devuelve datos para redirigir al login con un mensaje de bienvenida.
 */
final class ActivarTenantService
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly RegistroEmpresaRepository $registrosRepo,
    ) {
    }

    /**
     * @return array{tenant_id: string, razon_social: string, email_admin: string}
     */
    public function execute(string $tokenPlano, string $ip, string $userAgent): array
    {
        $tokenPlano = trim($tokenPlano);
        if ($tokenPlano === '' || !preg_match('/^[a-f0-9]{64}$/', $tokenPlano)) {
            throw new DomainException('El enlace de activación no es válido.');
        }

        $tokenHash = hash('sha256', $tokenPlano);

        $tenant = $this->db->fetchOne(
            'SELECT id, razon_social, ruc, email_contacto, estado,
                    token_activacion_expira_at, activado_at
               FROM tenants
              WHERE token_activacion_hash = :h
              LIMIT 1',
            ['h' => $tokenHash],
        );

        if ($tenant === null) {
            throw new DomainException('El enlace de activación no es válido o ya fue utilizado.');
        }

        if ($tenant['activado_at'] !== null && $tenant['estado'] === 'activo') {
            // Idempotente: si ya está activo, no error — redirigimos al login.
            return [
                'tenant_id' => (string) $tenant['id'],
                'razon_social' => (string) $tenant['razon_social'],
                'email_admin' => (string) $tenant['email_contacto'],
            ];
        }

        $expira = $tenant['token_activacion_expira_at'];
        if ($expira !== null && strtotime((string) $expira) < time()) {
            $this->registrosRepo->registrar(
                evento: 'expirado',
                emailAdmin: (string) $tenant['email_contacto'],
                ruc: (string) $tenant['ruc'],
                razonSocial: (string) $tenant['razon_social'],
                tenantId: (string) $tenant['id'],
                ip: $ip,
                userAgent: $userAgent,
                detalles: ['expiro_at' => (string) $expira],
            );
            throw new DomainException('El enlace de activación expiró. Solicita uno nuevo desde el formulario de registro.');
        }

        if ($tenant['estado'] === 'cancelado' || $tenant['estado'] === 'suspendido') {
            throw new DomainException('Esta cuenta no puede activarse. Contacta soporte.');
        }

        $tenantId = (string) $tenant['id'];
        $now = Clock::nowString();

        $this->db->transactional(function () use ($tenantId, $now): void {
            $this->db->execute(
                "UPDATE tenants
                    SET estado = 'activo',
                        activado_at = :now,
                        token_activacion_hash = NULL,
                        token_activacion_expira_at = NULL,
                        updated_at = :now
                  WHERE id = :id",
                ['id' => $tenantId, 'now' => $now],
            );

            $this->db->execute(
                "UPDATE usuarios
                    SET estado = 'activo',
                        updated_at = :now
                  WHERE tenant_id = :tid
                    AND estado = 'pendiente_verificacion'",
                ['tid' => $tenantId, 'now' => $now],
            );
        });

        $this->registrosRepo->registrar(
            evento: 'activado',
            emailAdmin: (string) $tenant['email_contacto'],
            ruc: (string) $tenant['ruc'],
            razonSocial: (string) $tenant['razon_social'],
            tenantId: $tenantId,
            ip: $ip,
            userAgent: $userAgent,
        );

        return [
            'tenant_id' => $tenantId,
            'razon_social' => (string) $tenant['razon_social'],
            'email_admin' => (string) $tenant['email_contacto'],
        ];
    }
}
