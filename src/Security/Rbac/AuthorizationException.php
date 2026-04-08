<?php

declare(strict_types=1);

namespace DigiSecure\Security\Rbac;

/**
 * Lanzada cuando un usuario no tiene el permiso requerido para una acción.
 */
final class AuthorizationException extends \RuntimeException
{
    public function __construct(
        public readonly string $permisoRequerido,
        public readonly ?string $usuarioId = null,
        string $message = '',
    ) {
        if ($message === '') {
            $message = "Acceso denegado. Permiso requerido: '{$permisoRequerido}'.";
        }
        parent::__construct($message, 403);
    }
}
