<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Rbac\Authorizer;
use DigiSecure\Security\Session\SessionManager;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Servicio central de autenticación.
 *
 * Responsabilidades:
 *  1. Validar credenciales (email + password)
 *  2. Rate limiting por IP+email (tabla intentos_login)
 *  3. Resolver el tenant del usuario
 *  4. Cargar permisos y roles del usuario
 *  5. Establecer sesión con datos del usuario autenticado
 *  6. Registrar en auditoría
 *  7. Manejar logout seguro
 *
 * NO usa BaseRepository porque el login ocurre ANTES de tener contexto de tenant.
 * Las queries filtran por email normalizado y luego resuelven el tenant.
 */
final class Authenticator
{
    private const SESSION_USER_KEY = '_digisecure.auth_user';
    private const SESSION_USER_SYNC_AT_KEY = '_digisecure.auth_user_sync_at';
    private const SESSION_USER_SYNC_INTERVAL_SECONDS = 120;
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function __construct(
        private readonly PdoConnection $db,
        private readonly SessionManager $session,
        private readonly Authorizer $authorizer,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Intenta autenticar al usuario con email y password.
     *
     * Flujo:
     *  1. Normalizar email
     *  2. Verificar rate limit (IP + email)
     *  3. Buscar usuario por email normalizado
     *  4. Verificar password con password_verify (timing-safe)
     *  5. Verificar estado del usuario y del tenant
     *  6. Registrar intento exitoso
     *  7. Resolver tenant → establecer TenantContext
     *  8. Cargar permisos/roles
     *  9. Crear AuthenticatedUser y guardar en sesión
     * 10. Regenerar session_id (anti-fixation)
     *
     * @throws AuthenticationException
     */
    public function login(string $email, string $password, string $ip, string $userAgent): AuthenticatedUser
    {
        $emailNorm = $this->normalizeEmail($email);

        // 1. Rate limiting
        $this->checkRateLimit($emailNorm, $ip);

        // 2. Buscar usuario (puede estar en cualquier tenant)
        $usuario = $this->db->fetchOne(
            'SELECT u.id, u.tenant_id, u.email, u.password_hash, u.nombres, u.apellidos,
                    u.estado, u.intentos_fallidos, u.bloqueado_hasta, u.debe_cambiar_password,
                    t.razon_social AS tenant_nombre, t.estado AS tenant_estado
             FROM usuarios u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email_normalizado = :email_normalizado',
            ['email_normalizado' => $emailNorm],
        );

        // 3. Siempre ejecutar password_verify para evitar timing attacks
        //    (si no hay usuario, verificamos contra un hash dummy)
        $hashToVerify = $usuario['password_hash']
            ?? '$argon2id$v=19$m=65536,t=4,p=2$dW5rbm93bg$dW5rbm93bg';
        $passwordValid = password_verify($password, $hashToVerify);

        if ($usuario === null || !$passwordValid) {
            $this->registrarIntentoFallido($emailNorm, $ip, $userAgent, $usuario['id'] ?? null, $usuario['tenant_id'] ?? null);

            if ($usuario !== null) {
                $this->incrementarIntentosFallidos($usuario['id']);
            }

            throw new AuthenticationException(AuthenticationException::INVALID_CREDENTIALS);
        }

        // 4. Verificar bloqueo temporal
        if ($usuario['bloqueado_hasta'] !== null) {
            $bloqueadoHasta = new \DateTimeImmutable($usuario['bloqueado_hasta']);
            if ($bloqueadoHasta > Clock::now()) {
                $this->registrarIntentoFallido($emailNorm, $ip, $userAgent, $usuario['id'], $usuario['tenant_id']);
                throw new AuthenticationException(AuthenticationException::ACCOUNT_BLOCKED);
            }
        }

        // 5. Verificar estado del usuario
        if ($usuario['estado'] !== 'activo') {
            $this->registrarIntentoFallido($emailNorm, $ip, $userAgent, $usuario['id'], $usuario['tenant_id']);
            throw new AuthenticationException(AuthenticationException::ACCOUNT_INACTIVE);
        }

        // 6. Verificar estado del tenant.
        //    Excepción: el superadmin de sistema puede ingresar aunque su tenant
        //    esté suspendido/cancelado para no perder control operativo global.
        $isSystemSuperadmin = $this->userHasSystemSuperadminRole((string) $usuario['id']);
        if ($usuario['tenant_estado'] !== 'activo' && !$isSystemSuperadmin) {
            $this->registrarIntentoFallido($emailNorm, $ip, $userAgent, $usuario['id'], $usuario['tenant_id']);
            throw new AuthenticationException(AuthenticationException::TENANT_SUSPENDED);
        }

        // 7. Login exitoso — establecer contexto de tenant
        $this->tenantContext->set(
            $usuario['tenant_id'],
            $usuario['tenant_nombre'],
            $usuario['tenant_estado'],
        );

        // 8. Cargar permisos y roles
        $permisos = $this->authorizer->loadPermisosUsuario($usuario['id']);
        $roles = $this->authorizer->loadRolesUsuario($usuario['id']);

        // 9. Crear DTO del usuario autenticado
        $authUser = new AuthenticatedUser(
            id: $usuario['id'],
            tenantId: $usuario['tenant_id'],
            email: $usuario['email'],
            nombres: $usuario['nombres'],
            apellidos: $usuario['apellidos'],
            estado: $usuario['estado'],
            tenantNombre: $usuario['tenant_nombre'],
            tenantEstado: $usuario['tenant_estado'],
            permisos: $permisos,
            roles: $roles,
        );

        // 10. Resetear intentos fallidos
        $this->resetearIntentosFallidos($usuario['id']);

        // 11. Actualizar último login
        $this->db->execute(
            'UPDATE usuarios SET ultimo_login_at = :now, ultimo_login_ip = :ip, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id',
            [
                'now' => Clock::nowString(),
                'ip' => inet_pton($ip) ?: null,
                'id' => $usuario['id'],
            ],
        );

        // 12. Registrar intento exitoso
        $this->registrarIntentoExitoso($emailNorm, $ip, $userAgent, $usuario['id'], $usuario['tenant_id']);

        // 13. Guardar en sesión + regenerar ID (anti-fixation)
        $this->session->regenerateId(true);
        $this->session->set(self::SESSION_USER_KEY, $authUser->toSessionArray());
        $this->session->set(self::SESSION_USER_SYNC_AT_KEY, time());

        // 14. Auditoría
        $this->audit->loginExitoso($usuario['id'], $ip, $userAgent);

        // 15. Si debe cambiar password, permitir login pero marcar
        if ($usuario['debe_cambiar_password']) {
            $this->session->set('_digisecure.must_change_password', true);
        }

        return $authUser;
    }

    /**
     * Cierra la sesión del usuario actual.
     */
    public function logout(string $ip): void
    {
        $user = $this->currentUser();
        if ($user !== null) {
            $this->audit->logout($user->id, $ip);
        }
        $this->session->remove(self::SESSION_USER_SYNC_AT_KEY);
        $this->session->destroy();
    }

    /**
     * Obtiene el usuario autenticado del request actual desde la sesión.
     */
    public function currentUser(): ?AuthenticatedUser
    {
        $data = $this->session->get(self::SESSION_USER_KEY);
        if (!is_array($data) || !isset($data['id'])) {
            return null;
        }

        return AuthenticatedUser::fromSessionArray($data);
    }

    /**
     * Sincroniza roles/permisos en sesión para reflejar cambios RBAC sin forzar relogin.
     * Hace refresh como máximo cada SESSION_USER_SYNC_INTERVAL_SECONDS.
     */
    public function syncSessionUser(AuthenticatedUser $user): AuthenticatedUser
    {
        $lastSyncAt = (int) $this->session->get(self::SESSION_USER_SYNC_AT_KEY, 0);
        $now = time();

        if (($now - $lastSyncAt) < self::SESSION_USER_SYNC_INTERVAL_SECONDS) {
            return $user;
        }

        $permisos = array_column($this->db->fetchAll(
            'SELECT DISTINCT p.codigo
               FROM usuarios_roles ur
               INNER JOIN roles r ON r.id = ur.role_id
               INNER JOIN roles_permisos rp ON rp.role_id = r.id
               INNER JOIN permisos p ON p.id = rp.permiso_id
              WHERE ur.usuario_id = :usuario_id
                AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)',
            [
                'usuario_id' => $user->id,
                'tenant_id' => $user->tenantId,
            ],
        ), 'codigo');

        $roles = $this->db->fetchAll(
            'SELECT r.codigo, r.nombre
               FROM usuarios_roles ur
               INNER JOIN roles r ON r.id = ur.role_id
              WHERE ur.usuario_id = :usuario_id
                AND (r.tenant_id = :tenant_id OR r.tenant_id IS NULL)',
            [
                'usuario_id' => $user->id,
                'tenant_id' => $user->tenantId,
            ],
        );

        $this->session->set(self::SESSION_USER_SYNC_AT_KEY, $now);

        if ($permisos === $user->permisos && $roles === $user->roles) {
            return $user;
        }

        $updated = new AuthenticatedUser(
            id: $user->id,
            tenantId: $user->tenantId,
            email: $user->email,
            nombres: $user->nombres,
            apellidos: $user->apellidos,
            estado: $user->estado,
            tenantNombre: $user->tenantNombre,
            tenantEstado: $user->tenantEstado,
            permisos: $permisos,
            roles: $roles,
        );

        $this->session->set(self::SESSION_USER_KEY, $updated->toSessionArray());

        return $updated;
    }

