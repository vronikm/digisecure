<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Infrastructure;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;

/**
 * Repositorio de aspirantes (postulantes a guardia).
 *
 * Al igual que RegistroEmpresaRepository, NO es tenant-aware: el registro nace
 * desde la pantalla publica de login, pero el flujo GEMESEG asigna tenant_id
 * explicitamente en la capa de aplicacion. Por eso depende solo de la conexion,
 * sin TenantContext.
 *
 * No es `final` a proposito: permite un doble en memoria en los tests del
 * servicio sin necesidad de una base de datos real.
 */
class AspiranteRepository
{
    public function __construct(
        private readonly PdoConnection $db,
    ) {
    }

    /**
     * Inserta un nuevo aspirante. La capa de aplicacion ya validó y normalizó
     * todos los campos; aqui solo se persiste con prepared statements.
     *
     * @param array{
     *     id: string,
     *     tenant_id: ?string,
     *     tipo_documento: string,
     *     documento: string,
     *     apellidos: string,
     *     nombres: string,
     *     celular: string,
     *     correo: string,
     *     correo_normalizado: string,
     *     password_hash: string,
     *     estatura_cm: int,
     *     fecha_nacimiento: string,
     *     origen: string,
     *     acepta_politicas: bool,
     *     politicas_version: ?string,
     *     verificacion_token_hash: ?string,
     *     verificacion_token_expira_at: ?string,
     *     ip: ?string,
     *     user_agent: ?string,
     *     created_at: string
     * } $a
     */
    public function crear(array $a): void
    {
        $ipBinary = null;
        if (!empty($a['ip'])) {
            $packed = @inet_pton($a['ip']);
            $ipBinary = $packed !== false ? $packed : null;
        }

        $this->db->execute(
            'INSERT INTO aspirantes
                (id, tenant_id, tipo_documento, documento, apellidos, nombres,
                 celular, correo, correo_normalizado, password_hash, estatura_cm, fecha_nacimiento,
                 estado, origen, acepta_politicas, politicas_version,
                 verificacion_token_hash, verificacion_token_expira_at,
                 ip, user_agent, created_at, updated_at)
             VALUES
                (:id, :tid, :tdoc, :doc, :ap, :no,
                 :cel, :co, :con, :pwh, :est, :fn,
                 :estado, :origen, :acepta, :pver,
                 :vth, :vte,
                 :ip, :ua, :ca, :ua2)',
            [
                'id'     => $a['id'],
                'tid'    => $a['tenant_id'],
                'tdoc'   => $a['tipo_documento'],
                'doc'    => $a['documento'],
                'ap'     => $a['apellidos'],
                'no'     => $a['nombres'],
                'cel'    => $a['celular'],
                'co'     => $a['correo'],
                'con'    => $a['correo_normalizado'],
                'pwh'    => $a['password_hash'],
                'est'    => $a['estatura_cm'],
                'fn'     => $a['fecha_nacimiento'],
                'estado' => 'nuevo',
                'origen' => $a['origen'],
                'acepta' => $a['acepta_politicas'] ? 1 : 0,
                'pver'   => $a['politicas_version'],
                'vth'    => $a['verificacion_token_hash'],
                'vte'    => $a['verificacion_token_expira_at'],
                'ip'     => $ipBinary,
                'ua'     => $a['user_agent'],
                'ca'     => $a['created_at'],
                'ua2'    => $a['created_at'],
            ],
        );
    }

    // ======================= Revisión de la ficha del aspirante =======================
    // El aspirante completa su ficha con su usuario restringido, que vive en el
    // tenant de plataforma. El reclutador (en otro tenant) necesita revisarla, por
    // eso estas lecturas son CROSS-TENANT (sin filtro de tenant) — algo legítimo
    // aquí porque el aspirante es tenant-less hasta que se le aprueba.

