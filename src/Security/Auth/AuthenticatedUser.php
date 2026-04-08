<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth;

/**
 * DTO inmutable que representa al usuario autenticado en el request actual.
 *
 * Se almacena serializado en sesión y se rehidrata en cada request
 * por el AuthenticationMiddleware.
 */
final readonly class AuthenticatedUser
{
    /**
     * @param list<string> $permisos  Códigos de permiso ("recurso.accion")
     * @param list<array{codigo: string, nombre: string}> $roles
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $email,
        public string $nombres,
        public string $apellidos,
        public string $estado,
        public string $tenantNombre,
        public string $tenantEstado,
        public array $permisos,
        public array $roles,
    ) {
    }

    public function nombreCompleto(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }

    public function tienePermiso(string $permiso): bool
    {
        return in_array($permiso, $this->permisos, true);
    }

    public function tieneAlgunPermiso(string ...$permisos): bool
    {
        foreach ($permisos as $permiso) {
            if (in_array($permiso, $this->permisos, true)) {
                return true;
            }
        }
        return false;
    }

    public function esSuperAdmin(): bool
    {
        foreach ($this->roles as $rol) {
            if ($rol['codigo'] === 'superadmin') {
                return true;
            }
        }
        return false;
    }

    public function rolPrincipal(): string
    {
        return $this->roles[0]['codigo'] ?? 'sin_rol';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSessionArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'email' => $this->email,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'estado' => $this->estado,
            'tenant_nombre' => $this->tenantNombre,
            'tenant_estado' => $this->tenantEstado,
            'permisos' => $this->permisos,
            'roles' => $this->roles,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromSessionArray(array $data): self
    {
        return new self(
            id: $data['id'],
            tenantId: $data['tenant_id'],
            email: $data['email'],
            nombres: $data['nombres'],
            apellidos: $data['apellidos'],
            estado: $data['estado'],
            tenantNombre: $data['tenant_nombre'],
            tenantEstado: $data['tenant_estado'],
            permisos: $data['permisos'],
            roles: $data['roles'],
        );
    }
}
