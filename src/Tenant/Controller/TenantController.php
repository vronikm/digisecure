<?php

declare(strict_types=1);

namespace DigiSecure\Tenant\Controller;

use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Tenant\Application\ActualizarTenantService;
use DigiSecure\Tenant\Infrastructure\TenantRepository;

/**
 * Controller de gestión del tenant propio.
 *
 * Rutas:
 *   GET  /empresa        → ver datos de la empresa
 *   POST /empresa/editar → actualizar datos editables
 */
final class TenantController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TenantRepository $tenantRepo,
        private readonly ActualizarTenantService $actualizarService,
        private readonly FlashBag $flash,
    ) {
    }

    public function show(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $tenant = $this->tenantRepo->findCurrent();

        return $this->view->response('tenant/show', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'tenant' => $tenant,
        ]);
    }

    public function edit(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $tenant = $this->tenantRepo->findCurrent();
        $canEditSensitive = $this->canEditSensitive($user);

        return $this->view->response('tenant/edit', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'tenant' => $tenant,
            'canEditSensitive' => $canEditSensitive,
        ]);
    }

    public function update(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $canEditSensitive = $this->canEditSensitive($user);

        try {
            $this->actualizarService->execute($request->post, $user->id, $request->clientIp(), $canEditSensitive);
            $this->flash->success('Datos de la empresa actualizados correctamente.');
            return Response::redirect('/empresa/editar');
        } catch (DomainException $e) {
            $tenant = $this->tenantRepo->findCurrent();
            return $this->view->response('tenant/edit', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/empresa/editar',
                'tenant' => array_merge($tenant ?? [], $request->post),
                'error' => $e->getMessage(),
                'canEditSensitive' => $canEditSensitive,
            ]);
        }
    }

    private function canEditSensitive(AuthenticatedUser $user): bool
    {
        return $user->tienePermiso('superadmin.panel')
            || $user->tienePermiso('superadmin.tenants.gestionar');
    }
}
