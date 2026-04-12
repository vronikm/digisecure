<?php

declare(strict_types=1);

namespace DigiSecure\Security\Auth\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
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
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $stats = $this->statsService->getDashboardStats();

        return $this->viewRenderer->response('dashboard/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'stats' => $stats,
        ]);
    }
}
