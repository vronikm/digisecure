<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Infrastructure;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Infrastructure\Persistence\BaseRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio de Fichas de Guardia (tenant-aware via BaseRepository).
 *
 * Toda query se filtra automáticamente por tenant_id.
 * Los datos sensibles (cédula, cuenta bancaria) se almacenan cifrados;
 * el cifrado/descifrado se maneja en la capa de servicio, no aquí.
 */
final class FichaRepository extends BaseRepository
{
    public function __construct(PdoConnection $db, TenantContext $tenantContext)
    {
        parent::__construct($db, $tenantContext);
    }

    protected function tableName(): string
    {
        return 'fichas';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findFichaById(string $id): ?array
    {
        return $this->findById($id);
    }

    /**
     * Busca fichas por hash de cédula (para detectar reingresos).
     *
     * @return list<array<string, mixed>>
     */
    public function findByCedulaHash(string $cedulaHash): array
    {
        return $this->findByCedulaHashFiltered($cedulaHash);
    }

    /**
     * Lista fichas del guardia por hash de cédula con filtros opcionales.
     *
     * @param array{estado?: string, tipo_ficha?: string, desde?: string, hasta?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function findByCedulaHashFiltered(string $cedulaHash, array $filters = []): array
    {
        $bindings = ['hash' => $cedulaHash];
        $conditions = ['cedula_hash = :hash'];

        if (!empty($filters['estado'])) {
            $conditions[] = 'estado = :estado';
            $bindings['estado'] = (string) $filters['estado'];
        }
        if (!empty($filters['tipo_ficha'])) {
            $conditions[] = 'tipo_ficha = :tipo_ficha';
            $bindings['tipo_ficha'] = (string) $filters['tipo_ficha'];
        }
        if (!empty($filters['desde'])) {
            $conditions[] = 'fecha_emision >= :desde';
            $bindings['desde'] = (string) $filters['desde'];
        }
        if (!empty($filters['hasta'])) {
            $conditions[] = 'fecha_emision <= :hasta';
            $bindings['hasta'] = (string) $filters['hasta'];
        }

        return $this->tenantQuery(
            'SELECT id, correlativo, tipo_ficha, estado, fecha_emision, fecha_revision,
                    nombres, apellidos, created_at, updated_at
             FROM fichas
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY fecha_emision DESC, created_at DESC',
            $bindings,
        );
    }

    /**
     * Busca la ficha más reciente por hash de cédula dentro del tenant.
     * Se usa en LOPDP para autocompletar titulares de tipo guardia.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestByCedulaHash(string $cedulaHash): ?array
    {
        return $this->tenantQueryOne(
            'SELECT id, correlativo, nombres, apellidos, email, celular, fecha_baja, motivo_baja
             FROM fichas
             WHERE cedula_hash = :hash
             ORDER BY created_at DESC
             LIMIT 1',
            ['hash' => $cedulaHash],
        );
    }

    /**
     * Lista fichas del tenant con paginación y filtros opcionales.
     *
     * @param array<string, mixed> $filters  Filtros: estado, tipo_ficha, busqueda
     * @return list<array<string, mixed>>
     */
    public function listFichas(int $limit = 25, int $offset = 0, array $filters = []): array
    {
        $where = '';
        $bindings = [];

        $conditions = [];
        if (!empty($filters['estado'])) {
            $conditions[] = 'estado = :estado';
            $bindings['estado'] = $filters['estado'];
        }
        if (!empty($filters['tipo_ficha'])) {
            $conditions[] = 'tipo_ficha = :tipo_ficha';
            $bindings['tipo_ficha'] = $filters['tipo_ficha'];
        }
        if (!empty($filters['busqueda'])) {
            $conditions[] = '(apellidos LIKE :busq OR nombres LIKE :busq2 OR correlativo LIKE :busq3)';
            $busq = '%' . $filters['busqueda'] . '%';
            $bindings['busq'] = $busq;
            $bindings['busq2'] = $busq;
            $bindings['busq3'] = $busq;
        }

        if ($conditions !== []) {
            $where = implode(' AND ', $conditions);
        }

        $sql = 'SELECT id, correlativo, tipo_ficha, estado, fecha_emision, nombres, apellidos,
                       genero, celular, email, created_at
                FROM fichas';
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :lim OFFSET :off';
        $bindings['lim'] = $limit;
        $bindings['off'] = $offset;

        return $this->tenantQuery($sql, $bindings);
    }

    /**
     * Cuenta fichas del tenant con filtros opcionales.
     */
    public function countFichas(array $filters = []): int
    {
        $where = '';
        $bindings = [];
        $conditions = [];

        if (!empty($filters['estado'])) {
            $conditions[] = 'estado = :estado';
            $bindings['estado'] = $filters['estado'];
        }
        if (!empty($filters['tipo_ficha'])) {
            $conditions[] = 'tipo_ficha = :tipo_ficha';
            $bindings['tipo_ficha'] = $filters['tipo_ficha'];
        }
        if (!empty($filters['busqueda'])) {
            $conditions[] = '(apellidos LIKE :busq OR nombres LIKE :busq2 OR correlativo LIKE :busq3)';
            $busq = '%' . $filters['busqueda'] . '%';
            $bindings['busq'] = $busq;
            $bindings['busq2'] = $busq;
            $bindings['busq3'] = $busq;
        }

        if ($conditions !== []) {
            $where = implode(' AND ', $conditions);
        }

        return $this->tenantCount($where, $bindings);
    }

    /**
     * Lista guardias únicos (agrupados por cédula hash) para la vista principal.
     *
     * @param array<string, mixed> $filters Filtros: busqueda, cedula_hash
     * @return list<array<string, mixed>>
     */
    public function listGuardias(int $limit = 25, int $offset = 0, array $filters = []): array
    {
        $bindings = [
            'lim' => $limit,
            'off' => $offset,
        ];

        $conditions = [];
        $hasBusqueda = !empty($filters['busqueda']);
        $hasCedulaHash = !empty($filters['cedula_hash']);

        if ($hasBusqueda) {
            $busq = '%' . $filters['busqueda'] . '%';
            $bindings['busq'] = $busq;
            $bindings['busq2'] = $busq;
            $bindings['busq3'] = $busq;
            $bindings['busq4'] = $busq;
        }
        if ($hasCedulaHash) {
            $bindings['cedula_hash'] = (string) $filters['cedula_hash'];
        }

        if ($hasBusqueda && $hasCedulaHash) {
            // Si el usuario busca por cedula, no forzamos un AND con filtros textuales:
            // debe coincidir por texto O por hash de cedula.
            $conditions[] = '((apellidos LIKE :busq OR nombres LIKE :busq2 OR email LIKE :busq3 OR celular LIKE :busq4) OR cedula_hash = :cedula_hash)';
        } elseif ($hasBusqueda) {
            $conditions[] = '(apellidos LIKE :busq OR nombres LIKE :busq2 OR email LIKE :busq3 OR celular LIKE :busq4)';
        } elseif ($hasCedulaHash) {
            $conditions[] = 'cedula_hash = :cedula_hash';
        }

        $sql = 'SELECT
                    MAX(id) AS ficha_id,
                    cedula_hash,
                    MAX(cedula_cifrada) AS cedula_cifrada,
                    MAX(apellidos) AS apellidos,
                    MAX(nombres) AS nombres,
                    MAX(fecha_nacimiento) AS fecha_nacimiento,
                    MAX(celular) AS celular,
                    MAX(email) AS email,
                    MAX(created_at) AS ultima_ficha_at,
                    COUNT(*) AS total_fichas
                FROM fichas';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY cedula_hash
                  ORDER BY MAX(created_at) DESC
                  LIMIT :lim OFFSET :off';

        return $this->tenantQuery($sql, $bindings);
    }

    /**
     * Cuenta guardias únicos (1 por cédula hash) con filtros opcionales.
     *
     * @param array<string, mixed> $filters Filtros: busqueda, cedula_hash
     */
    public function countGuardias(array $filters = []): int
    {
        $bindings = [];
        $conditions = [];
        $hasBusqueda = !empty($filters['busqueda']);
        $hasCedulaHash = !empty($filters['cedula_hash']);

        if ($hasBusqueda) {
            $busq = '%' . $filters['busqueda'] . '%';
            $bindings['busq'] = $busq;
            $bindings['busq2'] = $busq;
            $bindings['busq3'] = $busq;
            $bindings['busq4'] = $busq;
        }
        if ($hasCedulaHash) {
            $bindings['cedula_hash'] = (string) $filters['cedula_hash'];
        }

        if ($hasBusqueda && $hasCedulaHash) {
            $conditions[] = '((apellidos LIKE :busq OR nombres LIKE :busq2 OR email LIKE :busq3 OR celular LIKE :busq4) OR cedula_hash = :cedula_hash)';
        } elseif ($hasBusqueda) {
            $conditions[] = '(apellidos LIKE :busq OR nombres LIKE :busq2 OR email LIKE :busq3 OR celular LIKE :busq4)';
        } elseif ($hasCedulaHash) {
            $conditions[] = 'cedula_hash = :cedula_hash';
        }

        $sql = 'SELECT COUNT(*) FROM (
                    SELECT cedula_hash
                    FROM fichas';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' GROUP BY cedula_hash
                ) AS guardias_unicos';

        $result = $this->tenantScalar($sql, $bindings);
        return (int) $result;
    }

