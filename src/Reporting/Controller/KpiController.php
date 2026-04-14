<?php

declare(strict_types=1);

namespace DigiSecure\Reporting\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Reporting\Application\DashboardStatsService;
use DigiSecure\Reporting\Application\ExportarReporteService;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Controller de KPIs y Reportes.
 *
 * Rutas:
 *   GET /reportes/kpis                         → Dashboard con gráficos Chart.js
 *   GET /reportes/exportar                     → Hub de exportaciones
 *   GET /reportes/exportar/fichas              → CSV de fichas
 *   GET /reportes/exportar/vencimientos        → CSV de vencimientos (?dias=30|60|90)
 *   GET /reportes/exportar/expedientes         → CSV resumen de expedientes
 */
final class KpiController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly DashboardStatsService $statsService,
        private readonly ExportarReporteService $exportService,
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

    public function exportarFichas(Request $request): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $out = $this->exportService->exportarFichas($user->id, $request->clientIp());
        return $this->csvResponse($out['content'], $out['filename']);
    }

    public function exportarVencimientos(Request $request): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $dias = max(1, (int) $request->query('dias', 30));
        $out = $this->exportService->exportarVencimientos($dias, $user->id, $request->clientIp());
        return $this->csvResponse($out['content'], $out['filename']);
    }

    public function exportarExpedientes(Request $request): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $out = $this->exportService->exportarExpedientes($user->id, $request->clientIp());
        return $this->csvResponse($out['content'], $out['filename']);
    }

    private function csvResponse(string $body, string $filename): Response
    {
        return new Response(
            body: $body,
            status: Response::STATUS_OK,
            headers: [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
