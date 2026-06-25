<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\Error\ErrorHandler;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Reporting\Application\DashboardStatsService;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;

/**
 * Dashboard principal con KPIs reales.
 */
final class DashboardController
{
    public function __construct(
        private readonly ViewRenderer $viewRenderer,
        private readonly DashboardStatsService $statsService,
        private readonly ErrorHandler $errorHandler,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $statsError = false;
        try {
            $stats = $this->statsService->getDashboardStats();
        } catch (\Throwable $e) {
            $this->errorHandler->report($e);
            $statsError = true;
            $stats = $this->emptyStats();
        }

        return $this->viewRenderer->response('dashboard/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'stats' => $stats,
            'statsError' => $statsError,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        return [
            'guardias_activos' => 0,
            'fichas_revision' => 0,
            'fichas_borrador' => 0,
            'docs_por_vencer' => 0,
            'docs_vencidos' => 0,
            'expedientes_completos' => 0,
            'expedientes_incompletos' => 0,
            'actividad_reciente' => [],
        ];
    }
}
