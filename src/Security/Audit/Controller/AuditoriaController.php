<?php

declare(strict_types=1);

namespace DigiSecure\Security\Audit\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Audit\AuditoriaRepository;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Visor de auditoría: read-only listado filtrable.
 *
 * GET /auditoria → tabla paginada con filtros (evento, nivel, fechas).
 */
final class AuditoriaController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AuditoriaRepository $repo,
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
            'evento' => $request->query('evento'),
            'nivel' => $request->query('nivel'),
            'usuario_id' => $request->query('usuario_id'),
            'entidad_tipo' => $request->query('entidad_tipo'),
            'desde' => $request->query('desde'),
            'hasta' => $request->query('hasta'),
        ], static fn($v) => $v !== null && $v !== '');

        $eventos = $this->repo->listByTenant($filters, $limit, $offset);
        $total = $this->repo->countByTenant($filters);
        $totalPages = max(1, (int) ceil($total / $limit));

        return $this->view->response('auditoria/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'eventos' => $eventos,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'eventosDisponibles' => $this->repo->distinctEventos(),
        ]);
    }
}
