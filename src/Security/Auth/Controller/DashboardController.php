<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Dashboard principal.
 *
 * En Fase 0.9 muestra KPIs placeholder.
 * En Fase 4 se conectarán queries reales vía CQRS (Reporting module).
 */
final class DashboardController
{
    public function __construct(
        private readonly ViewRenderer $viewRenderer,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        // Stats placeholder — en Fase 4 vendrán del módulo Reporting (CQRS)
        $stats = [
            'guardias_activos' => 0,
            'docs_por_vencer' => 0,
            'docs_vencidos' => 0,
            'expedientes_completos' => 0,
            'actividad_reciente' => [],
        ];

        return $this->viewRenderer->response('dashboard/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'stats' => $stats,
        ]);
    }
}
