<?php

declare(strict_types=1);

namespace DigiSecure\Cliente\Controller;

use DigiSecure\Cliente\Application\GestionarClientesService;
use DigiSecure\Cliente\Infrastructure\ClienteRepository;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Puesto\Infrastructure\PuestoRepository;
use DigiSecure\Kernel\Http\Paginacion;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

/**
 * CRUD de clientes (empresas a las que el tenant presta servicios).
 *
 * Rutas:
 *   GET  /clientes              listado paginado con filtros
 *   GET  /clientes/crear        formulario de alta
 *   POST /clientes/crear        procesar alta
 *   GET  /clientes/{id}         detalle
 *   GET  /clientes/{id}/editar  formulario de edicion
 *   POST /clientes/{id}/editar  procesar edicion
 *   POST /clientes/{id}/estado  cambiar estado (activo/inactivo/archivado)
 */
final class ClienteController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly ClienteRepository $repo,
        private readonly GestionarClientesService $service,
        private readonly FlashBag $flash,
        private readonly PuestoRepository $puestoRepo,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'estado' => $request->query('estado'),
        ];

        $paginacion = Paginacion::desdeRequest($request)->conTotal($this->repo->contar($filters));
        $data = [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'clientes' => $this->repo->listar($filters, $paginacion->limite(), $paginacion->offset()),
            'paginacion' => $paginacion,
            'filters' => $filters,
        ];

        // Busqueda, filtros y paginacion en vivo (ds-tabla-remota.js): solo la tabla.
        if ($request->header('X-Partial') === 'clientes') {
            return Response::html($this->view->render('clientes/_listado', $data));
        }

        $data['kpis'] = $this->repo->kpis();
        return $this->view->response('clientes/index', $data);
    }

    public function create(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        return $this->view->response('clientes/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'input' => [],
        ]);
    }

    public function store(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;

        try {
            $id = $this->service->crear($input, $user->id, $request->clientIp());
            $this->flash->success('Cliente registrado correctamente.');
            return Response::redirect($this->securePath($request, "/clientes/{$id}"));
        } catch (DomainException $e) {
            return $this->view->response('clientes/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/clientes/crear',
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function show(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $cliente = $this->repo->findById($id);
        if ($cliente === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        $puestos = $this->puestoRepo->listarPorCliente($id);

        return $this->view->response('clientes/show', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'cliente' => $cliente,
            'puestos' => $puestos,
        ]);
    }

    public function edit(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $cliente = $this->repo->findById($id);
        if ($cliente === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        return $this->view->response('clientes/edit', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'cliente' => $cliente,
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
            $this->flash->success('Cliente actualizado correctamente.');
            return Response::redirect($this->securePath($request, "/clientes/{$id}"));
        } catch (DomainException $e) {
            $cliente = $this->repo->findById($id) ?? [];
            return $this->view->response('clientes/edit', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'cliente' => array_merge($cliente, $input),
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
            $msg = match ($nuevoEstado) {
                'activo'     => 'Cliente reactivado.',
                'inactivo'   => 'Cliente marcado como inactivo.',
                'archivado'  => 'Cliente archivado.',
                default      => 'Estado actualizado.',
            };
            $this->flash->success($msg);
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo cambiar el estado del cliente');
        }
        return Response::redirect($this->securePath($request, "/clientes/{$id}"));
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
