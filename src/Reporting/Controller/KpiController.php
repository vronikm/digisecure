<?php

declare(strict_types=1);

namespace DigiSecure\Reporting\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Reporting\Application\DashboardStatsService;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Controller de KPIs y Reportes.
 *
 * Rutas:
 *   GET /reportes/kpis     → Dashboard de KPIs con gráficos Chart.js
 *   GET /reportes/exportar → Exportación de datos (placeholder)
 */
final class KpiController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly DashboardStatsService $statsService,
    ) {
    }

    public function dashboard(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $kpi = $this->statsService->getKpiData();

        return $this->view->response('reporting/kpis', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'kpi' => $kpi,
        ]);
    }

    public function exportar(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        return $this->view->response('reporting/exportar', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
        ]);
    }
}
