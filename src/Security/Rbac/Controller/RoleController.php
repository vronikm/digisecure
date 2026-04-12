<?php

declare(strict_types=1);

namespace DigiSecure\Security\Rbac\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Security\Rbac\RoleRepository;

/**
 * Visor de roles y permisos (read-only).
 *
 * GET /roles            → listado
 * GET /roles/{id}       → detalle del rol con permisos asignados
 */
final class RoleController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly RoleRepository $repo,
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
}
