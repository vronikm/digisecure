<?php

declare(strict_types=1);

namespace DigiSecure\Tenant\Domain;

/**
 * Contexto de tenant activo durante el request.
 *
 * Se establece tras la autenticación del usuario (el tenant se resuelve
 * desde el registro del usuario). Es request-scoped: cada request tiene
 * su propia instancia.
 *
 * INVARIANTE CRÍTICA: una vez establecido, el tenant_id NO puede cambiar
 * durante el mismo request. Esto previene ataques de escalación horizontal.
 */
final class TenantContext
{
    private ?string $tenantId = null;
    private ?string $tenantNombre = null;
    private ?string $tenantEstado = null;

    public function set(string $tenantId, string $nombre, string $estado): void
    {
        if ($this->tenantId !== null) {
            throw new TenantContextException(
                "Intento de cambiar el tenant_id durante el mismo request. "
                . "Actual: {$this->tenantId}, nuevo: {$tenantId}. Esto es un bug crítico."
            );
        }

        $this->tenantId = $tenantId;
        $this->tenantNombre = $nombre;
        $this->tenantEstado = $estado;
    }

    public function currentTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function requireTenantId(): string
    {
        if ($this->tenantId === null) {
            throw new TenantContextException(
                'Se requiere contexto de tenant pero no se ha establecido. '
                . 'Verifica que el usuario esté autenticado y el middleware de tenant haya ejecutado.'
            );
        }

        return $this->tenantId;
    }

    public function tenantNombre(): ?string
    {
        return $this->tenantNombre;
    }

    public function tenantEstado(): ?string
    {
        return $this->tenantEstado;
    }

    public function isSet(): bool
    {
        return $this->tenantId !== null;
    }

    public function isActivo(): bool
    {
        return $this->tenantEstado === 'activo';
    }

    /**
     * Solo para uso en el pipeline de autenticación (superadmin sin tenant).
     */
    public function isSuperAdminContext(): bool
    {
        return $this->tenantId === null;
    }
}
