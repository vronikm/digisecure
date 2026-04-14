<?php

declare(strict_types=1);

namespace DigiSecure\Security\Lopdp\Controller;

use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Security\Lopdp\Application\RegistrarSolicitudArcopService;
use DigiSecure\Security\Lopdp\Application\ResponderSolicitudArcopService;
use DigiSecure\Security\Lopdp\Infrastructure\ConsentimientoRepository;
use DigiSecure\Security\Lopdp\Infrastructure\SolicitudArcopRepository;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Controller del módulo LOPDP (Ley Orgánica de Protección de Datos Personales).
 *
 * Cubre:
 *   - Dashboard con KPIs (urgencia ARCO-P, consentimientos activos).
 *   - CRUD de solicitudes ARCO-P (alta, listado, detalle, responder/denegar).
 *   - Visor de consentimientos LOPDP del tenant.
 */
final class LopdpController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly SolicitudArcopRepository $solicitudRepo,
        private readonly ConsentimientoRepository $consentRepo,
        private readonly RegistrarSolicitudArcopService $registrarService,
        private readonly ResponderSolicitudArcopService $responderService,
    ) {
    }

    // ======================== DASHBOARD ========================

    public function dashboard(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $urgencia = $this->solicitudRepo->estadisticasUrgencia();
        $recientesSolicitudes = $this->solicitudRepo->listar(5, 0);
        $consentActivos = $this->consentRepo->contarActivos();
        $recientesConsentimientos = $this->consentRepo->listarRecientes(5);

        return $this->view->response('lopdp/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'urgencia' => $urgencia,
            'recientesSolicitudes' => $recientesSolicitudes,
            'consentActivos' => $consentActivos,
            'recientesConsentimientos' => $recientesConsentimientos,
        ]);
    }

    // ======================== SOLICITUDES ARCO-P ========================

    public function listarSolicitudes(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $page = max(1, (int)$request->query('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = array_filter([
            'estado' => $request->query('estado'),
            'tipo' => $request->query('tipo'),
            'busqueda' => $request->query('q'),
        ], static fn($v) => $v !== null && $v !== '');

        $solicitudes = $this->solicitudRepo->listar($limit, $offset, $filters);
        $total = $this->solicitudRepo->contar($filters);
        $totalPages = max(1, (int)ceil($total / $limit));

        return $this->view->response('lopdp/solicitudes/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'solicitudes' => $solicitudes,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
        ]);
    }

    public function crearSolicitudForm(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        return $this->view->response('lopdp/solicitudes/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'input' => ['fecha_recepcion' => date('Y-m-d')],
        ]);
    }

    public function crearSolicitud(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;

        try {
            $id = $this->registrarService->execute($input, $user->id, $request->clientIp());
            return Response::redirect("/lopdp/solicitudes/{$id}");
        } catch (DomainException $e) {
            return $this->view->response('lopdp/solicitudes/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/lopdp/solicitudes/crear',
                'input' => $input,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function verSolicitud(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $solicitud = $this->solicitudRepo->findById($id);
        if ($solicitud === null) {
            return $this->view->response('errors/404', [], 404);
        }

        return $this->view->response('lopdp/solicitudes/show', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'solicitud' => $solicitud,
        ]);
    }

    public function responderSolicitud(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $this->responderService->execute($id, $request->post, $user->id, $request->clientIp());
            return Response::redirect("/lopdp/solicitudes/{$id}");
        } catch (DomainException $e) {
            $solicitud = $this->solicitudRepo->findById($id);
            return $this->view->response('lopdp/solicitudes/show', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'solicitud' => $solicitud,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ======================== CONSENTIMIENTOS ========================

    public function listarConsentimientos(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $consentimientos = $this->consentRepo->listarRecientes(100);
        $activos = $this->consentRepo->contarActivos();

        return $this->view->response('lopdp/consentimientos/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'consentimientos' => $consentimientos,
            'activos' => $activos,
        ]);
    }
}
