<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Infrastructure;

use DigiSecure\Expediente\Domain\Enum\EstadoExpediente;
use DigiSecure\Infrastructure\Persistence\BaseRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio del Expediente Documental (tenant-aware).
 *
 * Gestiona expedientes y sus documentos. Los tipos de documento se
 * consultan desde documento_tipos (global + override tenant).
 */
final class ExpedienteRepository extends BaseRepository
{
    public function __construct(PdoConnection $db, TenantContext $tenantContext)
    {
        parent::__construct($db, $tenantContext);
    }

    protected function tableName(): string
    {
        return 'expedientes';
    }

    // ==================== EXPEDIENTE ====================

    /**
     * @return array<string, mixed>|null
     */
    public function findExpedienteById(string $id): ?array
    {
        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findExpedienteByFichaId(string $fichaId): ?array
    {
        return $this->tenantQueryOne(
            'SELECT * FROM expedientes WHERE ficha_id = :fid',
            ['fid' => $fichaId],
        );
    }

    /**
     * Crea un nuevo expediente vinculado a una ficha.
     *
     * @return string ID del expediente creado
     */
    public function createExpediente(string $fichaId, string $usuarioId): string
    {
        $id = IdGenerator::ulid();
        $now = Clock::nowString();
        $tenantId = $this->tenantContext->requireTenantId();

        // Contar tipos por bloque para este tenant
        $tiposA = $this->countDocumentoTiposByBloque('A');
        $tiposB = $this->countDocumentoTiposByBloque('B');

        $this->tenantInsert([
            'id' => $id,
            'ficha_id' => $fichaId,
            'estado' => EstadoExpediente::INCOMPLETO->value,
            'bloque_a_total' => $tiposA,
            'bloque_a_aceptados' => 0,
            'bloque_b_total' => $tiposB,
            'bloque_b_aceptados' => 0,
            'bloque_b_desbloqueado' => 0,
            'created_by' => $usuarioId,
            'updated_by' => $usuarioId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    public function updateExpediente(string $id, array $data): bool
    {
        $data['updated_at'] = Clock::nowString();
        return $this->tenantUpdate($id, $data) > 0;
    }

    /**
     * Lista expedientes con datos de la ficha (para listado general).
     *
     * @return list<array<string, mixed>>
     */
    public function listExpedientes(int $limit = 25, int $offset = 0, array $filters = []): array
    {
        $where = '';
        $bindings = [];
        $conditions = [];

        if (!empty($filters['estado'])) {
            $conditions[] = 'e.estado = :estado';
            $bindings['estado'] = $filters['estado'];
        }
        if (!empty($filters['busqueda'])) {
            $conditions[] = '(f.apellidos LIKE :busq OR f.nombres LIKE :busq2 OR f.correlativo LIKE :busq3)';
            $busq = '%' . $filters['busqueda'] . '%';
            $bindings['busq'] = $busq;
            $bindings['busq2'] = $busq;
            $bindings['busq3'] = $busq;
        }

        if ($conditions !== []) {
            $where = 'AND ' . implode(' AND ', $conditions);
        }

        $tenantId = $this->tenantContext->requireTenantId();
        $bindings['tid'] = $tenantId;
        $bindings['lim'] = $limit;
        $bindings['off'] = $offset;

        return $this->db->fetchAll(
            "SELECT e.*, f.correlativo, f.nombres, f.apellidos, f.estado AS ficha_estado
             FROM expedientes e
             INNER JOIN fichas f ON f.id = e.ficha_id
             WHERE e.tenant_id = :tid {$where}
             ORDER BY e.created_at DESC
             LIMIT :lim OFFSET :off",
            $bindings,
        );
    }

    public function countExpedientes(array $filters = []): int
    {
        $conditions = [];
        $bindings = [];

        if (!empty($filters['estado'])) {
            $conditions[] = 'e.estado = :estado';
            $bindings['estado'] = $filters['estado'];
        }
        if (!empty($filters['busqueda'])) {
            $conditions[] = '(f.apellidos LIKE :busq OR f.nombres LIKE :busq2 OR f.correlativo LIKE :busq3)';
            $busq = '%' . $filters['busqueda'] . '%';
            $bindings['busq'] = $busq;
            $bindings['busq2'] = $busq;
            $bindings['busq3'] = $busq;
        }

        $where = $conditions !== [] ? 'AND ' . implode(' AND ', $conditions) : '';
        $tenantId = $this->tenantContext->requireTenantId();
        $bindings['tid'] = $tenantId;

        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM expedientes e
             INNER JOIN fichas f ON f.id = e.ficha_id
             WHERE e.tenant_id = :tid {$where}",
            $bindings,
        );
    }

    // ==================== DOCUMENTO TIPOS ====================

    /**
     * Carga los tipos de documento activos para el tenant actual.
     * Usa override del tenant si existe, de lo contrario usa la plantilla global (tenant_id IS NULL).
     *
     * @return list<array<string, mixed>>
     */
    public function getDocumentoTipos(?string $bloque = null): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        // Nota: PDO sin emulate_prepares no permite reusar :tid en dos lugares.
        $bindings = ['tid_outer' => $tenantId, 'tid_inner' => $tenantId];

        $bloqueFilter = '';
        if ($bloque !== null) {
            $bloqueFilter = 'AND dt.bloque = :bloque';
            $bindings['bloque'] = $bloque;
        }

        // Priorizar override del tenant sobre plantilla global
        return $this->db->fetchAll(
            "SELECT dt.* FROM documento_tipos dt
             WHERE (dt.tenant_id = :tid_outer OR dt.tenant_id IS NULL)
               AND dt.activo = 1
               {$bloqueFilter}
               AND NOT EXISTS (
                   SELECT 1 FROM documento_tipos dt2
                   WHERE dt2.tenant_id = :tid_inner
                     AND dt2.codigo = dt.codigo
                     AND dt.tenant_id IS NULL
               )
             ORDER BY dt.bloque, dt.orden",
            $bindings,
        );
    }

    /**
     * Busca un tipo de documento por código para el tenant actual.
     */
    public function findDocumentoTipoByCodigo(string $codigo): ?array
    {
        $tenantId = $this->tenantContext->requireTenantId();

        // Primero buscar override del tenant
        $row = $this->db->fetchOne(
            'SELECT * FROM documento_tipos WHERE tenant_id = :tid AND codigo = :codigo AND activo = 1',
            ['tid' => $tenantId, 'codigo' => $codigo],
        );

        if ($row !== null) {
            return $row;
        }

        // Fallback a plantilla global
        return $this->db->fetchOne(
            'SELECT * FROM documento_tipos WHERE tenant_id IS NULL AND codigo = :codigo AND activo = 1',
            ['codigo' => $codigo],
        );
    }

    public function findDocumentoTipoById(string $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM documento_tipos WHERE id = :id AND activo = 1',
            ['id' => $id],
        );
    }

    private function countDocumentoTiposByBloque(string $bloque): int
    {
        $tenantId = $this->tenantContext->requireTenantId();

        // Nota: PDO::ATTR_EMULATE_PREPARES = false no permite reusar el mismo
        // named placeholder en dos lugares de la query, por eso :tid_outer y :tid_inner
        // (ambos bindeados al mismo valor).
        $result = $this->db->fetchScalar(
            "SELECT COUNT(*) FROM documento_tipos dt
             WHERE (dt.tenant_id = :tid_outer OR dt.tenant_id IS NULL)
               AND dt.activo = 1
               AND dt.obligatorio = 1
               AND dt.bloque = :bloque
               AND NOT EXISTS (
                   SELECT 1 FROM documento_tipos dt2
                   WHERE dt2.tenant_id = :tid_inner
                     AND dt2.codigo = dt.codigo
                     AND dt.tenant_id IS NULL
               )",
            ['tid_outer' => $tenantId, 'tid_inner' => $tenantId, 'bloque' => $bloque],
        );

        return (int) $result;
    }

    // ==================== DOCUMENTOS ====================

    /**
     * Carga documentos de un expediente con info del tipo.
     *
     * @return list<array<string, mixed>>
     */
    public function getDocumentos(string $expedienteId, ?string $bloque = null): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $bindings = ['tid' => $tenantId, 'eid' => $expedienteId];

        $bloqueFilter = '';
        if ($bloque !== null) {
            $bloqueFilter = 'AND dt.bloque = :bloque';
            $bindings['bloque'] = $bloque;
        }

        return $this->db->fetchAll(
            "SELECT ed.*, dt.codigo AS tipo_codigo, dt.nombre AS tipo_nombre,
                    dt.bloque, dt.obligatorio, dt.requiere_validacion_externa,
                    dt.tiene_vencimiento, dt.dias_vigencia
             FROM expediente_documentos ed
             INNER JOIN documento_tipos dt ON dt.id = ed.documento_tipo_id
             WHERE ed.tenant_id = :tid AND ed.expediente_id = :eid
             {$bloqueFilter}
             ORDER BY dt.bloque, dt.orden",
            $bindings,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDocumentoById(string $id): ?array
    {
        $tenantId = $this->tenantContext->requireTenantId();

        return $this->db->fetchOne(
            'SELECT ed.*, dt.codigo AS tipo_codigo, dt.nombre AS tipo_nombre,
                    dt.bloque, dt.obligatorio, dt.requiere_validacion_externa,
                    dt.tiene_vencimiento, dt.dias_vigencia
             FROM expediente_documentos ed
             INNER JOIN documento_tipos dt ON dt.id = ed.documento_tipo_id
             WHERE ed.id = :id AND ed.tenant_id = :tid',
            ['id' => $id, 'tid' => $tenantId],
        );
    }

    /**
     * Busca un documento existente de un expediente para un tipo dado.
     */
    public function findDocumentoByTipo(string $expedienteId, string $tipoId): ?array
    {
        $tenantId = $this->tenantContext->requireTenantId();

        return $this->db->fetchOne(
            'SELECT * FROM expediente_documentos
             WHERE expediente_id = :eid AND documento_tipo_id = :tid2 AND tenant_id = :tid',
            ['eid' => $expedienteId, 'tid2' => $tipoId, 'tid' => $tenantId],
        );
    }

    /**
     * Crea un registro de documento en el expediente.
     */
    public function createDocumento(array $data): string
    {
        $id = IdGenerator::ulid();
        $now = Clock::nowString();
        $tenantId = $this->tenantContext->requireTenantId();

        $data['id'] = $id;
        $data['tenant_id'] = $tenantId;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(
            static fn(string $col): string => ":{$col}",
            array_keys($data),
        ));

        $this->db->execute(
            "INSERT INTO expediente_documentos ({$columns}) VALUES ({$placeholders})",
            $data,
        );

        return $id;
    }

    /**
     * Actualiza un documento existente.
     */
    public function updateDocumento(string $id, array $data): bool
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $data['updated_at'] = Clock::nowString();

        $setClauses = [];
        foreach (array_keys($data) as $col) {
            $setClauses[] = "{$col} = :{$col}";
        }
        $setString = implode(', ', $setClauses);

        $data['id'] = $id;
        $data['tenant_id'] = $tenantId;

        return $this->db->affectedRows(
            "UPDATE expediente_documentos SET {$setString} WHERE id = :id AND tenant_id = :tenant_id",
            $data,
        ) > 0;
    }

