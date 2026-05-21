<?php

declare(strict_types=1);

namespace DigiSecure\Security\Rbac\Controller;

use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Security\Rbac\Application\GestionarRolesService;
use DigiSecure\Security\Rbac\RoleRepository;
use DigiSecure\Shared\Domain\DomainException;

/**
 * CRUD de roles y permisos.
 *
 * GET  /roles              → listado
 * GET  /roles/crear        → form de creación
 * POST /roles/crear        → crear rol
 * GET  /roles/{id}         → detalle (read-only con matriz de permisos)
 * GET  /roles/{id}/editar  → form de edición
 * POST /roles/{id}/editar  → actualizar rol + permisos
 * POST /roles/{id}/eliminar → eliminar rol
 */
final class RoleController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly RoleRepository $repo,
        private readonly GestionarRolesService $gestionarService,
        private readonly FlashBag $flash,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $roles = $this->repo->listAccesibles();

        return $this->view->response('roles/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'roles' => $roles,
        ]);
    }

    public function show(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $rol = $this->repo->findById($id);
        if ($rol === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        $permisos = $this->repo->permisosByRoleId($id);
        $catalogo = $this->repo->catalogoPermisosByRecurso();

        return $this->view->response('roles/show', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'rol' => $rol,
            'permisos' => $permisos,
            'catalogo' => $catalogo,
        ]);
    }

    public function createForm(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $catalogo = $this->repo->catalogoPermisosByRecurso();

        return $this->view->response('roles/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'catalogo' => $catalogo,
        ]);
    }

    public function store(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $id = $this->gestionarService->crear($request->post, $user->id, $request->clientIp());
            $this->flash->success('Rol creado correctamente.');
            return Response::redirect($this->securePath($request, "/roles/{$id}"));
        } catch (DomainException $e) {
            $catalogo = $this->repo->catalogoPermisosByRecurso();
            return $this->view->response('roles/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/roles/crear',
                'catalogo' => $catalogo,
                'input' => $request->post,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function editForm(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $id = (string) $request->attribute('route.id');

        $rol = $this->repo->findById($id);
        if ($rol === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        $catalogo = $this->repo->catalogoPermisosByRecurso();
        $asignadoIds = $this->repo->permisoIdsByRoleId($id);

        return $this->view->response('roles/edit', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'rol' => $rol,
            'catalogo' => $catalogo,
            'asignadoIds' => $asignadoIds,
        ]);
    }

    public function update(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $id = (string) $request->attribute('route.id');

        try {
            $this->gestionarService->actualizar($id, $request->post, $user->id, $request->clientIp());
            $this->flash->success('Rol actualizado correctamente.');
            return Response::redirect($this->securePath($request, "/roles/{$id}"));
        } catch (DomainException $e) {
            $rol = $this->repo->findById($id);
            $catalogo = $this->repo->catalogoPermisosByRecurso();
            $asignadoIds = $this->repo->permisoIdsByRoleId($id);

            return $this->view->response('roles/edit', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => "/roles/{$id}/editar",
                'rol' => $rol !== null ? array_merge($rol, $request->post) : $request->post,
                'catalogo' => $catalogo,
                'asignadoIds' => $asignadoIds,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $id = (string) $request->attribute('route.id');

        try {
            $this->gestionarService->eliminar($id, $user->id, $request->clientIp());
            $this->flash->success('Rol eliminado correctamente.');
            return Response::redirect('/roles');
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo eliminar el rol');
            return Response::redirect($this->securePath($request, "/roles/{$id}"));
        }
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
}
