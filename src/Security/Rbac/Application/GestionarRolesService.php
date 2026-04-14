<?php

declare(strict_types=1);

namespace DigiSecure\Security\Rbac\Application;

use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Rbac\RoleRepository;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\IdGenerator;

/**
 * Gestión de roles propios del tenant.
 *
 * Reglas de negocio:
 *   - Los roles del sistema (es_sistema=1) NO se pueden editar ni eliminar.
 *   - El código del rol debe ser único dentro del tenant + sistema.
 *   - No se puede eliminar un rol que tiene usuarios asignados.
 *   - Los permisos se sincronizan en bloque (replace all).
 */
final class GestionarRolesService
{
    public function __construct(
        private readonly RoleRepository $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Crea un nuevo rol propio del tenant.
     *
     * @param array<string, mixed> $input  {codigo, nombre, descripcion?, permisos_ids?}
     */
    public function crear(array $input, string $usuarioId, string $ip): string
    {
        $codigo = $this->sanitizeCodigo($input['codigo'] ?? '');
        $nombre = trim((string) ($input['nombre'] ?? ''));

        if ($codigo === '') {
            throw new DomainException('El código del rol es obligatorio.');
        }
        if ($nombre === '') {
            throw new DomainException('El nombre del rol es obligatorio.');
        }
        if (strlen($codigo) > 50) {
            throw new DomainException('El código del rol no debe superar 50 caracteres.');
        }

        // Verificar unicidad
        $existing = $this->repo->findByCodigo($codigo);
        if ($existing !== null) {
            throw new DomainException("Ya existe un rol con el código '{$codigo}'.");
        }

        $id = IdGenerator::ulid();
        $descripcion = trim((string) ($input['descripcion'] ?? '')) ?: null;

        $this->repo->create($id, $codigo, $nombre, $descripcion);

        // Asignar permisos si se enviaron
        $permisoIds = $this->extractPermisoIds($input);
        if ($permisoIds !== []) {
            $this->repo->syncPermisos($id, $permisoIds);
        }

        $this->audit->log(
            'role.creado',
            $usuarioId,
            'role',
            $id,
            ['codigo' => $codigo, 'permisos_count' => count($permisoIds)],
            ip: $ip,
        );

        return $id;
    }

    /**
     * Actualiza un rol existente del tenant.
     *
     * @param array<string, mixed> $input  {nombre, descripcion?, permisos_ids?}
     */
    public function actualizar(string $id, array $input, string $usuarioId, string $ip): void
    {
        $rol = $this->repo->findById($id);
        if ($rol === null) {
            throw new DomainException('Rol no encontrado.');
        }
        if ((int) $rol['es_sistema'] === 1) {
            throw new DomainException('No se puede editar un rol del sistema.');
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new DomainException('El nombre del rol es obligatorio.');
        }

        $descripcion = trim((string) ($input['descripcion'] ?? '')) ?: null;

        $this->repo->update($id, $nombre, $descripcion);

        // Sincronizar permisos
        $permisoIds = $this->extractPermisoIds($input);
        $this->repo->syncPermisos($id, $permisoIds);

        $this->audit->log(
            'role.actualizado',
            $usuarioId,
            'role',
            $id,
            ['codigo' => $rol['codigo'], 'permisos_count' => count($permisoIds)],
            ip: $ip,
        );
    }

    /**
     * Elimina un rol del tenant.
     */
    public function eliminar(string $id, string $usuarioId, string $ip): void
    {
        $rol = $this->repo->findById($id);
        if ($rol === null) {
            throw new DomainException('Rol no encontrado.');
        }
        if ((int) $rol['es_sistema'] === 1) {
            throw new DomainException('No se puede eliminar un rol del sistema.');
        }

        $usersCount = $this->repo->countUsuariosByRoleId($id);
        if ($usersCount > 0) {
            throw new DomainException(
                "No se puede eliminar el rol '{$rol['nombre']}': tiene {$usersCount} usuario(s) asignado(s). "
                . 'Reasigne los usuarios a otro rol primero.',
            );
        }

        $this->repo->delete($id);

        $this->audit->log(
            'role.eliminado',
            $usuarioId,
            'role',
            $id,
            ['codigo' => $rol['codigo']],
            nivel: 'warning',
            ip: $ip,
        );
    }

    /**
     * Normaliza el código del rol: minúsculas, sin espacios, solo alfanuméricos y guiones bajos.
     */
    private function sanitizeCodigo(string $raw): string
    {
        $clean = strtolower(trim($raw));
        $clean = (string) preg_replace('/[^a-z0-9_]/', '_', $clean);
        return (string) preg_replace('/_{2,}/', '_', trim($clean, '_'));
    }

    /**
     * Extrae IDs de permisos del input (array de checkbox IDs).
     *
     * @return list<string>
     */
    private function extractPermisoIds(array $input): array
    {
        $raw = $input['permisos_ids'] ?? $input['permisos'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_filter(
            array_map('trim', $raw),
            static fn(string $v) => $v !== '',
        ));
    }
}
