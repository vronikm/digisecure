<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Controller;

use DigiSecure\Aspirante\Application\GestionarAspirantesService;
use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Panel de reclutamiento: revision y aprobacion de aspirantes (Fase 4).
 *
 * Rutas (permiso aspirantes.gestionar):
 *   GET  /reclutamiento              listado + filtros + KPIs
 *   GET  /reclutamiento/{id}         detalle del aspirante
 *   POST /reclutamiento/{id}/aprobar crea usuario y aprueba
 *   POST /reclutamiento/{id}/rechazar marca descartado
 */
final class AspiranteAdminController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AspiranteRepository $repo,
        private readonly GestionarAspirantesService $service,
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
            'q'          => $request->query('q'),
            'estado'     => $request->query('estado'),
            'verificado' => $request->query('verificado'),
        ];

        $aspirantes = $this->repo->listarAdmin($filters, $limit, $offset);
        $total = $this->repo->contarAdmin($filters);

        return $this->view->response('reclutamiento/index', [
            'user'        => $user,
            'csrfToken'   => $csrf->token(),
            'currentPath' => $request->path,
            'aspirantes'  => $aspirantes,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => max(1, (int) ceil($total / $limit)),
            'filters'     => $filters,
            'kpis'        => $this->repo->kpisAdmin(),
        ]);
    }

    public function show(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $aspirante = $this->repo->findDetalle($id);
        if ($aspirante === null) {
            return $this->view->response('errors/404', [
                'user'        => $user,
                'csrfToken'   => $csrf->token(),
                'currentPath' => $request->path,
            ], Response::STATUS_NOT_FOUND);
        }

        return $this->view->response('reclutamiento/show', [
            'user'        => $user,
            'csrfToken'   => $csrf->token(),
            'currentPath' => $request->path,
            'aspirante'   => $aspirante,
        ]);
    }

    public function aprobar(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $this->service->aprobar($id, $user->tenantId, $user->id, $request->clientIp());
            $this->flash->success('Aspirante aprobado: se creó su cuenta de usuario activa.');
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo aprobar al aspirante');
        }

        return Response::redirect($this->securePath($request, "/reclutamiento/{$id}"));
    }

    public function rechazar(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $motivo = (string) $request->post('motivo', '');

        try {
            $this->service->rechazar($id, $motivo, $user->id, $request->clientIp());
            $this->flash->success('Aspirante rechazado.');
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo rechazar al aspirante');
        }

        return Response::redirect($this->securePath($request, "/reclutamiento/{$id}"));
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
