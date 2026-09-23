<?php

declare(strict_types=1);

namespace DigiSecure\Subscription\Application;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Subscription\Domain\UsoTenant;
use DigiSecure\Subscription\Infrastructure\CriteriosCupo;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Calcula el snapshot de uso actual del tenant activo.
 *
 * - Usuarios y guardias: según CriteriosCupo (aspirantes no consumen cupo;
 *   un guardia es una persona, no cada versión de su ficha).
 * - Storage: suma de archivo_tamano_bytes de expediente_documentos del tenant
 *
 * Los límites (max_*) los toma de la fila tenants vigente.
 */
final class CalcularUsoService
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array{max_usuarios?:int, max_guardias?:int, max_storage_mb?:int} $limites
     */
    public function execute(array $limites): UsoTenant
    {
        $tid = $this->tenantContext->requireTenantId();

        $usuarios = (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM usuarios u WHERE u.tenant_id = :tid AND ' . CriteriosCupo::usuario('u'),
            ['tid' => $tid],
        )['n'] ?? 0);

        $guardias = (int) ($this->db->fetchOne(
            'SELECT COUNT(DISTINCT ' . CriteriosCupo::personaGuardia('f') . ') AS n
               FROM fichas f
              WHERE f.tenant_id = :tid AND ' . CriteriosCupo::guardia('f'),
            ['tid' => $tid],
        )['n'] ?? 0);

        $bytes = (int) ($this->db->fetchOne(
            'SELECT COALESCE(SUM(archivo_tamano_bytes), 0) AS b
               FROM expediente_documentos
              WHERE tenant_id = :tid',
            ['tid' => $tid],
        )['b'] ?? 0);

        $storageMb = (int) floor($bytes / (1024 * 1024));

        return new UsoTenant(
            usuariosActuales: $usuarios,
            usuariosMax: (int) ($limites['max_usuarios'] ?? 0),
            guardiasActuales: $guardias,
            guardiasMax: (int) ($limites['max_guardias'] ?? 0),
            storageUsadoMb: $storageMb,
            storageMaxMb: (int) ($limites['max_storage_mb'] ?? 0),
        );
    }
}
