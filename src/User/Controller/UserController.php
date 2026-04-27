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

        $page = max(1, (int) $request->query('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;
        $estado = $request->query('estado');
        $estadoFiltro = is_string($estado) && $estado !== '' ? $estado : null;

        $usuarios = $this->repo->listByTenant($limit, $offset, $estadoFiltro);
        $total = $this->repo->countByTenant($estadoFiltro);
        $totalPages = max(1, (int) ceil($total / $limit));

        // Cargar roles por usuario para mostrar en la tabla
        $userIds = array_column($usuarios, 'id');
        $rolesByUser = $this->loadRolesByUserIds($userIds);

        return $this->view->response('user/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
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

        return $this->view->response('user/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'roles' => $this->loadRolesAccesibles(),
            'input' => [],
        ]);
    }

    public function store(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;

        try {
            $this->verificarLimite->verificarCrearUsuario();
            $this->service->crear($input, $user->id, $request->clientIp());
            $this->flash->success('Usuario creado correctamente.');
            return Response::redirect('/usuarios');
        } catch (DomainException $e) {
            return $this->view->response('user/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/usuarios/crear',
                'roles' => $this->loadRolesAccesibles(),
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function edit(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $usuario = $this->repo->findUserById($id);
        if ($usuario === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        $rolesAsignados = $this->loadRolesByUserIds([$id])[$id] ?? [];

        return $this->view->response('user/edit', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'usuario' => $usuario,
            'roles' => $this->loadRolesAccesibles(),
            'rolActualId' => $rolesAsignados[0]['id'] ?? null,
            'input' => [],
        ]);
    }

    public function update(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;

        try {
            $this->service->actualizar($id, $input, $user->id, $request->clientIp());
            $this->flash->success('Usuario actualizado correctamente.');
            return Response::redirect('/usuarios');
        } catch (DomainException $e) {
            $usuario = $this->repo->findUserById($id);
            $rolesAsignados = $this->loadRolesByUserIds([$id])[$id] ?? [];
            return $this->view->response('user/edit', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'usuario' => $usuario,
                'roles' => $this->loadRolesAccesibles(),
                'rolActualId' => $rolesAsignados[0]['id'] ?? null,
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
            $this->service->resetearPassword($id, $newPassword, $user->id, $request->clientIp());
            $this->flash->success('Contrasena restablecida correctamente.');
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo restablecer la contrasena');
            return Response::redirect("/usuarios/{$id}/editar");
        }
        return Response::redirect('/usuarios');
    }

    /**
     * @return list<array{id:string, codigo:string, nombre:string, descripcion:?string}>
     */
    private function loadRolesAccesibles(): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        return $this->db->fetchAll(
            'SELECT id, codigo, nombre, descripcion
             FROM roles
             WHERE tenant_id = :tid OR tenant_id IS NULL
             ORDER BY es_sistema DESC, nombre ASC',
            ['tid' => $tenantId],
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
             WHERE ur.usuario_id IN (' . implode(',', $placeholders) . ')',
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
}
