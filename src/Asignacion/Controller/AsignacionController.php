<?php

declare(strict_types=1);

namespace DigiSecure\Asignacion\Controller;

use DigiSecure\Asignacion\Application\GestionarAsignacionesService;
use DigiSecure\Asignacion\Infrastructure\AsignacionRepository;
use DigiSecure\Cliente\Infrastructure\ClienteRepository;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

/**
 * CRUD de asignaciones guardia <-> puesto.
 *
 * Rutas:
 *   GET  /asignaciones              listado paginado con filtros
 *   GET  /asignaciones/crear        formulario alta (acepta ?ficha_id=  o ?puesto_id= preseleccion)
 *   POST /asignaciones/crear        procesar alta
 *   GET  /asignaciones/{id}         detalle
 *   GET  /asignaciones/{id}/editar  editar fechas/notas (solo activas)
 *   POST /asignaciones/{id}/editar  procesar edicion
 *   POST /asignaciones/{id}/cerrar  finalizar o cancelar (con motivo)
 */
final class AsignacionController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AsignacionRepository $repo,
        private readonly GestionarAsignacionesService $service,
        private readonly ClienteRepository $clienteRepo,
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

        $filters = [
            'q' => $request->query('q'),
            'estado' => $request->query('estado'),
            'cliente_id' => $request->query('cliente_id'),
            'puesto_id' => $request->query('puesto_id'),
            'ficha_id' => $request->query('ficha_id'),
        ];

        $asignaciones = $this->repo->listar($filters, $limit, $offset);
        $total = $this->repo->contar($filters);
        $totalPages = max(1, (int) ceil($total / $limit));
        $kpis = $this->repo->kpis();
        $clientes = $this->clienteRepo->listar(['estado' => 'activo'], 500, 0);

        // Mismas condiciones que create(): se avisan aquí para no ofrecer un
        // botón que solo redirige con un mensaje.
        $bloqueoCreacion = null;
        if ($user->tienePermiso('asignaciones.crear')) {
            if ($this->repo->fichasCompletasParaSelect() === []) {
                $bloqueoCreacion = [
                    'mensaje' => 'Para asignar se necesita al menos un guardia con ficha COMPLETA.',
                    'url' => '/guardias',
                    'accion' => 'Ir a Guardias',
                ];
            } elseif ($this->repo->puestosActivosParaSelect() === []) {
                $bloqueoCreacion = [
                    'mensaje' => 'Para asignar se necesita al menos un puesto activo.',
                    'url' => '/puestos/crear',
                    'accion' => 'Registrar un puesto',
                ];
            }
        }

        return $this->view->response('asignaciones/index', [
            'bloqueoCreacion' => $bloqueoCreacion,
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'asignaciones' => $asignaciones,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'kpis' => $kpis,
            'clientes' => $clientes,
        ]);
    }

    public function create(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $fichas = $this->repo->fichasCompletasParaSelect();
        $puestos = $this->repo->puestosActivosParaSelect();

        if ($fichas === []) {
            $this->flash->warning('No hay guardias con ficha COMPLETA. Completa primero al menos un expediente.');
            return Response::redirect('/guardias');
        }
        if ($puestos === []) {
            $this->flash->warning('No hay puestos activos. Registra al menos uno antes de asignar guardias.');
            return Response::redirect('/puestos/crear');
        }

        $input = [
            'ficha_id' => (string) $request->query('ficha_id', ''),
            'puesto_id' => (string) $request->query('puesto_id', ''),
            'fecha_inicio' => date('Y-m-d'),
        ];

        return $this->view->response('asignaciones/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'fichas' => $fichas,
            'puestos' => $puestos,
            'input' => $input,
        ]);
    }

    public function store(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;

        try {
            $id = $this->service->crear($input, $user->id, $request->clientIp());
            $this->flash->success('Asignacion creada correctamente.');
            return Response::redirect($this->securePath($request, "/asignaciones/{$id}"));
        } catch (DomainException $e) {
            return $this->view->response('asignaciones/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/asignaciones/crear',
                'fichas' => $this->repo->fichasCompletasParaSelect(),
                'puestos' => $this->repo->puestosActivosParaSelect(),
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function show(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $asignacion = $this->repo->findById($id);
        if ($asignacion === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        return $this->view->response('asignaciones/show', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'asignacion' => $asignacion,
        ]);
    }

    public function edit(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $asignacion = $this->repo->findById($id);
        if ($asignacion === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }
        if ($asignacion['estado'] !== 'activa') {
            $this->flash->error('Solo se pueden editar asignaciones activas.');
            return Response::redirect($this->securePath($request, "/asignaciones/{$id}"));
        }

        return $this->view->response('asignaciones/edit', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'asignacion' => $asignacion,
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
            $this->flash->success('Asignacion actualizada.');
            return Response::redirect($this->securePath($request, "/asignaciones/{$id}"));
        } catch (DomainException $e) {
            $asignacion = $this->repo->findById($id) ?? [];
            return $this->view->response('asignaciones/edit', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'asignacion' => array_merge($asignacion, $input),
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function cerrar(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $accion = (string) $request->post('accion', 'finalizar');
        $motivo = (string) $request->post('motivo_fin', '');

        try {
            $this->service->cerrar($id, $accion, $motivo, $user->id, $request->clientIp());
            $this->flash->success($accion === 'finalizar' ? 'Asignacion finalizada.' : 'Asignacion cancelada.');
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo cerrar la asignacion');
        }
        return Response::redirect($this->securePath($request, "/asignaciones/{$id}"));
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
