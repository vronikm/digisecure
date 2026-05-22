<?php

declare(strict_types=1);

namespace DigiSecure\User\Infrastructure;

use DigiSecure\Infrastructure\Persistence\BaseRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Repositorio de usuarios del sistema (tenant-aware).
 *
 * Opera SIEMPRE dentro del contexto del tenant actual.
 * Para operaciones pre-login (sin tenant), el Authenticator
 * accede a la BD directamente.
 */
final class UserRepository extends BaseRepository
{
    public function __construct(PdoConnection $db, TenantContext $tenantContext)
    {
        parent::__construct($db, $tenantContext);
    }

    protected function tableName(): string
    {
        return 'usuarios';
    }

    /**
     * Busca un usuario por ID dentro del tenant actual.
     *
     * @return array<string, mixed>|null
     */
    public function findUserById(string $id): ?array
    {
        return $this->findById($id);
    }

    /**
     * Busca un usuario por email dentro del tenant actual.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $emailNorm = mb_strtolower(trim($email), 'UTF-8');

        return $this->tenantQueryOne(
            'SELECT * FROM usuarios WHERE email_normalizado = :email',
            ['email' => $emailNorm],
        );
    }

    /**
     * Lista usuarios del tenant con paginación.
     *
     * @return list<array<string, mixed>>
     */
    public function listByTenant(int $limit = 50, int $offset = 0, ?string $estado = null): array
    {
        $where = '';
        $bindings = [];

        if ($estado !== null) {
            $where = 'estado = :estado';
            $bindings['estado'] = $estado;
        }

        $sql = 'SELECT id, email, nombres, apellidos, cargo, estado, ultimo_login_at, created_at
                FROM usuarios';
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        $sql .= ' ORDER BY apellidos, nombres LIMIT :limit OFFSET :offset';
        $bindings['limit'] = $limit;
        $bindings['offset'] = $offset;

        return $this->tenantQuery($sql, $bindings);
    }

    /**
     * Lista usuarios globales (cross-tenant), ordenados por tenant.
     * Uso exclusivo para vistas de Super Administrador.
     *
     * @return list<array<string, mixed>>
     */
    public function listGlobalOrderedByTenant(int $limit = 50, int $offset = 0, ?string $estado = null): array
    {
        $where = '';
        $bindings = [];

        if ($estado !== null) {
            $where = 'estado = :estado';
            $bindings['estado'] = $estado;
        }

        $sql = 'SELECT id, tenant_id, email, nombres, apellidos, cargo, estado, ultimo_login_at, created_at
                FROM usuarios';
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        $sql .= ' ORDER BY tenant_id ASC, apellidos ASC, nombres ASC LIMIT :limit OFFSET :offset';
        $bindings['limit'] = $limit;
        $bindings['offset'] = $offset;

        return $this->db->fetchAll($sql, $bindings);
    }

    /**
     * Cuenta usuarios del tenant.
     */
    public function countByTenant(?string $estado = null): int
    {
        $where = '';
        $bindings = [];
        if ($estado !== null) {
            $where = 'estado = :estado';
            $bindings['estado'] = $estado;
        }
        return $this->tenantCount($where, $bindings);
    }

    /**
     * Cuenta usuarios globales (cross-tenant).
     * Uso exclusivo para vistas de Super Administrador.
     */
    public function countGlobal(?string $estado = null): int
    {
        if ($estado === null) {
            return (int) $this->db->fetchScalar('SELECT COUNT(*) FROM usuarios');
        }

        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM usuarios WHERE estado = :estado',
            ['estado' => $estado],
        );
    }

    /**
     * Crea un nuevo usuario dentro del tenant actual.
     *
     * @param array<string, scalar|null> $data
     * @return string ID generado (ULID)
     */
    public function create(array $data): string
    {
        $id = IdGenerator::ulid();
        $now = Clock::nowString();

        $data['id'] = $id;
        $data['email_normalizado'] = mb_strtolower(trim((string)($data['email'] ?? '')), 'UTF-8');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $this->tenantInsert($data);

        return $id;
    }

    /**
     * Actualiza un usuario dentro del tenant.
     *
     * @param array<string, scalar|null> $data Columnas a actualizar
     * @return bool true si se actualizó, false si no se encontró
     */
    public function update(string $id, array $data): bool
    {
        $data['updated_at'] = Clock::nowString();
        if (isset($data['email'])) {
            $data['email_normalizado'] = mb_strtolower(trim((string)$data['email']), 'UTF-8');
        }
        return $this->tenantUpdate($id, $data) > 0;
    }

    /**
     * Asigna un rol a un usuario.
     */
    public function assignRole(string $usuarioId, string $roleId, ?string $assignedBy = null): void
    {
        $this->db->execute(
            'INSERT IGNORE INTO usuarios_roles (usuario_id, role_id, assigned_at, assigned_by)
             VALUES (:usuario_id, :role_id, :assigned_at, :assigned_by)',
            [
                'usuario_id' => $usuarioId,
                'role_id' => $roleId,
                'assigned_at' => Clock::nowString(),
                'assigned_by' => $assignedBy,
            ],
        );
    }

    /**
     * Verifica si un email ya existe en el tenant (para unicidad antes de crear/editar).
     */
    public function emailExistsInTenant(string $email, ?string $excludeUserId = null): bool
    {
        $emailNorm = mb_strtolower(trim($email), 'UTF-8');
        $where = 'email_normalizado = :email';
        $bindings = ['email' => $emailNorm];

        if ($excludeUserId !== null) {
            $where .= ' AND id != :exclude_id';
            $bindings['exclude_id'] = $excludeUserId;
        }

        return $this->tenantCount($where, $bindings) > 0;
    }
}
