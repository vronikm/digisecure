<?php

declare(strict_types=1);

namespace DigiSecure\User\Controller;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Subscription\Application\VerificarLimiteService;
use DigiSecure\Tenant\Domain\TenantContext;
use DigiSecure\User\Application\AdministrarUsuariosService;
use DigiSecure\User\Infrastructure\UserRepository;

/**
 * Controller de administración de usuarios del tenant.
 *
 * Rutas:
 *   GET    /usuarios                  → listado
 *   GET    /usuarios/crear            → formulario de creación
 *   POST   /usuarios/crear            → procesar creación
 *   GET    /usuarios/{id}/editar      → formulario de edición
 *   POST   /usuarios/{id}/editar      → procesar edición
 *   POST   /usuarios/{id}/estado      → cambiar estado (activo/inactivo/bloqueado)
 *   POST   /usuarios/{id}/password    → resetear contraseña
 */
final class UserController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly UserRepository $repo,
        private readonly AdministrarUsuariosService $service,
        private readonly PdoConnection $db,
        private readonly TenantContext $tenantContext,
        private readonly VerificarLimiteService $verificarLimite,
        private readonly FlashBag $flash,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $esSuperAdmin = $user->esSuperAdmin();

        $page = max(1, (int) $request->query('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;
        $estado = $request->query('estado');
        $estadoFiltro = is_string($estado) && $estado !== '' ? $estado : null;

        if ($esSuperAdmin) {
            $usuarios = $this->repo->listGlobalOrderedByTenant($limit, $offset, $estadoFiltro);
            $total = $this->repo->countGlobal($estadoFiltro);

            $tenantIds = array_values(array_unique(array_filter(
                array_map(
                    static fn(array $u): string => trim((string) ($u['tenant_id'] ?? '')),
                    $usuarios,
                ),
            )));
            $tenantNames = $this->loadTenantNamesByIds($tenantIds);

            foreach ($usuarios as &$u) {
                $tid = trim((string) ($u['tenant_id'] ?? ''));
                $u['tenant_nombre'] = $tenantNames[$tid] ?? 'Sin empresa';
            }
            unset($u);
        } else {
            $usuarios = $this->repo->listByTenant($limit, $offset, $estadoFiltro);
            $total = $this->repo->countByTenant($estadoFiltro);
        }

        $totalPages = max(1, (int) ceil($total / $limit));

        // Cargar roles por usuario para mostrar en la tabla
        $userIds = array_column($usuarios, 'id');
        $rolesByUser = $this->loadRolesByUserIds($userIds);

        return $this->view->response('user/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'esSuperAdmin' => $esSuperAdmin,
            'usuarios' => $usuarios,
            'rolesByUser' => $rolesByUser,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => ['estado' => $estadoFiltro],
        ]);
    }

    public function create(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $esSuperAdmin = $user->esSuperAdmin();
        $systemTenantId = $esSuperAdmin
            ? $this->resolvePlatformTenantIdOrFallback($user->tenantId)
            : $user->tenantId;

        return $this->view->response('user/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'roles' => $esSuperAdmin ? $this->loadSystemRolesCatalog() : $this->loadRolesAccesibles(),
            'esSuperAdmin' => $esSuperAdmin,
            'tenants' => $esSuperAdmin ? $this->loadTenantsCatalog() : [],
            'systemTenantId' => $systemTenantId,
            'input' => [],
        ]);
    }

    public function store(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;
        $esSuperAdmin = $user->esSuperAdmin();
        $systemTenantId = $esSuperAdmin
            ? $this->resolvePlatformTenantIdOrFallback($user->tenantId)
            : $user->tenantId;

        try {
            if (!$esSuperAdmin) {
                $this->verificarLimite->verificarCrearUsuario();
            }
            $this->service->crear($input, $user->id, $request->clientIp(), $esSuperAdmin, $user->tenantId);
            $this->flash->success('Usuario creado correctamente.');
            return Response::redirect('/usuarios');
        } catch (DomainException $e) {
            return $this->view->response('user/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/usuarios/crear',
                'roles' => $esSuperAdmin ? $this->loadSystemRolesCatalog() : $this->loadRolesAccesibles(),
                'esSuperAdmin' => $esSuperAdmin,
                'tenants' => $esSuperAdmin ? $this->loadTenantsCatalog() : [],
                'systemTenantId' => $systemTenantId,
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function edit(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $esSuperAdmin = $user->esSuperAdmin();
        $systemTenantId = $esSuperAdmin
            ? $this->resolvePlatformTenantIdOrFallback($user->tenantId)
            : $user->tenantId;

        $usuario = $this->findUsuario($id, $esSuperAdmin);
        if ($usuario === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        $rolesAsignados = $this->loadRolesByUserIds([$id])[$id] ?? [];
        $tenantActualNombre = $this->loadTenantNombreById((string) ($usuario['tenant_id'] ?? ''));

        return $this->view->response('user/edit', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'usuario' => $usuario,
            'roles' => $esSuperAdmin ? $this->loadSystemRolesCatalog() : $this->loadRolesAccesibles(),
            'rolActualId' => $rolesAsignados[0]['id'] ?? null,
            'rolActualCodigo' => $rolesAsignados[0]['codigo'] ?? null,
            'esSuperAdmin' => $esSuperAdmin,
            'tenants' => $esSuperAdmin ? $this->loadTenantsCatalog() : [],
            'systemTenantId' => $systemTenantId,
            'tenantActualNombre' => $tenantActualNombre,
            'input' => [],
        ]);
    }

    public function update(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;
        $esSuperAdmin = $user->esSuperAdmin();
        $systemTenantId = $esSuperAdmin
            ? $this->resolvePlatformTenantIdOrFallback($user->tenantId)
            : $user->tenantId;

        try {
            $this->service->actualizar($id, $input, $user->id, $request->clientIp(), $esSuperAdmin, $user->tenantId);
            $this->flash->success('Usuario actualizado correctamente.');
            return Response::redirect('/usuarios');
        } catch (DomainException $e) {
            $usuario = $this->findUsuario($id, $esSuperAdmin);
            $rolesAsignados = $this->loadRolesByUserIds([$id])[$id] ?? [];
            $tenantActualNombre = $usuario !== null
                ? $this->loadTenantNombreById((string) ($usuario['tenant_id'] ?? ''))
                : '';
            return $this->view->response('user/edit', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'usuario' => $usuario,
                'roles' => $esSuperAdmin ? $this->loadSystemRolesCatalog() : $this->loadRolesAccesibles(),
                'rolActualId' => $rolesAsignados[0]['id'] ?? null,
                'rolActualCodigo' => $rolesAsignados[0]['codigo'] ?? null,
                'esSuperAdmin' => $esSuperAdmin,
                'tenants' => $esSuperAdmin ? $this->loadTenantsCatalog() : [],
                'systemTenantId' => $systemTenantId,
                'tenantActualNombre' => $tenantActualNombre,
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function cambiarEstado(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $nuevoEstado = (string) $request->post('estado', '');

        try {
            $this->service->cambiarEstado($id, $nuevoEstado, $user->id, $request->clientIp());
            $this->flash->success("Estado actualizado a {$nuevoEstado}.");
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo cambiar el estado');
        }
        return Response::redirect('/usuarios');
    }

    public function resetPassword(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $newPassword = (string) $request->post('new_password', '');

        try {
            $this->service->resetearPassword(
                $id,
                $newPassword,
                $user->id,
                $request->clientIp(),
                $user->esSuperAdmin(),
            );
            $this->flash->success('Contraseña restablecida correctamente.');
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo restablecer la contraseña');
            return Response::redirect($this->securePath($request, "/usuarios/{$id}/editar"));
        }
        return Response::redirect($this->securePath($request, "/usuarios/{$id}/editar"));
    }

    /**
     * @return list<array{id:string, codigo:string, nombre:string, descripcion:?string}>
     */
    private function loadRolesAccesibles(): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        return $this->db->fetchAll(
            'SELECT id, codigo, nombre, descripcion
             FROM roles r
             WHERE r.codigo <> :codigo_superadmin
               AND (
                    r.tenant_id = :tid
                    OR (
                        r.tenant_id IS NULL
                        AND NOT EXISTS (
                            SELECT 1
                              FROM roles rt
                             WHERE rt.tenant_id = :tid_shadow
                               AND rt.codigo = r.codigo
                        )
                    )
               )
             ORDER BY es_sistema ASC, nombre ASC',
            [
                'tid' => $tenantId,
                'tid_shadow' => $tenantId,
                'codigo_superadmin' => 'superadmin',
            ],
        );
    }

    /**
     * Catálogo de roles base de sistema para operaciones cross-tenant del superadmin.
     *
     * @return list<array{id:string, codigo:string, nombre:string, descripcion:?string}>
     */
    private function loadSystemRolesCatalog(): array
    {
        return $this->db->fetchAll(
            'SELECT id, codigo, nombre, descripcion
               FROM roles
              WHERE tenant_id IS NULL
              ORDER BY nombre ASC',
        );
    }

    /**
     * @return list<array{id:string, razon_social:string, ruc:string, estado:string}>
     */
    private function loadTenantsCatalog(): array
    {
        return $this->db->fetchAll(
            'SELECT id, razon_social, ruc, estado
               FROM tenants
              ORDER BY razon_social ASC',
        );
    }

    /**
     * @param list<string> $userIds
     * @return array<string, list<array{id:string, codigo:string, nombre:string}>>
     */
    private function loadRolesByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $placeholders = [];
        $bindings = [];
        foreach ($userIds as $i => $uid) {
            $key = "u{$i}";
            $placeholders[] = ":{$key}";
            $bindings[$key] = $uid;
        }
        $rows = $this->db->fetchAll(
            'SELECT ur.usuario_id, r.id, r.codigo, r.nombre
             FROM usuarios_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.usuario_id IN (' . implode(',', $placeholders) . ')
             ORDER BY ur.assigned_at DESC',
            $bindings,
        );

        $byUser = [];
        foreach ($rows as $row) {
            $byUser[$row['usuario_id']][] = [
                'id' => $row['id'],
                'codigo' => $row['codigo'],
                'nombre' => $row['nombre'],
            ];
        }
        return $byUser;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUsuario(string $id, bool $esSuperAdmin): ?array
    {
        if (!$esSuperAdmin) {
            return $this->repo->findUserById($id);
        }

        return $this->db->fetchOne(
            'SELECT * FROM usuarios WHERE id = :id',
            ['id' => $id],
        );
    }

    private function securePath(Request $request, string $path): string
    {
        $formatter = $request->attribute('secure_url');
        if (!\is_callable($formatter)) {
            return $path;
        }

        try {
            $secure = $formatter($path);
        } catch (\Throwable) {
            return $path;
        }

        return \is_string($secure) && $secure !== '' ? $secure : $path;
    }

    private function resolvePlatformTenantIdOrFallback(?string $fallbackTenantId): string
    {
        $row = $this->db->fetchOne(
            "SELECT id
               FROM tenants
              WHERE platform_marker = 'Y'
              LIMIT 1",
        );

        if ($row !== null) {
            return (string) $row['id'];
        }

        return trim((string) $fallbackTenantId);
    }

    private function loadTenantNombreById(string $tenantId): string
    {
        $tenantId = trim($tenantId);
        if ($tenantId === '') {
            return '';
        }

        $row = $this->db->fetchOne(
            'SELECT razon_social FROM tenants WHERE id = :id LIMIT 1',
            ['id' => $tenantId],
        );

        return $row !== null ? (string) $row['razon_social'] : '';
    }

    /**
     * @param list<string> $tenantIds
     * @return array<string, string>
     */
    private function loadTenantNamesByIds(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $placeholders = [];
        $bindings = [];
        foreach ($tenantIds as $i => $tenantId) {
            $key = "t{$i}";
            $placeholders[] = ":{$key}";
            $bindings[$key] = $tenantId;
        }

        $rows = $this->db->fetchAll(
            'SELECT id, razon_social
               FROM tenants
              WHERE id IN (' . implode(',', $placeholders) . ')',
            $bindings,
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['id']] = (string) $row['razon_social'];
        }

        return $map;
    }
}
