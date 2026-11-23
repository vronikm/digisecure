<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Infrastructure;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Infrastructure\Persistence\BaseRepository;
use DigiSecure\Infrastructure\Persistence\BusquedaTexto;
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
    /** @var array<string, true>|null */
    private ?array $tableColumns = null;

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

        [$where, $filtroBindings] = $this->filtroGuardias($filters);
        $bindings += $filtroBindings;

        // Prioriza la ficha COMPLETA y luego la mas reciente para no mezclar
        // datos de fichas antiguas del mismo documento.
        $personaKey = "COALESCE(NULLIF(f.cedula_hash, ''), f.id)";
        $sql = "SELECT
                    r.ficha_id,
                    r.cedula_hash,
                    r.cedula_cifrada,
                    r.apellidos,
                    r.nombres,
                    r.fecha_nacimiento,
                    r.celular,
                    r.email,
                    r.ultima_ficha_at,
                    r.total_fichas,
                    r.estado,
                    r.fecha_baja,
                    r.es_aspirante
                FROM (
                    SELECT
                        f.id AS ficha_id,
                        f.cedula_hash,
                        f.cedula_cifrada,
                        f.apellidos,
                        f.nombres,
                        f.fecha_nacimiento,
                        f.celular,
                        f.email,
                        f.estado,
                        f.fecha_baja,
                        EXISTS (
                            SELECT 1 FROM usuarios_roles ur_asp
                              JOIN roles r_asp ON r_asp.id = ur_asp.role_id
                             WHERE ur_asp.usuario_id = f.created_by AND r_asp.codigo = 'aspirante'
                        ) AS es_aspirante,
                        MAX(f.created_at) OVER (PARTITION BY {$personaKey}) AS ultima_ficha_at,
                        COUNT(*) OVER (PARTITION BY {$personaKey}) AS total_fichas,
                        ROW_NUMBER() OVER (
                            PARTITION BY {$personaKey}
                            ORDER BY (f.estado = 'COMPLETA') DESC, f.created_at DESC, f.id DESC
                        ) AS rn
                    FROM fichas f
                    {$where}
                ) r
                WHERE r.rn = 1
                ORDER BY r.ultima_ficha_at DESC
                LIMIT :lim OFFSET :off";

        return $this->db->fetchAll($sql, $bindings);
    }

    /**
     * Cuenta guardias únicos (1 por cédula hash) con filtros opcionales.
     *
     * @param array<string, mixed> $filters Filtros: busqueda, cedula_hash
     */
    public function countGuardias(array $filters = []): int
    {
        [$where, $bindings] = $this->filtroGuardias($filters);
        $personaKey = "COALESCE(NULLIF(f.cedula_hash, ''), f.id)";

        $sql = "SELECT COUNT(*) FROM (
                    SELECT {$personaKey} AS guardia_key
                    FROM fichas f
                    {$where}
                    GROUP BY guardia_key
                ) AS guardias_unicos";

        $result = $this->db->fetchScalar($sql, $bindings);
        return (int) $result;
    }

    /**
     * WHERE comun del listado y del conteo de guardias (unica fuente de verdad).
     *
     * - busqueda: se divide en palabras y CADA una debe aparecer en apellidos,
     *   nombres, correo o celular ("paladines nixon" encuentra apellido + nombre).
     *   La collation utf8mb4_unicode_ci ignora tildes y mayusculas. Los comodines
     *   % y _ escritos por el usuario se buscan literalmente.
     * - cedula_hash: la cedula esta cifrada; solo se busca completa (10 digitos)
     *   por su indice ciego, como alternativa (OR) a la busqueda por texto.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, string>}
     */
    private function filtroGuardias(array $filters): array
    {
        $bindings = ['__tenant_id' => $this->tenantContext->requireTenantId()];
        $conditions = ["f.estado <> 'ANULADA'"];

        [$textoSql, $textoBindings] = BusquedaTexto::condicion(
            (string) ($filters['busqueda'] ?? ''),
            ['f.apellidos', 'f.nombres', 'f.email', 'f.celular'],
            't',
        );
        $bindings += $textoBindings;

        $cedulaSql = null;
        if (!empty($filters['cedula_hash'])) {
            $bindings['cedula_hash'] = (string) $filters['cedula_hash'];
            $cedulaSql = 'f.cedula_hash = :cedula_hash';
        }

        if ($textoSql !== null && $cedulaSql !== null) {
            $conditions[] = "({$textoSql} OR {$cedulaSql})";
        } elseif ($textoSql !== null) {
            $conditions[] = $textoSql;
        } elseif ($cedulaSql !== null) {
            $conditions[] = $cedulaSql;
        }

        return ['WHERE f.tenant_id = :__tenant_id AND ' . implode(' AND ', $conditions), $bindings];
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

        $data = $this->filterKnownColumns($data);
        try {
            $this->tenantInsert($data);
        } catch (\Throwable $e) {
            error_log('[DIGISECURE][FICHA] Insert completo de ficha fallo, intentando minimo: ' . $e->getMessage());
            $fallback = $this->minimalCreateData($data);
            try {
                $this->tenantInsert($fallback);
            } catch (\Throwable $retry) {
                error_log('[DIGISECURE][FICHA] Insert minimo de ficha tambien fallo: ' . $retry->getMessage());
                throw $e;
            }
        }

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
    public function getFamiliares(string $fichaId): array
    {
        return $this->tenantQuery(
            "SELECT * FROM fichas_familiares
              WHERE ficha_id = :fid
              ORDER BY FIELD(parentesco, 'PADRE', 'MADRE', 'HERMANO', 'CONYUGE')",
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
     * Verifica si la ficha tiene cargada la autorizacion LOPDP firmada en el
     * expediente documental. El documento puede estar en revision o aceptado;
     * si fue rechazado, no cuenta como evidencia valida.
     */
    public function tieneAutorizacionLopdpCargada(string $fichaId): bool
    {
        $tenantId = $this->tenantContext->requireTenantId();

        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*)
               FROM expedientes e
               INNER JOIN expediente_documentos ed
                       ON ed.expediente_id = e.id
                      AND ed.tenant_id = e.tenant_id
               INNER JOIN documento_tipos dt ON dt.id = ed.documento_tipo_id
              WHERE e.tenant_id = :tid
                AND e.ficha_id = :fid
                AND dt.codigo = 'LOPDP_CONSENT'
                AND ed.estado IN ('EN_REVISION', 'ACEPTADO', 'VENCIDO')
                AND ed.archivo_nombre_storage IS NOT NULL",
            ['tid' => $tenantId, 'fid' => $fichaId],
        ) > 0;
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
            $cursoId = $this->scalarString($c['curso_id'] ?? '');
            if ($cursoId === '') {
                continue;
            }
            $fecha = $this->scalarString($c['fecha_obtencion'] ?? '');
            $observacion = $this->scalarString($c['observacion'] ?? '');

            $this->db->execute(
                'INSERT INTO fichas_cursos (ficha_id, curso_id, tenant_id, fecha_obtencion, observacion)
                 VALUES (:fid, :cid, :tid, :fecha, :obs)',
                [
                    'fid' => $fichaId,
                    'cid' => $cursoId,
                    'tid' => $tenantId,
                    'fecha' => $fecha !== '' ? $fecha : null,
                    'obs' => $observacion !== '' ? $observacion : null,
                ],
            );
        }
    }

    /**
     * Guarda/reemplaza los familiares directos de una ficha.
     *
     * @param list<array{parentesco:string,nombre:string,direccion:?string,telefono:?string,cedula_hash:?string,cedula_cifrada:?string,edad:?int}> $familiares
     */
    public function saveFamiliares(string $fichaId, array $familiares): void
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $this->db->execute(
            'DELETE FROM fichas_familiares WHERE ficha_id = :fid AND tenant_id = :tid',
            ['fid' => $fichaId, 'tid' => $tenantId],
        );

        $now = Clock::nowString();
        foreach ($familiares as $familiar) {
            $this->db->execute(
                'INSERT INTO fichas_familiares
                    (id, tenant_id, ficha_id, parentesco, nombre, direccion, telefono, cedula_hash, cedula_cifrada, edad, created_at, updated_at)
                 VALUES
                    (:id, :tid, :fid, :parentesco, :nombre, :direccion, :telefono, :cedula_hash, :cedula_cifrada, :edad, :created_at, :updated_at)',
                [
                    'id' => IdGenerator::ulid(),
                    'tid' => $tenantId,
                    'fid' => $fichaId,
                    'parentesco' => $familiar['parentesco'],
                    'nombre' => $familiar['nombre'],
                    'direccion' => $familiar['direccion'],
                    'telefono' => $familiar['telefono'],
                    'cedula_hash' => $familiar['cedula_hash'],
                    'cedula_cifrada' => $familiar['cedula_cifrada'],
                    'edad' => $familiar['edad'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function scalarString(mixed $value): string
    {
        if ($value === null || is_scalar($value)) {
            return trim((string)$value);
        }

        return '';
    }

    /**
     * Permite que despliegues con migraciones pendientes no fallen por columnas
     * opcionales nuevas. Las columnas obligatorias del esquema base se mantienen.
     *
     * @param array<string, scalar|null> $data
     * @return array<string, scalar|null>
     */
    private function filterKnownColumns(array $data): array
    {
        $columns = $this->knownColumns();
        if ($columns === []) {
            return $data;
        }

        return array_intersect_key($data, $columns);
    }

    /**
     * @param array<string, scalar|null> $data
     * @return array<string, scalar|null>
     */
    private function minimalCreateData(array $data): array
    {
        $keys = [
            'correlativo',
            'tipo_ficha',
            'estado',
            'fecha_emision',
            'nombres',
            'apellidos',
            'cedula_hash',
            'cedula_cifrada',
            'genero',
            'fecha_nacimiento',
            'id',
            'created_at',
            'updated_at',
        ];

        return array_intersect_key($data, array_fill_keys($keys, true));
    }

    /**
     * @return array<string, true>
     */
    private function knownColumns(): array
    {
        if ($this->tableColumns !== null) {
            return $this->tableColumns;
        }

        try {
            $rows = $this->db->fetchAll('SHOW COLUMNS FROM fichas');
        } catch (\Throwable $e) {
            error_log('[DIGISECURE][FICHA] No se pudo leer columnas de fichas: ' . $e->getMessage());
            return $this->tableColumns = [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $field = $this->scalarString($row['Field'] ?? '');
            if ($field !== '') {
                $columns[$field] = true;
            }
        }

        return $this->tableColumns = $columns;
    }
}
