<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Infrastructure;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio del catálogo de tipos de documento.
 *
 * La tabla documento_tipos tiene un esquema de herencia por tenant:
 *   - tenant_id IS NULL → plantilla global (semilla del sistema).
 *   - tenant_id = X      → override del tenant (prevalece sobre global).
 *
 * Un tenant puede "personalizar" un tipo global creando su propia fila
 * con el mismo código. Puede cambiar vigencias, orden, obligatoriedad
 * y desactivar tipos que no necesita.
 *
 * Este repo NO extiende BaseRepository porque la tabla NO sigue el
 * patrón estándar tenant-only (tiene rows globales y rows por tenant).
 */
final class DocumentoTipoRepository
{
    public function __construct(
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Lista todos los tipos de documento efectivos para el tenant:
     * override si existe, global si no. Incluye los desactivados por el tenant.
     *
     * @return list<array<string, mixed>>
     */
    public function listEfectivos(?string $bloque = null): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $bindings = ['tid' => $tenantId];

        $bloqueFilter = '';
        if ($bloque !== null) {
            $bloqueFilter = 'AND effective.bloque = :bloque';
            $bindings['bloque'] = $bloque;
        }

        // COALESCE override del tenant sobre la plantilla global
        return $this->db->fetchAll(
            "SELECT effective.* FROM (
                SELECT dt.*,
                       ROW_NUMBER() OVER (PARTITION BY dt.codigo ORDER BY dt.tenant_id DESC) AS rn
                FROM documento_tipos dt
                WHERE (dt.tenant_id = :tid OR dt.tenant_id IS NULL)
             ) effective
             WHERE effective.rn = 1
             {$bloqueFilter}
             ORDER BY effective.bloque, effective.orden",
            $bindings,
        );
    }

    /**
     * Devuelve un tipo por ID.
     */
    public function findById(string $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM documento_tipos WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * Devuelve el tipo efectivo para un código dentro del tenant actual.
     */
    public function findByCodigo(string $codigo): ?array
    {
        $tenantId = $this->tenantContext->requireTenantId();

        // Override del tenant primero
        $row = $this->db->fetchOne(
            'SELECT * FROM documento_tipos WHERE tenant_id = :tid AND codigo = :codigo',
            ['tid' => $tenantId, 'codigo' => $codigo],
        );

        return $row ?? $this->db->fetchOne(
            'SELECT * FROM documento_tipos WHERE tenant_id IS NULL AND codigo = :codigo',
            ['codigo' => $codigo],
        );
    }

    /**
     * Crea un tipo documental propio del tenant.
     *
     * @param array<string, mixed> $data
     */
    public function createTenantTipo(array $data): string
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $id = IdGenerator::ulid();
        $now = Clock::nowString();

        $row = [
            'id' => $id,
            'tenant_id' => $tenantId,
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'bloque' => $data['bloque'],
            'orden' => $data['orden'],
            'obligatorio' => $data['obligatorio'],
            'numero_documentos' => $data['numero_documentos'] ?? 1,
            'requiere_validacion_externa' => $data['requiere_validacion_externa'],
            'tiene_vencimiento' => $data['tiene_vencimiento'],
            'dias_vigencia' => $data['dias_vigencia'],
            'formatos_permitidos' => $data['formatos_permitidos'],
            'tamano_maximo_mb' => $data['tamano_maximo_mb'],
            'activo' => $data['activo'],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $columns = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_map(
            static fn(string $col): string => ":{$col}",
            array_keys($row),
        ));

        $this->db->execute(
            "INSERT INTO documento_tipos ({$columns}) VALUES ({$placeholders})",
            $row,
        );

        return $id;
    }

    /**
     * Crea un override del tenant para un tipo global, o actualiza un
     * override existente. Campos editables por tenant:
     *   - activo, obligatorio, numero_documentos, requiere_validacion_externa,
     *     tiene_vencimiento, dias_vigencia, orden.
     *
     * @param array<string, mixed> $data
     */
    public function upsertOverride(string $codigo, array $data): string
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $existing = $this->db->fetchOne(
            'SELECT id FROM documento_tipos WHERE tenant_id = :tid AND codigo = :codigo',
            ['tid' => $tenantId, 'codigo' => $codigo],
        );

        if ($existing !== null) {
            // Actualizar override existente
            $data['updated_at'] = Clock::nowString();
            $setClauses = [];
            foreach (array_keys($data) as $col) {
                $setClauses[] = "{$col} = :{$col}";
            }
            $data['id'] = (string) $existing['id'];
            $data['tenant_id'] = $tenantId;

            $this->db->execute(
                'UPDATE documento_tipos SET ' . implode(', ', $setClauses)
                . ' WHERE id = :id AND tenant_id = :tenant_id',
                $data,
            );

            return (string) $existing['id'];
        }

        // Crear override copiando datos del global
        $global = $this->db->fetchOne(
            'SELECT * FROM documento_tipos WHERE tenant_id IS NULL AND codigo = :codigo',
            ['codigo' => $codigo],
        );

        if ($global === null) {
            throw new \InvalidArgumentException("Tipo de documento '{$codigo}' no encontrado en catálogo global.");
        }

        $id = IdGenerator::ulid();
        $now = Clock::nowString();

        $row = [
            'id' => $id,
            'tenant_id' => $tenantId,
            'codigo' => $global['codigo'],
            'nombre' => $global['nombre'],
            'descripcion' => $global['descripcion'],
            'bloque' => $global['bloque'],
            'orden' => $data['orden'] ?? $global['orden'],
            'obligatorio' => $data['obligatorio'] ?? $global['obligatorio'],
            'numero_documentos' => $data['numero_documentos'] ?? ($global['numero_documentos'] ?? 1),
            'requiere_validacion_externa' => $data['requiere_validacion_externa'] ?? $global['requiere_validacion_externa'],
            'tiene_vencimiento' => $data['tiene_vencimiento'] ?? $global['tiene_vencimiento'],
            'dias_vigencia' => $data['dias_vigencia'] ?? $global['dias_vigencia'],
            'formatos_permitidos' => $global['formatos_permitidos'],
            'tamano_maximo_mb' => $global['tamano_maximo_mb'],
            'activo' => $data['activo'] ?? $global['activo'],
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $columns = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_map(
            static fn(string $col): string => ":{$col}",
            array_keys($row),
        ));

        $this->db->execute(
            "INSERT INTO documento_tipos ({$columns}) VALUES ({$placeholders})",
            $row,
        );

        return $id;
    }

    /**
     * Cuenta tipos activos por bloque para el tenant actual.
     */
    public function countActivosByBloque(string $bloque): int
    {
        $all = $this->listEfectivos($bloque);
        return count(array_filter($all, static fn(array $t) => (int)$t['activo'] === 1));
    }
}
