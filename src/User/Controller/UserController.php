<?php

declare(strict_types=1);

namespace DigiSecure\User\Controller;

use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Paginacion;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Tenant\Domain\TenantContext;
use DigiSecure\User\Application\AdministrarUsuariosService;
use DigiSecure\User\Infrastructure\UserRepository;

/**
 * Controller de administración de usuarios de la empresa.
 *
 * Sistema a la medida de GEMESEG: todos los usuarios, incluido el
 * superadministrador, gestionan las cuentas de la empresa del contexto.
 * Las cuentas superadmin no aparecen ni se pueden editar desde aquí
 * (ver UserRepository::SIN_SUPERADMIN).
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
        private readonly FlashBag $flash,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $estado = $request->query('estado');
        $estadoFiltro = is_string($estado) && $estado !== '' ? $estado : null;
        $busqueda = trim((string) $request->query('q', ''));

        $paginacion = Paginacion::desdeRequest($request)
            ->conTotal($this->repo->countByTenant($estadoFiltro, $busqueda));
        $usuarios = $this->repo->listByTenant($paginacion->limite(), $paginacion->offset(), $estadoFiltro, $busqueda);

        $data = [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'usuarios' => $usuarios,
            'rolesByUser' => $this->loadRolesByUserIds(array_column($usuarios, 'id')),
            'paginacion' => $paginacion,
            'filters' => ['estado' => $estadoFiltro, 'q' => $busqueda],
        ];

        // Busqueda, filtros y paginacion en vivo (ds-tabla-remota.js): solo la tabla.
        if ($request->header('X-Partial') === 'usuarios') {
            return Response::html($this->view->render('user/_listado', $data));
        }

        return $this->view->response('user/index', $data);
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

        return $this->renderEdit($request, $csrf, $user, $usuario, []);
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
            return $this->renderEdit($request, $csrf, $user, $this->repo->findUserById($id), $input, $e->getMessage());
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
            $emailEnviado = $this->service->resetearPassword($id, $newPassword, $user->id, $request->clientIp());
            if ($emailEnviado) {
                $this->flash->success('Contraseña restablecida correctamente y enviada por email al usuario.');
            } else {
                $this->flash->error(
                    'La contraseña fue restablecida, pero no se pudo enviar el email. Entregala por un canal seguro o revisa la configuracion SMTP.',
                    'Email no enviado',
                );
            }
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo restablecer la contraseña');
        }
        return Response::redirect($this->securePath($request, "/usuarios/{$id}/editar"));
    }

    /**
     * @param array<string, mixed>|null $usuario
     * @param array<string, mixed> $input
     */
    private function renderEdit(
        Request $request,
        CsrfGuard $csrf,
        AuthenticatedUser $user,
        ?array $usuario,
        array $input,
        ?string $error = null,
    ): Response {
        $id = (string) ($usuario['id'] ?? '');
        $rolesAsignados = $id !== '' ? ($this->loadRolesByUserIds([$id])[$id] ?? []) : [];

        $data = [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'usuario' => $usuario,
            'roles' => $this->loadRolesAccesibles(),
            'rolActualId' => $rolesAsignados[0]['id'] ?? null,
            'rolActualCodigo' => $rolesAsignados[0]['codigo'] ?? null,
            'input' => $input,
        ];
        if ($error !== null) {
            $data['error'] = $error;
        }

        return $this->view->response('user/edit', $data);
    }

    /**
     * Roles asignables en la empresa: los propios del tenant y los de sistema
     * que la empresa no haya redefinido. Nunca el rol superadmin.
     *
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
