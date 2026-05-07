<?php

declare(strict_types=1);

namespace DigiSecure\Notification\Controller;

use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Notification\Domain\Enum\EstadoNotificacion;
use DigiSecure\Notification\Infrastructure\NotificacionRepository;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Visor de notificaciones: listado filtrable + detalle + reintentar.
 *
 * Rutas:
 *   GET  /notificaciones           → listado paginado
 *   GET  /notificaciones/{id}      → detalle
 *   POST /notificaciones/{id}/retry → reintentar notificación fallida
 */
final class NotificacionController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly NotificacionRepository $repo,
        private readonly FlashBag $flash,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $page = max(1, (int) $request->query('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $filters = array_filter([
            'estado' => $request->query('estado'),
            'canal' => $request->query('canal'),
            'evento' => $request->query('evento'),
        ], static fn($v) => $v !== null && $v !== '');

        $notificaciones = $this->repo->listByTenant($filters, $limit, $offset);
        $total = $this->repo->countByTenant($filters);
        $totalPages = max(1, (int) ceil($total / $limit));

        // KPIs rápidos
        $kpis = [
            'pendientes' => $this->repo->countByTenant(['estado' => EstadoNotificacion::PENDIENTE->value]),
            'enviadas' => $this->repo->countByTenant(['estado' => EstadoNotificacion::ENVIADA->value]),
            'fallidas' => $this->repo->countByTenant(['estado' => EstadoNotificacion::FALLIDA->value]),
            'total' => $this->repo->countByTenant(),
        ];

        return $this->view->response('notificaciones/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'notificaciones' => $notificaciones,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'kpis' => $kpis,
        ]);
    }

    public function show(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $id = $request->attribute('route.id');

        $notificacion = $this->repo->findByIdWithLog((string) $id);
        if ($notificacion === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        return $this->view->response('notificaciones/show', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'notificacion' => $notificacion['notificacion'],
            'intentos' => $notificacion['intentos'],
        ]);
    }

    public function retry(Request $request, CsrfGuard $csrf): Response
    {
        $id = $request->attribute('route.id');

        $this->repo->resetForRetry((string) $id);
        $this->flash->success('Notificacion encolada para reintento.');

        return Response::redirect("/notificaciones/{$id}");
    }
}
