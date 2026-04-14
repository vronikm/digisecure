<?php

declare(strict_types=1);

namespace DigiSecure\Tenant\Infrastructure;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio para la tabla global `tenants`.
 *
 * NO extiende BaseRepository porque `tenants` es global (sin tenant_id).
 * El acceso se restringe al tenant activo usando TenantContext.
 */
final class TenantRepository
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCurrent(): ?array
    {
        $tenantId = $this->tenantContext->requireTenantId();

        return $this->db->fetchOne(
            'SELECT id, razon_social, ruc, nombre_comercial, email_contacto,
                    telefono, direccion, ciudad, provincia, pais,
                    estado, plan_codigo, plan_inicio, plan_vencimiento,
                    max_usuarios, max_guardias, max_storage_mb,
                    timezone, locale, notas, created_at, updated_at
             FROM tenants WHERE id = :id',
            ['id' => $tenantId],
        );
    }

    /**
     * Actualiza los campos editables del tenant actual.
     *
     * @param array<string, scalar|null> $data
     */
    public function updateCurrent(array $data): bool
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $data['updated_at'] = Clock::nowString();

        $setClauses = [];
        foreach (array_keys($data) as $col) {
            $setClauses[] = "{$col} = :{$col}";
        }
        $data['id'] = $tenantId;

        return $this->db->affectedRows(
            'UPDATE tenants SET ' . implode(', ', $setClauses) . ' WHERE id = :id',
            $data,
        ) > 0;
    }
}