    /**
     * Inserta una nueva ficha.
     *
     * @param array<string, scalar|null> $data
     * @return string ID generado (ULID)
     */
    public function create(array $data): string
    {
        $id = IdGenerator::ulid();
        $now = Clock::nowString();
        $data['id'] = $id;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $this->tenantInsert($data);
        return $id;
    }

    /**
     * Actualiza una ficha existente.
     *
     * @param array<string, scalar|null> $data
     */
    public function update(string $id, array $data): bool
    {
        $data['updated_at'] = Clock::nowString();
        return $this->tenantUpdate($id, $data) > 0;
    }

    /**
     * Genera el siguiente correlativo para el tenant.
     * Formato: YYYY-NNNN (ej: 2026-0001)
     */
    public function nextCorrelativo(): string
    {
        $year = date('Y');
        $prefix = "{$year}-";

        $lastCorrelativo = $this->tenantScalar(
            "SELECT correlativo FROM fichas WHERE correlativo LIKE :prefix ORDER BY correlativo DESC LIMIT 1",
            ['prefix' => "{$prefix}%"],
        );

        if ($lastCorrelativo === null) {
            return "{$prefix}0001";
        }

        $lastNum = (int)substr((string)$lastCorrelativo, strlen($prefix));
        $nextNum = $lastNum + 1;

        return $prefix . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Cuenta fichas por estado para KPIs del dashboard.
     *
     * @return array<string, int>
     */
    public function countByEstado(): array
    {
        $rows = $this->tenantQuery(
            'SELECT estado, COUNT(*) AS total FROM fichas GROUP BY estado',
        );

        $result = [];
        foreach (EstadoFicha::cases() as $estado) {
            $result[$estado->value] = 0;
        }
        foreach ($rows as $row) {
            $result[$row['estado']] = (int)$row['total'];
        }
        return $result;
    }

    // ---- Relaciones 1:N ----

    /**
     * @return list<array<string, mixed>>
     */
    public function getTrabajosAnteriores(string $fichaId): array
    {
        return $this->tenantQuery(
            'SELECT * FROM fichas_trabajos_anteriores WHERE ficha_id = :fid ORDER BY orden',
            ['fid' => $fichaId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getHijosMenores(string $fichaId): array
    {
        return $this->tenantQuery(
            'SELECT * FROM fichas_hijos_menores WHERE ficha_id = :fid ORDER BY orden',
            ['fid' => $fichaId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getReferencias(string $fichaId): array
    {
        return $this->tenantQuery(
            'SELECT * FROM fichas_referencias WHERE ficha_id = :fid ORDER BY orden',
            ['fid' => $fichaId],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCursos(string $fichaId): array
    {
        return $this->tenantQuery(
            'SELECT fc.*, cc.codigo, cc.nombre AS curso_nombre
             FROM fichas_cursos fc
             INNER JOIN catalogo_cursos cc ON cc.id = fc.curso_id
             WHERE fc.ficha_id = :fid
             ORDER BY cc.orden',
            ['fid' => $fichaId],
        );
    }

    /**
     * Guarda/reemplaza trabajos anteriores de una ficha.
     *
     * @param list<array<string, scalar|null>> $trabajos
     */
    public function saveTrabajosAnteriores(string $fichaId, array $trabajos): void
    {
        $tenantId = $this->tenantContext->requireTenantId();

        // Borrar existentes
        $this->db->execute(
            'DELETE FROM fichas_trabajos_anteriores WHERE ficha_id = :fid AND tenant_id = :tid',
            ['fid' => $fichaId, 'tid' => $tenantId],
        );

        $now = Clock::nowString();
        foreach ($trabajos as $i => $t) {
            $this->db->execute(
                'INSERT INTO fichas_trabajos_anteriores (id, tenant_id, ficha_id, empresa, direccion, telefonos, tiempo, cargo, orden, created_at)
                 VALUES (:id, :tid, :fid, :empresa, :direccion, :telefonos, :tiempo, :cargo, :orden, :now)',
                [
                    'id' => IdGenerator::ulid(),
                    'tid' => $tenantId,
                    'fid' => $fichaId,
                    'empresa' => $t['empresa'] ?? '',
                    'direccion' => $t['direccion'] ?? null,
                    'telefonos' => $t['telefonos'] ?? null,
                    'tiempo' => $t['tiempo'] ?? null,
                    'cargo' => $t['cargo'] ?? null,
                    'orden' => $i + 1,
                    'now' => $now,
                ],
            );
        }
    }

    /**
     * Guarda/reemplaza hijos menores de una ficha.
     *
     * @param list<array<string, scalar|null>> $hijos
     */
    public function saveHijosMenores(string $fichaId, array $hijos): void
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $this->db->execute(
            'DELETE FROM fichas_hijos_menores WHERE ficha_id = :fid AND tenant_id = :tid',
            ['fid' => $fichaId, 'tid' => $tenantId],
        );

        $now = Clock::nowString();
        foreach ($hijos as $i => $h) {
            $this->db->execute(
                'INSERT INTO fichas_hijos_menores (id, tenant_id, ficha_id, nombre, edad, justifica_con_cedula, orden, created_at)
                 VALUES (:id, :tid, :fid, :nombre, :edad, :justifica, :orden, :now)',
                [
                    'id' => IdGenerator::ulid(),
                    'tid' => $tenantId,
                    'fid' => $fichaId,
                    'nombre' => $h['nombre'] ?? '',
                    'edad' => (int)($h['edad'] ?? 0),
                    'justifica' => (int)($h['justifica_con_cedula'] ?? 0),
                    'orden' => $i + 1,
                    'now' => $now,
                ],
            );
        }
    }

    /**
     * Guarda/reemplaza referencias personales de una ficha.
     *
     * @param list<array<string, scalar|null>> $referencias
     */
    public function saveReferencias(string $fichaId, array $referencias): void
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $this->db->execute(
            'DELETE FROM fichas_referencias WHERE ficha_id = :fid AND tenant_id = :tid',
            ['fid' => $fichaId, 'tid' => $tenantId],
        );

        $now = Clock::nowString();
        foreach ($referencias as $i => $r) {
            $this->db->execute(
                'INSERT INTO fichas_referencias (id, tenant_id, ficha_id, parentesco, nombre, direccion, telefono, orden, created_at)
                 VALUES (:id, :tid, :fid, :parentesco, :nombre, :direccion, :telefono, :orden, :now)',
                [
                    'id' => IdGenerator::ulid(),
                    'tid' => $tenantId,
                    'fid' => $fichaId,
                    'parentesco' => $r['parentesco'] ?? 'OTRO',
                    'nombre' => $r['nombre'] ?? '',
                    'direccion' => $r['direccion'] ?? null,
                    'telefono' => $r['telefono'] ?? null,
                    'orden' => $i + 1,
                    'now' => $now,
                ],
            );
        }
    }

    /**
     * Guarda/reemplaza cursos de una ficha.
     *
     * @param list<array{curso_id: string, fecha_obtencion?: string|null, observacion?: string|null}> $cursos
     */
    public function saveCursos(string $fichaId, array $cursos): void
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $this->db->execute(
            'DELETE FROM fichas_cursos WHERE ficha_id = :fid AND tenant_id = :tid',
            ['fid' => $fichaId, 'tid' => $tenantId],
        );

        foreach ($cursos as $c) {
            $this->db->execute(
                'INSERT INTO fichas_cursos (ficha_id, curso_id, tenant_id, fecha_obtencion, observacion)
                 VALUES (:fid, :cid, :tid, :fecha, :obs)',
                [
                    'fid' => $fichaId,
                    'cid' => $c['curso_id'],
                    'tid' => $tenantId,
                    'fecha' => $c['fecha_obtencion'] ?? null,
                    'obs' => $c['observacion'] ?? null,
                ],
            );
        }
    }
}
