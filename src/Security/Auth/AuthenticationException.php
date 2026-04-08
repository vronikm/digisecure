<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth;

final class AuthenticationException extends \RuntimeException
{
    public const INVALID_CREDENTIALS = 'credenciales_invalidas';
    public const ACCOUNT_BLOCKED = 'cuenta_bloqueada';
    public const ACCOUNT_INACTIVE = 'cuenta_inactiva';
    public const TENANT_SUSPENDED = 'tenant_suspendido';
    public const RATE_LIMITED = 'rate_limited';
    public const MUST_CHANGE_PASSWORD = 'debe_cambiar_password';

    public function __construct(
        public readonly string $reason,
        string $message = '',
    ) {
        if ($message === '') {
            $message = match ($reason) {
                self::INVALID_CREDENTIALS => 'Email o contraseña incorrectos.',
                self::ACCOUNT_BLOCKED => 'Tu cuenta está bloqueada temporalmente por múltiples intentos fallidos.',
                self::ACCOUNT_INACTIVE => 'Tu cuenta no está activa. Contacta al administrador.',
                self::TENANT_SUSPENDED => 'La empresa tiene la cuenta suspendida. Contacta soporte.',
                self::RATE_LIMITED => 'Demasiados intentos de inicio de sesión. Intenta de nuevo más tarde.',
                self::MUST_CHANGE_PASSWORD => 'Debes cambiar tu contraseña antes de continuar.',
                default => 'Error de autenticación.',
            };
        }
        parent::__construct($message, 401);
    }
}
