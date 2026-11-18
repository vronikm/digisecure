<?php

declare(strict_types=1);

namespace DigiSecure\User\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Notification\Infrastructure\Channel\EmailChannel;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Crypto\Hasher;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Tenant\Domain\TenantContext;
use DigiSecure\User\Infrastructure\UserRepository;

/**
 * Caso de uso: alta, edición y cambios de estado de usuarios del tenant.
 *
 * Reglas de negocio:
 *  - Email único por tenant.
 *  - Contraseña inicial generada por el admin (forzar cambio en próximo login).
 *  - Los cambios de estado se auditan con el actor.
 *  - El usuario que realiza la acción no puede bloquearse a sí mismo.
 */
final class AdministrarUsuariosService
{
    public function __construct(
        private readonly UserRepository $repo,
        private readonly PdoConnection $db,
        private readonly Hasher $hasher,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
        private readonly EmailChannel $emailChannel,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id:string}
     */
    public function crear(
        array $input,
        string $actorId,
        string $ip,
    ): array
    {
        $email = trim((string) ($input['email'] ?? ''));
        $nombres = trim((string) ($input['nombres'] ?? ''));
        $apellidos = trim((string) ($input['apellidos'] ?? ''));
        $passwordPlano = (string) ($input['password'] ?? '');
        $cargo = trim((string) ($input['cargo'] ?? '')) ?: null;
        $telefono = trim((string) ($input['telefono'] ?? '')) ?: null;
        $estado = (string) ($input['estado'] ?? 'activo');
        $rolId = trim((string) ($input['role_id'] ?? ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Email inválido.');
        }
        if ($nombres === '' || $apellidos === '') {
            throw new DomainException('Nombres y apellidos son obligatorios.');
        }
        if (strlen($passwordPlano) < 8) {
            throw new DomainException('La contraseña inicial debe tener al menos 8 caracteres.');
        }
        if (!in_array($estado, ['activo', 'inactivo', 'bloqueado', 'pendiente_verificacion'], true)) {
            throw new DomainException('Estado inválido.');
        }
        if ($rolId === '') {
            throw new DomainException('Debe asignar al menos un rol.');
        }
        $now = Clock::nowString();
        $passwordHash = $this->hasher->hash($passwordPlano);
        if ($this->repo->emailExistsInTenant($email)) {
            throw new DomainException('Ya existe un usuario con ese email en esta empresa.');
        }
        $this->ensureRoleAccessible($rolId);

        $id = $this->repo->create([
            'email' => $email,
            'password_hash' => $passwordHash,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'cargo' => $cargo,
            'telefono' => $telefono,
            'estado' => $estado,
            'mfa_habilitado' => 0,
            'intentos_fallidos' => 0,
            'password_changed_at' => $now,
            'debe_cambiar_password' => 1,
            'created_by' => $actorId,
        ]);

        $this->repo->assignRole($id, $rolId, $actorId);

        $this->audit->log(
            'usuario.creado',
            $actorId,
            'usuario',
            $id,
            detalles: [
                'email' => $email,
                'role_id' => $rolId,
                'estado' => $estado,
            ],
            nivel: 'info',
            ip: $ip,
        );

        return ['id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function actualizar(
        string $id,
        array $input,
        string $actorId,
        string $ip,
    ): void
    {
        $usuario = $this->repo->findUserById($id);
        if ($usuario === null) {
            throw new DomainException('Usuario no encontrado.');
        }

        $email = trim((string) ($input['email'] ?? ''));
        $nombres = trim((string) ($input['nombres'] ?? ''));
        $apellidos = trim((string) ($input['apellidos'] ?? ''));
        $cargo = trim((string) ($input['cargo'] ?? '')) ?: null;
        $telefono = trim((string) ($input['telefono'] ?? '')) ?: null;

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Email inválido.');
        }
        if ($nombres === '' || $apellidos === '') {
            throw new DomainException('Nombres y apellidos son obligatorios.');
        }
        $rolId = trim((string) ($input['role_id'] ?? ''));

        if ($this->repo->emailExistsInTenant($email, $id)) {
            throw new DomainException('Otro usuario ya usa ese email.');
        }

        $this->repo->update($id, [
            'email' => $email,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'cargo' => $cargo,
            'telefono' => $telefono,
        ]);

        // Cambio de rol opcional (reemplaza todos los roles existentes por el nuevo)
        if ($rolId !== '') {
            $this->ensureRoleAccessible($rolId);
            $this->db->execute(
                'DELETE FROM usuarios_roles WHERE usuario_id = :uid',
                ['uid' => $id],
            );
            $this->repo->assignRole($id, $rolId, $actorId);
        }

        $this->audit->log(
            'usuario.actualizado',
            $actorId,
            'usuario',
            $id,
            detalles: [
                'email' => $email,
                'role_id' => $rolId ?: null,
            ],
            nivel: 'info',
            ip: $ip,
        );
    }

    public function cambiarEstado(string $id, string $nuevoEstado, string $actorId, string $ip): void
    {
        if (!in_array($nuevoEstado, ['activo', 'inactivo', 'bloqueado'], true)) {
            throw new DomainException('Estado inválido.');
        }
        if ($id === $actorId && $nuevoEstado !== 'activo') {
            throw new DomainException('No puedes desactivar o bloquear tu propia cuenta.');
        }
        $usuario = $this->repo->findUserById($id);
        if ($usuario === null) {
            throw new DomainException('Usuario no encontrado.');
        }

        $update = ['estado' => $nuevoEstado];
        // Si se reactiva, limpiar bloqueos
        if ($nuevoEstado === 'activo') {
            $update['intentos_fallidos'] = 0;
            $update['bloqueado_hasta'] = null;
        }
        $this->repo->update($id, $update);

        $this->audit->log(
            'usuario.estado_cambiado',
            $actorId,
            'usuario',
            $id,
            detalles: ['estado_anterior' => $usuario['estado'], 'estado_nuevo' => $nuevoEstado],
            nivel: $nuevoEstado === 'activo' ? 'info' : 'warning',
            ip: $ip,
        );
    }

    public function resetearPassword(
        string $id,
        string $passwordPlano,
        string $actorId,
        string $ip,
    ): bool
    {
        if (strlen($passwordPlano) < 8) {
            throw new DomainException('La contraseña debe tener al menos 8 caracteres.');
        }
        $usuario = $this->repo->findUserById($id);
        if ($usuario === null) {
            throw new DomainException('Usuario no encontrado.');
        }

        $updateData = [
            'password_hash' => $this->hasher->hash($passwordPlano),
            'password_changed_at' => Clock::nowString(),
            'debe_cambiar_password' => 1,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ];
        $this->repo->update($id, $updateData);

        $emailEnviado = $this->enviarPasswordTemporal($usuario, $passwordPlano);

        $this->audit->log(
            'usuario.password_reseteado',
            $actorId,
            'usuario',
            $id,
            detalles: ['email_notificado' => $emailEnviado],
            nivel: 'warning',
            ip: $ip,
        );

        return $emailEnviado;
    }

    /**
     * Envia la clave temporal sin pasar por el Mailer transaccional, porque ese
     * fallback registra el cuerpo del correo en logs cuando falla el envio.
     *
     * @param array<string, mixed> $usuario
     */
    private function enviarPasswordTemporal(array $usuario, string $passwordPlano): bool
    {
        $email = trim((string) ($usuario['email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $nombres = trim((string) ($usuario['nombres'] ?? ''));
        $apellidos = trim((string) ($usuario['apellidos'] ?? ''));
        $nombre = trim($nombres . ' ' . $apellidos);
        if ($nombre === '') {
            $nombre = $email;
        }

        $asunto = 'DigiSecure - contrasena temporal actualizada';
        $cuerpoTexto = implode("\n", [
            "Hola {$nombre},",
            '',
            'Un administrador actualizo tu contrasena temporal de DigiSecure.',
            "Usuario: {$email}",
            "Contrasena temporal: {$passwordPlano}",
            '',
            'Al iniciar sesion deberas cambiar esta contrasena.',
            'Si no solicitaste este cambio, contacta al administrador de tu empresa.',
        ]);

        $nombreHtml = htmlspecialchars($nombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $emailHtml = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $passwordHtml = htmlspecialchars($passwordPlano, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cuerpoHtml = <<<HTML
<!doctype html>
<html lang="es">
<body style="margin:0;padding:24px;background:#f6f8fb;font-family:Arial,sans-serif;color:#0f172a;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;">
    <h2 style="margin:0 0 12px;color:#0f3a63;">DigiSecure</h2>
    <p>Hola {$nombreHtml},</p>
    <p>Un administrador actualizo tu contrasena temporal de DigiSecure.</p>
    <p style="margin-bottom:6px;"><strong>Usuario:</strong> {$emailHtml}</p>
    <p style="margin-bottom:6px;"><strong>Contrasena temporal:</strong></p>
    <div style="font-size:18px;font-weight:700;letter-spacing:.04em;background:#f1f5f9;border-radius:8px;padding:12px 14px;">{$passwordHtml}</div>
    <p>Al iniciar sesion deberas cambiar esta contrasena.</p>
    <p style="font-size:13px;color:#64748b;">Si no solicitaste este cambio, contacta al administrador de tu empresa.</p>
  </div>
</body>
</html>
HTML;

        try {
            $resultado = $this->emailChannel->send([
                'destinatario_email' => $email,
                'destinatario_nombre' => $nombre,
                'asunto' => $asunto,
                'cuerpo_texto' => $cuerpoTexto,
                'cuerpo_html' => $cuerpoHtml,
            ]);

            return $resultado->success;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Verifica que el rol existe y es accesible (rol de sistema o del tenant actual).
     */
    private function ensureRoleAccessible(string $roleId): void
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $row = $this->db->fetchOne(
            'SELECT r.id
               FROM roles r
              WHERE r.id = :id
                AND r.codigo <> :codigo_superadmin
                AND (
                    r.tenant_id = :tid
                    OR (
                        r.tenant_id IS NULL
                        AND NOT EXISTS (
                            SELECT 1
                              FROM roles rt
                             WHERE rt.tenant_id = :tid_shadow
                               AND rt.codigo = r.codigo
                        )
                    )
                )',
            [
                'id' => $roleId,
                'tid' => $tenantId,
                'tid_shadow' => $tenantId,
                'codigo_superadmin' => 'superadmin',
            ],
        );
        if ($row === null) {
            throw new DomainException('Rol inválido o no accesible.');
        }
    }
}
