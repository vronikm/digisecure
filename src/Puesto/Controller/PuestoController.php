<?php

declare(strict_types=1);

namespace DigiSecure\Puesto\Controller;

use DigiSecure\Asignacion\Infrastructure\AsignacionRepository;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Puesto\Application\GestionarPuestosService;
use DigiSecure\Puesto\Domain\Enum\ModalidadPuesto;
use DigiSecure\Puesto\Domain\Enum\TipoPuesto;
use DigiSecure\Puesto\Infrastructure\PuestoRepository;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

/**
 * CRUD de puestos / sitios donde el tenant presta servicios de seguridad.
 *
 * Rutas:
 *   GET  /puestos              listado paginado
 *   GET  /puestos/crear        formulario de alta
 *   POST /puestos/crear        procesar alta
 *   GET  /puestos/{id}         detalle
 *   GET  /puestos/{id}/editar  formulario edicion
 *   POST /puestos/{id}/editar  procesar edicion
 *   POST /puestos/{id}/estado  cambiar estado
 */
final class PuestoController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly PuestoRepository $repo,
        private readonly GestionarPuestosService $service,
        private readonly FlashBag $flash,
        private readonly AsignacionRepository $asignacionRepo,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $page = max(1, (int) $request->query('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'q' => $request->query('q'),
            'estado' => $request->query('estado'),
            'tipo' => $request->query('tipo'),
            'cliente_id' => $request->query('cliente_id'),
        ];

        $puestos = $this->repo->listar($filters, $limit, $offset);
        $total = $this->repo->contar($filters);
        $totalPages = max(1, (int) ceil($total / $limit));
        $kpis = $this->repo->kpis();
        $clientes = $this->repo->clientesActivosParaSelect();

        return $this->view->response('puestos/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'puestos' => $puestos,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'kpis' => $kpis,
            'clientes' => $clientes,
            'tiposPuesto' => TipoPuesto::cases(),
        ]);
    }

    public function create(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $clientes = $this->repo->clientesActivosParaSelect();
        if ($clientes === []) {
            $this->flash->warning('Debes registrar al menos un cliente activo antes de crear un puesto.');
            return Response::redirect('/clientes/crear');
        }

        $clienteIdPreseleccionado = (string) $request->query('cliente_id', '');

        return $this->view->response('puestos/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'clientes' => $clientes,
            'tiposPuesto' => TipoPuesto::cases(),
            'modalidades' => ModalidadPuesto::cases(),
            'input' => ['cliente_id' => $clienteIdPreseleccionado],
        ]);
    }

    public function store(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;

        try {
            $id = $this->service->crear($input, $user->id, $request->clientIp());
            $this->flash->success('Puesto registrado correctamente.');
            return Response::redirect("/puestos/{$id}");
        } catch (DomainException $e) {
            return $this->view->response('puestos/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/puestos/crear',
                'clientes' => $this->repo->clientesActivosParaSelect(),
                'tiposPuesto' => TipoPuesto::cases(),
                'modalidades' => ModalidadPuesto::cases(),
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function show(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $puesto = $this->repo->findById($id);
        if ($puesto === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        $asignacionesActivas = $this->asignacionRepo->listarActivasPorPuesto($id);

        return $this->view->response('puestos/show', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'puesto' => $puesto,
            'asignacionesActivas' => $asignacionesActivas,
        ]);
    }

    public function edit(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $puesto = $this->repo->findById($id);
        if ($puesto === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        return $this->view->response('puestos/edit', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'puesto' => $puesto,
            'clientes' => $this->repo->clientesActivosParaSelect(),
            'tiposPuesto' => TipoPuesto::cases(),
            'modalidades' => ModalidadPuesto::cases(),
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
            $this->flash->success('Puesto actualizado correctamente.');
            return Response::redirect("/puestos/{$id}");
        } catch (DomainException $e) {
            $puesto = $this->repo->findById($id) ?? [];
            return $this->view->response('puestos/edit', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'puesto' => array_merge($puesto, $input),
                'clientes' => $this->repo->clientesActivosParaSelect(),
                'tiposPuesto' => TipoPuesto::cases(),
                'modalidades' => ModalidadPuesto::cases(),
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
                'activo'    => 'Puesto reactivado.',
                'inactivo'  => 'Puesto marcado como inactivo.',
                'archivado' => 'Puesto archivado.',
                default     => 'Estado actualizado.',
            };
            $this->flash->success($msg);
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo cambiar el estado del puesto');
        }
        return Response::redirect("/puestos/{$id}");
    }
}