    /**
     * Verifica si hay un usuario autenticado.
     */
    public function isAuthenticated(): bool
    {
        return $this->currentUser() !== null;
    }

    /**
     * Verifica rate limit: máximo N intentos fallidos en los últimos M minutos.
     */
    private function checkRateLimit(string $emailNorm, string $ip): void
    {
        $since = Clock::now()->modify(sprintf('-%d minutes', self::LOCKOUT_MINUTES))->format('Y-m-d H:i:s.u');
        $ipBinary = inet_pton($ip) ?: '';

        // Contar intentos fallidos por email O por IP en la ventana
        $count = (int)$this->db->fetchScalar(
            'SELECT COUNT(*) FROM intentos_login
             WHERE exitoso = 0
               AND created_at >= :since
               AND (email_normalizado = :email OR ip = :ip)',
            [
                'since' => $since,
                'email' => $emailNorm,
                'ip' => $ipBinary,
            ],
        );

        if ($count >= self::MAX_ATTEMPTS) {
            throw new AuthenticationException(AuthenticationException::RATE_LIMITED);
        }
    }

    private function registrarIntentoFallido(
        string $emailNorm,
        string $ip,
        string $userAgent,
        ?string $usuarioId,
        ?string $tenantId,
    ): void {
        $ipBinary = inet_pton($ip) ?: null;

        $this->db->execute(
            'INSERT INTO intentos_login
                (tenant_id, usuario_id, email_intentado, email_normalizado, ip, user_agent, exitoso, motivo_fallo, created_at)
             VALUES
                (:tenant_id, :usuario_id, :email_intentado, :email_normalizado, :ip, :user_agent, 0, :motivo, :created_at)',
            [
                'tenant_id' => $tenantId,
                'usuario_id' => $usuarioId,
                'email_intentado' => $emailNorm,
                'email_normalizado' => $emailNorm,
                'ip' => $ipBinary,
                'user_agent' => mb_substr($userAgent, 0, 500),
                'motivo' => 'credenciales_invalidas',
                'created_at' => Clock::nowString(),
            ],
        );

        $this->audit->loginFallido($emailNorm, 'credenciales_invalidas', $ip, $userAgent);
    }