    /**
     * Última ficha creada por el usuario del aspirante (sin filtro de tenant).
     *
     * @return array<string, mixed>|null
     */
    public function findFichaDeUsuario(string $usuarioId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM fichas WHERE created_by = :uid AND estado <> 'ANULADA' ORDER BY created_at DESC LIMIT 1",
            ['uid' => $usuarioId],
        );
    }

    /** @return list<array<string, mixed>> */
    public function fichaTrabajos(string $fichaId): array
    {
        return $this->db->fetchAll('SELECT * FROM fichas_trabajos_anteriores WHERE ficha_id = :f ORDER BY orden', ['f' => $fichaId]);
    }

    /** @return list<array<string, mixed>> */
    public function fichaHijos(string $fichaId): array
    {
        return $this->db->fetchAll('SELECT * FROM fichas_hijos_menores WHERE ficha_id = :f ORDER BY orden', ['f' => $fichaId]);
    }

    /** @return list<array<string, mixed>> */
    public function fichaReferencias(string $fichaId): array
    {
        return $this->db->fetchAll('SELECT * FROM fichas_referencias WHERE ficha_id = :f ORDER BY orden', ['f' => $fichaId]);
    }

    /** @return list<array<string, mixed>> */
    public function fichaCursos(string $fichaId): array
    {
        return $this->db->fetchAll(
            'SELECT fc.*, cc.codigo, cc.nombre AS curso_nombre
               FROM fichas_cursos fc
               INNER JOIN catalogo_cursos cc ON cc.id = fc.curso_id
              WHERE fc.ficha_id = :f
              ORDER BY cc.orden',
            ['f' => $fichaId],
        );
    }

    /**
     * Mueve al usuario y TODAS sus fichas (con sus filas dependientes) al tenant
     * destino. Se usa al aprobar: el empleado y su ficha pasan del tenant de
     * plataforma al tenant de la empresa del reclutador.
     */
    public function moverUsuarioYFichasATenant(string $usuarioId, string $tenantDestino): void
    {
        $now = Clock::nowString();

        $this->db->execute(
            'UPDATE usuarios SET tenant_id = :t, updated_at = :now WHERE id = :uid',
            ['t' => $tenantDestino, 'now' => $now, 'uid' => $usuarioId],
        );

        $filas = $this->db->fetchAll('SELECT id FROM fichas WHERE created_by = :uid', ['uid' => $usuarioId]);
        if ($filas === []) {
            return;
        }

        $bind = ['t' => $tenantDestino];
        $placeholders = [];
        foreach ($filas as $i => $fila) {
            $key = "f{$i}";
            $placeholders[] = ":{$key}";
            $bind[$key] = (string) $fila['id'];
        }
        $in = implode(', ', $placeholders);

        // Filas dependientes por ficha_id (todas son tenant-aware) y la propia ficha.
        foreach ([
            'fichas_trabajos_anteriores',
            'fichas_hijos_menores',
            'fichas_referencias',
            'fichas_cursos',
            'expedientes',
            'asignaciones',
        ] as $tabla) {
            $this->db->execute("UPDATE {$tabla} SET tenant_id = :t WHERE ficha_id IN ({$in})", $bind);
        }
        $this->db->execute("UPDATE fichas SET tenant_id = :t WHERE id IN ({$in})", $bind);
    }

    public function existeDocumento(string $documento): bool
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM aspirantes WHERE documento = :doc',
            ['doc' => $documento],
        ) > 0;
    }

    public function existeCorreo(string $correoNormalizado): bool
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM aspirantes WHERE correo_normalizado = :co',
            ['co' => $correoNormalizado],
        ) > 0;
    }

    /**
     * El correo tampoco debe pertenecer a un usuario del sistema.
     */
    public function existeCorreoEnUsuarios(string $correoNormalizado): bool
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM usuarios WHERE email_normalizado = :co',
            ['co' => $correoNormalizado],
        ) > 0;
    }

    /**
     * Busca un aspirante por el hash de su token de verificacion de email.
     *
     * @return array{id: string, nombres: string, apellidos: string, email_verificado: int, expira_at: ?string}|null
     */
    public function findByTokenVerificacion(string $tokenHash): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT id, nombres, apellidos, email_verificado,
                    verificacion_token_expira_at AS expira_at
               FROM aspirantes
              WHERE verificacion_token_hash = :h',
            ['h' => $tokenHash],
        );

        if ($row === null) {
            return null;
        }

        return [
            'id'               => (string) $row['id'],
            'nombres'          => (string) $row['nombres'],
            'apellidos'        => (string) $row['apellidos'],
            'email_verificado' => (int) $row['email_verificado'],
            'expira_at'        => $row['expira_at'] !== null ? (string) $row['expira_at'] : null,
        ];
    }

    /**
     * Marca el email como verificado y consume el token (single-use).
     */
    public function marcarEmailVerificado(string $id, string $cuando): void
    {
        $this->db->execute(
            'UPDATE aspirantes
                SET email_verificado = 1,
                    email_verificado_at = :cuando,
                    verificacion_token_hash = NULL,
                    verificacion_token_expira_at = NULL,
                    updated_at = :cuando2
              WHERE id = :id',
            ['cuando' => $cuando, 'cuando2' => $cuando, 'id' => $id],
        );
    }

    /**
     * Cuenta postulaciones recientes desde una IP. Sirve de rate-limit
     * anti-spam antes de aceptar un nuevo registro.
     */
    public function contarIntentosRecientes(string $ip, int $ventanaMinutos = 60): int
    {
        $ipBinary = @inet_pton($ip);
        if ($ipBinary === false) {
            return 0;
        }

        $desde = Clock::now()->modify("-{$ventanaMinutos} minutes")->format('Y-m-d H:i:s.u');

        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM aspirantes WHERE ip = :ip AND created_at >= :desde',
            ['ip' => $ipBinary, 'desde' => $desde],
        );
    }

    // ======================= Panel de reclutamiento (Fase 4) =======================

    /**
     * @param array{q?: ?string, estado?: ?string, verificado?: ?string} $filtros
     * @return list<array<string, mixed>>
     */
    public function listarAdmin(array $filtros, int $limit, int $offset): array
    {
        [$where, $bind] = $this->whereAdmin($filtros);

        $sql = "SELECT id, tipo_documento, documento, nombres, apellidos, correo, celular,
                       estado, email_verificado, usuario_id, created_at
                  FROM aspirantes
                 {$where}
                 ORDER BY created_at DESC
                 LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

        return $this->db->fetchAll($sql, $bind);
    }

    /**
     * @param array{q?: ?string, estado?: ?string, verificado?: ?string} $filtros
     */
    public function contarAdmin(array $filtros): int
    {
        [$where, $bind] = $this->whereAdmin($filtros);
        return (int) $this->db->fetchScalar("SELECT COUNT(*) FROM aspirantes {$where}", $bind);
    }

    /**
     * @return array{total: int, pendientes: int, verificados: int, aprobados: int, descartados: int}
     */
    public function kpisAdmin(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total,
                SUM(email_verificado = 0 AND estado = 'nuevo') AS pendientes,
                SUM(email_verificado = 1 AND estado IN ('nuevo', 'aspirante', 'en_revision', 'contactado')) AS verificados,
                SUM(estado = 'aprobado') AS aprobados,
                SUM(estado = 'descartado') AS descartados
             FROM aspirantes",
        ) ?? [];

        return [
            'total'       => (int) ($row['total'] ?? 0),
            'pendientes'  => (int) ($row['pendientes'] ?? 0),
            'verificados' => (int) ($row['verificados'] ?? 0),
            'aprobados'   => (int) ($row['aprobados'] ?? 0),
            'descartados' => (int) ($row['descartados'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDetalle(string $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM aspirantes WHERE id = :id', ['id' => $id]);
    }

    /**
     * Datos del registro del aspirante asociado a un usuario ya creado. Se usan
     * para prefijar (y bloquear) los campos correspondientes cuando el aspirante
     * completa su ficha en /guardias/crear en su primer ingreso.
     *
     * @return array{tipo_documento: string, documento: string, apellidos: string,
     *     nombres: string, celular: string, correo: string, estatura_cm: int,
     *     fecha_nacimiento: string}|null
     */
    public function findRegistroByUsuarioId(string $usuarioId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT tipo_documento, documento, apellidos, nombres, celular,
                    correo, estatura_cm, fecha_nacimiento
               FROM aspirantes
              WHERE usuario_id = :uid
              LIMIT 1',
            ['uid' => $usuarioId],
        );

        if ($row === null) {
            return null;
        }

        return [
            'tipo_documento'   => (string) ($row['tipo_documento'] ?? ''),
            'documento'        => (string) ($row['documento'] ?? ''),
            'apellidos'        => (string) ($row['apellidos'] ?? ''),
            'nombres'          => (string) ($row['nombres'] ?? ''),
            'celular'          => (string) ($row['celular'] ?? ''),
            'correo'           => (string) ($row['correo'] ?? ''),
            'estatura_cm'      => (int) ($row['estatura_cm'] ?? 0),
            'fecha_nacimiento' => (string) ($row['fecha_nacimiento'] ?? ''),
        ];
    }

    /**
     * Busca el rol por codigo, prefiriendo el rol propio del tenant sobre el de sistema.
     */
    public function buscarRolId(string $codigo, string $tenantId): ?string
    {
        $id = $this->db->fetchScalar(
            'SELECT id FROM roles
              WHERE codigo = :codigo AND (BINARY tenant_id = BINARY :tid OR tenant_id IS NULL)
              ORDER BY (tenant_id IS NULL) ASC
              LIMIT 1',
            ['codigo' => $codigo, 'tid' => $tenantId],
        );

        return $id !== null ? (string) $id : null;
    }

    /**
     * @param array{id: string, tenant_id: string, email: string, email_normalizado: string,
     *     password_hash: string, nombres: string, apellidos: string, telefono: ?string,
     *     cargo: ?string, created_by: ?string, cuando: string} $u
     */
    public function crearUsuario(array $u): void
    {
        $this->db->execute(
            'INSERT INTO usuarios
                (id, tenant_id, email, email_normalizado, password_hash, nombres, apellidos,
                 telefono, cargo, estado, mfa_habilitado, intentos_fallidos, debe_cambiar_password,
                 password_changed_at, created_at, updated_at, created_by)
             VALUES
                (:id, :tid, :em, :emn, :pw, :no, :ap,
                 :tel, :cargo, :estado, 0, 0, 0,
                 :pca, :ca, :ua, :cb)',
            [
                'id'     => $u['id'],
                'tid'    => $u['tenant_id'],
                'em'     => $u['email'],
                'emn'    => $u['email_normalizado'],
                'pw'     => $u['password_hash'],
                'no'     => $u['nombres'],
                'ap'     => $u['apellidos'],
                'tel'    => $u['telefono'],
                'cargo'  => $u['cargo'],
                'estado' => 'activo',
                'pca'    => $u['cuando'],
                'ca'     => $u['cuando'],
                'ua'     => $u['cuando'],
                'cb'     => $u['created_by'],
            ],
        );
    }

    public function asignarRol(string $usuarioId, string $roleId, ?string $asignadoPor, string $cuando): void
    {
        $this->db->execute(
            'INSERT INTO usuarios_roles (usuario_id, role_id, assigned_at, assigned_by)
             VALUES (:uid, :rid, :now, :by)',
            ['uid' => $usuarioId, 'rid' => $roleId, 'now' => $cuando, 'by' => $asignadoPor],
        );
    }

    public function marcarAprobado(string $aspiranteId, string $usuarioId, string $tenantId, ?string $revisadoPor, string $cuando): void
    {
        $this->db->execute(
            "UPDATE aspirantes
                SET estado = 'aprobado',
                    usuario_id = :uid,
                    tenant_id = :tid,
                    aprobado_at = :cuando,
                    revisado_por = :por,
                    updated_at = :cuando2
              WHERE id = :id",
            ['uid' => $usuarioId, 'tid' => $tenantId, 'cuando' => $cuando, 'por' => $revisadoPor, 'cuando2' => $cuando, 'id' => $aspiranteId],
        );
    }

    public function marcarDescartado(string $aspiranteId, ?string $motivo, ?string $revisadoPor, string $cuando): void
    {
        $this->db->execute(
            "UPDATE aspirantes
                SET estado = 'descartado',
                    motivo_descarte = :motivo,
                    revisado_por = :por,
                    updated_at = :cuando
              WHERE id = :id",
            ['motivo' => $motivo, 'por' => $revisadoPor, 'cuando' => $cuando, 'id' => $aspiranteId],
        );
    }

    // ======================= Flujo seguro de registro (Fase 5) =======================

    /**
     * Resuelve el tenant de plataforma (operador). Es donde queda el usuario
     * restringido del aspirante hasta que un admin lo promueva a otra empresa.
     */
    public function tenantPlataforma(): ?string
    {
        $id = $this->db->fetchScalar(
            "SELECT id FROM tenants
              ORDER BY (platform_marker = 'Y') DESC, created_at ASC
              LIMIT 1",
        );

        return $id !== null ? (string) $id : null;
    }

    /**
     * Resuelve el tenant operativo GEMESEG para el registro publico.
     */
    public function tenantGemeseg(): ?string
    {
        $id = $this->db->fetchScalar(
            "SELECT id FROM tenants
              WHERE UPPER(razon_social) LIKE '%GEMESEG%'
                 OR UPPER(COALESCE(nombre_comercial, '')) LIKE '%GEMESEG%'
              ORDER BY (estado = 'activo') DESC, created_at ASC
              LIMIT 1",
        );

        return $id !== null ? (string) $id : null;
    }

    /**
     * Garantiza el rol restringido del aspirante dentro del tenant destino.
     */
    public function asegurarRolAspirante(string $tenantId, string $cuando): ?string
    {
        $roleId = $this->buscarRolId('aspirante', $tenantId);
        if ($roleId === null) {
            $roleId = IdGenerator::ulid();
            $this->db->execute(
                "INSERT INTO roles (id, tenant_id, codigo, nombre, descripcion, es_sistema, created_at, updated_at)
                 SELECT :id, :tid, 'aspirante', 'Aspirante (registro de guardia)',
                        'Acceso restringido: solo puede completar su propia ficha de guardia',
                        0, :ca, :ua
                   WHERE NOT EXISTS (
                       SELECT 1 FROM roles WHERE BINARY tenant_id = BINARY :tid_lookup AND codigo = 'aspirante'
                   )",
                [
                    'id' => $roleId,
                    'tid' => $tenantId,
                    'ca' => $cuando,
                    'ua' => $cuando,
                    'tid_lookup' => $tenantId,
                ],
            );

            $roleId = $this->buscarRolId('aspirante', $tenantId);
        }

        if ($roleId === null) {
            return null;
        }

        $this->db->execute(
            "INSERT INTO roles_permisos (role_id, permiso_id, granted_at)
             SELECT :rid, p.id, :granted
               FROM permisos p
              WHERE p.codigo = 'guardias.crear'
                AND NOT EXISTS (
                    SELECT 1
                      FROM roles_permisos rp
                     WHERE rp.role_id = :rid_lookup AND rp.permiso_id = p.id
                )",
            [
                'rid' => $roleId,
                'granted' => $cuando,
                'rid_lookup' => $roleId,
            ],
        );

        return $roleId;
    }

    /**
     * Marca el correo verificado y promueve estado a 'aspirante', enlazando el
     * usuario restringido recien creado. Consume el token (single-use).
     */
    public function marcarAspirante(string $aspiranteId, string $usuarioId, string $tenantId, string $cuando): void
    {
        $this->db->execute(
            "UPDATE aspirantes
                SET estado = 'aspirante',
                    email_verificado = 1,
                    email_verificado_at = :cuando,
                    usuario_id = :uid,
                    tenant_id = :tid,
                    verificacion_token_hash = NULL,
                    verificacion_token_expira_at = NULL,
                    updated_at = :cuando2
              WHERE id = :id",
            ['cuando' => $cuando, 'uid' => $usuarioId, 'tid' => $tenantId, 'cuando2' => $cuando, 'id' => $aspiranteId],
        );
    }

    /**
     * Para el reenvio de enlace: aspirante con ese correo que AUN no validó.
     *
     * @return array{id: string, nombres: string, apellidos: string, correo: string}|null
     */
    public function findPendientePorCorreo(string $correoNormalizado): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, nombres, apellidos, correo
               FROM aspirantes
              WHERE correo_normalizado = :co
                AND email_verificado = 0
                AND estado = 'nuevo'
              LIMIT 1",
            ['co' => $correoNormalizado],
        );

        if ($row === null) {
            return null;
        }

        return [
            'id'        => (string) $row['id'],
            'nombres'   => (string) $row['nombres'],
            'apellidos' => (string) $row['apellidos'],
            'correo'    => (string) $row['correo'],
        ];
    }

    /**
     * Regenera el token de verificacion (nuevo hash + nueva expiracion 48h).
     */
    public function regenerarTokenVerificacion(string $aspiranteId, string $tokenHash, string $expira, string $cuando): void
    {
        $this->db->execute(
            'UPDATE aspirantes
                SET verificacion_token_hash = :hash,
                    verificacion_token_expira_at = :expira,
                    updated_at = :cuando
              WHERE id = :id',
            ['hash' => $tokenHash, 'expira' => $expira, 'cuando' => $cuando, 'id' => $aspiranteId],
        );
    }

    public function quitarRol(string $usuarioId, string $roleId): void
    {
        $this->db->execute(
            'DELETE FROM usuarios_roles WHERE usuario_id = :uid AND role_id = :rid',
            ['uid' => $usuarioId, 'rid' => $roleId],
        );
    }

    public function actualizarEstadoUsuario(string $usuarioId, string $estado, string $cuando): void
    {
        $this->db->execute(
            'UPDATE usuarios SET estado = :estado, updated_at = :cuando WHERE id = :id',
            ['estado' => $estado, 'cuando' => $cuando, 'id' => $usuarioId],
        );
    }

    /**
     * @param array{q?: ?string, estado?: ?string, verificado?: ?string} $filtros
     * @return array{0: string, 1: array<string, scalar|null>}
     */
    private function whereAdmin(array $filtros): array
    {
        $conds = [];
        $bind = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $conds[] = '(nombres LIKE :q OR apellidos LIKE :q OR correo LIKE :q OR documento LIKE :q)';
            $bind['q'] = '%' . $q . '%';
        }

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $conds[] = 'estado = :estado';
            $bind['estado'] = $estado;
        }

        $verificado = (string) ($filtros['verificado'] ?? '');
        if ($verificado === '0' || $verificado === '1') {
            $conds[] = 'email_verificado = :verificado';
            $bind['verificado'] = (int) $verificado;
        }

        $where = $conds === [] ? '' : 'WHERE ' . implode(' AND ', $conds);

        return [$where, $bind];
    }
}