    /**
     * Cuenta documentos aceptados por bloque para recalcular progreso.
     */
    public function countAceptadosByBloque(string $expedienteId, string $bloque): int
    {
        $tenantId = $this->tenantContext->requireTenantId();

        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM expediente_documentos ed
             INNER JOIN documento_tipos dt ON dt.id = ed.documento_tipo_id
             WHERE ed.expediente_id = :eid AND ed.tenant_id = :tid
               AND ed.estado = 'ACEPTADO' AND dt.bloque = :bloque AND dt.obligatorio = 1",
            ['eid' => $expedienteId, 'tid' => $tenantId, 'bloque' => $bloque],
        );
    }

    /**
     * Documentos próximos a vencer (para dashboard/alertas).
     *
     * @return list<array<string, mixed>>
     */
    public function getDocumentosPorVencer(int $diasAlerta = 30): array
    {
        $tenantId = $this->tenantContext->requireTenantId();

        return $this->db->fetchAll(
            "SELECT ed.*, dt.nombre AS tipo_nombre, dt.codigo AS tipo_codigo,
                    f.correlativo, f.nombres, f.apellidos,
                    f.email AS guardia_email, f.celular AS guardia_telefono,
                    t.email_contacto AS admin_email, t.telefono AS admin_telefono,
                    COALESCE(t.nombre_comercial, t.razon_social) AS tenant_nombre,
                    DATEDIFF(ed.fecha_vencimiento, CURDATE()) AS dias_restantes
             FROM expediente_documentos ed
             INNER JOIN documento_tipos dt ON dt.id = ed.documento_tipo_id
             INNER JOIN expedientes e ON e.id = ed.expediente_id
             INNER JOIN fichas f ON f.id = e.ficha_id
             INNER JOIN tenants t ON t.id = ed.tenant_id
             WHERE ed.tenant_id = :tid
               AND ed.estado = 'ACEPTADO'
               AND ed.fecha_vencimiento IS NOT NULL
               AND ed.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
             ORDER BY ed.fecha_vencimiento ASC",
            ['tid' => $tenantId, 'dias' => $diasAlerta],
        );
    }

    /**
     * Documentos ya vencidos que aún están marcados como ACEPTADO.
     *
     * @return list<array<string, mixed>>
     */
    public function getDocumentosVencidos(): array
    {
        $tenantId = $this->tenantContext->requireTenantId();

        return $this->db->fetchAll(
            "SELECT ed.id, ed.expediente_id, ed.documento_tipo_id, ed.fecha_vencimiento,
                    dt.nombre AS tipo_nombre, dt.codigo AS tipo_codigo,
                    f.correlativo, f.nombres, f.apellidos,
                    f.email AS guardia_email, f.celular AS guardia_telefono,
                    t.email_contacto AS admin_email, t.telefono AS admin_telefono,
                    COALESCE(t.nombre_comercial, t.razon_social) AS tenant_nombre
             FROM expediente_documentos ed
             INNER JOIN documento_tipos dt ON dt.id = ed.documento_tipo_id
             INNER JOIN expedientes e ON e.id = ed.expediente_id
             INNER JOIN fichas f ON f.id = e.ficha_id
             INNER JOIN tenants t ON t.id = ed.tenant_id
             WHERE ed.tenant_id = :tid
               AND ed.estado = 'ACEPTADO'
               AND ed.fecha_vencimiento IS NOT NULL
               AND ed.fecha_vencimiento < CURDATE()",
            ['tid' => $tenantId],
        );
    }

    /**
     * KPIs para dashboard: cuenta expedientes por estado.
     *
     * @return array<string, int>
     */
    public function countByEstado(): array
    {
        $rows = $this->tenantQuery(
            'SELECT estado, COUNT(*) AS total FROM expedientes GROUP BY estado',
        );

        $result = [];
        foreach (EstadoExpediente::cases() as $est) {
            $result[$est->value] = 0;
        }
        foreach ($rows as $row) {
            $result[$row['estado']] = (int) $row['total'];
        }
        return $result;
    }
}