    private function registrarIntentoExitoso(
        string $emailNorm,
        string $ip,
        string $userAgent,
        string $usuarioId,
        string $tenantId,
    ): void {
        $ipBinary = inet_pton($ip) ?: null;

        $this->db->execute(
            'INSERT INTO intentos_login
                (tenant_id, usuario_id, email_intentado, email_normalizado, ip, user_agent, exitoso, created_at)
             VALUES
                (:tenant_id, :usuario_id, :email_intentado, :email_normalizado, :ip, :user_agent, 1, :created_at)',
            [
                'tenant_id' => $tenantId,
                'usuario_id' => $usuarioId,
                'email_intentado' => $emailNorm,
                'email_normalizado' => $emailNorm,
                'ip' => $ipBinary,
                'user_agent' => mb_substr($userAgent, 0, 500),
                'created_at' => Clock::nowString(),
            ],
        );
    }

    private function incrementarIntentosFallidos(string $usuarioId): void
    {
        // Incrementar contador e imponer bloqueo si se excede el límite
        $this->db->execute(
            'UPDATE usuarios SET
                intentos_fallidos = intentos_fallidos + 1,
                bloqueado_hasta = IF(
                    intentos_fallidos + 1 >= :max_attempts,
                    DATE_ADD(NOW(6), INTERVAL :lockout_min MINUTE),
                    bloqueado_hasta
                )
             WHERE id = :id',
            [
                'max_attempts' => self::MAX_ATTEMPTS,
                'lockout_min' => self::LOCKOUT_MINUTES,
                'id' => $usuarioId,
            ],
        );
    }

    private function resetearIntentosFallidos(string $usuarioId): void
    {
        $this->db->execute(
            'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id',
            ['id' => $usuarioId],
        );
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    private function userHasSystemSuperadminRole(string $userId): bool
    {
        $count = (int) $this->db->fetchScalar(
            "SELECT COUNT(*)
               FROM usuarios_roles ur
               INNER JOIN roles r ON r.id = ur.role_id
              WHERE ur.usuario_id = :uid
                AND r.codigo = 'superadmin'
                AND r.tenant_id IS NULL",
            ['uid' => $userId],
        );

        return $count > 0;
    }
}
