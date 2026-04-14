<?php

declare(strict_types=1);

namespace DigiSecure\User\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Crypto\Hasher;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Ejecuta el reseteo de contraseña a partir de un token válido.
 *
 * Reglas:
 *  - Token se valida con hash SHA256 (timing-safe hash_equals)
 *  - Token expirado → rechaza
 *  - Token single-use: se invalida después del reset
 *  - Contraseña mínima 8 caracteres
 *  - No puede coincidir con la contraseña anterior
 *  - Se invalidan intentos fallidos y bloqueos del usuario
 *  - Se audita con nivel warning
 */
final class ResetPasswordService
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly Hasher $hasher,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Valida un token sin consumirlo. Usado por la pantalla de reset para
     * mostrar el formulario solo si el token es válido.
     *
     * @return array{id:string,tenant_id:string,email:string}|null
     */
    public function validateToken(string $tokenPlano): ?array
    {
        if ($tokenPlano === '' || !ctype_xdigit($tokenPlano) || strlen($tokenPlano) !== 64) {
            return null;
        }

        $tokenHash = hash('sha256', $tokenPlano);
        $usuario = $this->db->fetchOne(
            'SELECT u.id, u.tenant_id, u.email, u.token_recuperacion_expira_at,
                    t.estado AS tenant_estado, t.razon_social AS tenant_nombre
             FROM usuarios u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE u.token_recuperacion_hash = :hash AND u.estado = :estado',
            ['hash' => $tokenHash, 'estado' => 'activo'],
        );

        if ($usuario === null) {
            return null;
        }
        if ($usuario['tenant_estado'] !== 'activo') {
            return null;
        }
        if ($usuario['token_recuperacion_expira_at'] === null) {
            return null;
        }
        if (new \DateTimeImmutable($usuario['token_recuperacion_expira_at']) < Clock::now()) {
            return null;
        }

        return [
            'id' => $usuario['id'],
            'tenant_id' => $usuario['tenant_id'],
            'tenant_nombre' => $usuario['tenant_nombre'],
            'email' => $usuario['email'],
        ];
    }

    /**
     * Consume el token y establece la nueva contraseña.
     */
    public function execute(string $tokenPlano, string $passwordNuevo, string $passwordConfirm, string $ip, string $userAgent): void
    {
        if ($passwordNuevo !== $passwordConfirm) {
            throw new DomainException('Las contraseñas no coinciden.');
        }
        if (strlen($passwordNuevo) < 8) {
            throw new DomainException('La contraseña debe tener al menos 8 caracteres.');
        }
        if (!preg_match('/[A-Za-z]/', $passwordNuevo) || !preg_match('/\d/', $passwordNuevo)) {
            throw new DomainException('La contraseña debe contener al menos una letra y un número.');
        }

        $usuario = $this->validateToken($tokenPlano);
        if ($usuario === null) {
            $this->audit->log(
                'password_reset.token_invalido',
                null,
                null,
                null,
                nivel: 'warning',
                ip: $ip,
                userAgent: $userAgent,
            );
            throw new DomainException('El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.');
        }

        // Cargar hash anterior para evitar reutilización
        $passwordHashAnterior = (string) $this->db->fetchScalar(
            'SELECT password_hash FROM usuarios WHERE id = :id',
            ['id' => $usuario['id']],
        );

        if ($passwordHashAnterior !== '' && $this->hasher->verify($passwordNuevo, $passwordHashAnterior)) {
            throw new DomainException('La nueva contraseña debe ser distinta a la anterior.');
        }

        $this->tenantContext->set($usuario['tenant_id'], $usuario['tenant_nombre'], 'activo');

        $this->db->execute(
            'UPDATE usuarios SET
                password_hash = :hash,
                password_changed_at = :now,
                debe_cambiar_password = 0,
                token_recuperacion_hash = NULL,
                token_recuperacion_expira_at = NULL,
                intentos_fallidos = 0,
                bloqueado_hasta = NULL,
                updated_at = :now
             WHERE id = :id',
            [
                'hash' => $this->hasher->hash($passwordNuevo),
                'now' => Clock::nowString(),
                'id' => $usuario['id'],
            ],
        );

        $this->audit->log(
            'password_reset.completado',
            $usuario['id'],
            'usuario',
            $usuario['id'],
            detalles: ['email' => $usuario['email']],
            nivel: 'warning',
            ip: $ip,
            userAgent: $userAgent,
        );
    }
}
