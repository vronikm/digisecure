<?php

declare(strict_types=1);

namespace DigiSecure\Security\Lopdp\Controller;

use DigiSecure\Ficha\Domain\ValueObject\Cedula;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Security\Lopdp\Application\ConsentManager;
use DigiSecure\Security\Lopdp\Application\RegistrarSolicitudArcopService;
use DigiSecure\Security\Lopdp\Application\ResponderSolicitudArcopService;
use DigiSecure\Security\Lopdp\Infrastructure\ConsentimientoRepository;
use DigiSecure\Security\Lopdp\Infrastructure\SolicitudArcopRepository;
use DigiSecure\User\Infrastructure\UserRepository;
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
        private readonly FichaRepository $fichaRepo,
        private readonly UserRepository $userRepo,
        private readonly ConsentManager $consentManager,
        private readonly RegistrarSolicitudArcopService $registrarService,
        private readonly ResponderSolicitudArcopService $responderService,
        private readonly FlashBag $flash,
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
            $this->flash->success('Solicitud ARCO-P registrada correctamente.');
            return Response::redirect($this->securePath($request, "/lopdp/solicitudes/{$id}"));
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

    /**
     * Endpoint AJAX para autocompletar datos del titular por cédula.
     * Solo opera sobre entidades del tenant actual.
     */
    public function buscarTitular(Request $request): Response
    {
        $tipo = strtolower(trim((string) ($request->post['titular_tipo'] ?? '')));
        $cedulaRaw = preg_replace('/\D+/', '', (string) ($request->post['cedula'] ?? '')) ?? '';

        if (!in_array($tipo, ['guardia', 'usuario'], true)) {
            return Response::json([
                'ok' => false,
                'found' => false,
                'message' => 'Selecciona tipo de titular guardia o usuario para buscar automáticamente.',
            ]);
        }

        if ($cedulaRaw === '') {
            return Response::json([
                'ok' => false,
                'found' => false,
                'message' => 'Ingresa una cédula para iniciar la búsqueda.',
            ]);
        }

        try {
            $cedula = Cedula::fromString($cedulaRaw);
        } catch (DomainException $e) {
            return Response::json([
                'ok' => false,
                'found' => false,
                'message' => $e->getMessage(),
            ]);
        }

        $hash = $cedula->hash();
        if ($tipo === 'guardia') {
            $guardia = $this->fichaRepo->findLatestByCedulaHash($hash);
            if ($guardia === null) {
                return Response::json([
                    'ok' => true,
                    'found' => false,
                    'message' => 'No existe una ficha de guardia para esa cédula en esta empresa.',
                ]);
            }

            return Response::json([
                'ok' => true,
                'found' => true,
                'titular' => [
                    'titular_id' => (string) $guardia['id'],
                    'titular_nombre' => trim(((string) ($guardia['apellidos'] ?? '')) . ' ' . ((string) ($guardia['nombres'] ?? ''))),
                    'titular_email' => (string) ($guardia['email'] ?? ''),
                    'titular_telefono' => (string) ($guardia['celular'] ?? ''),
                    'origen' => 'guardia',
                    'referencia' => (string) ($guardia['correlativo'] ?? ''),
                ],
            ]);
        }

        $usuario = $this->userRepo->findByCedulaHash($hash);
        if ($usuario === null) {
            return Response::json([
                'ok' => true,
                'found' => false,
                'message' => 'No existe un usuario para esa cédula en esta empresa.',
            ]);
        }

        return Response::json([
            'ok' => true,
            'found' => true,
            'titular' => [
                'titular_id' => (string) $usuario['id'],
                'titular_nombre' => trim(((string) ($usuario['apellidos'] ?? '')) . ' ' . ((string) ($usuario['nombres'] ?? ''))),
                'titular_email' => (string) ($usuario['email'] ?? ''),
                'titular_telefono' => (string) ($usuario['telefono'] ?? ''),
                'origen' => 'usuario',
                'referencia' => (string) ($usuario['email'] ?? ''),
            ],
        ]);
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
            $estado = (string) ($request->post['estado'] ?? 'RESPONDIDA');
            $msg = $estado === 'DENEGADA' ? 'Solicitud denegada y registrada en auditoria.' : 'Solicitud respondida correctamente.';
            $this->flash->success($msg);
            return Response::redirect($this->securePath($request, "/lopdp/solicitudes/{$id}"));
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
        $page = max(1, (int) $request->query('page', 1));
        $limit = 25;

        $estado = $this->normalizeConsentStatus((string)($request->query('estado', '')));
        $desde = $this->normalizeDate((string)($request->query('desde', '')));
        $hasta = $this->normalizeDate((string)($request->query('hasta', '')));

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $filters = array_filter([
            'estado' => $estado,
            'desde' => $desde,
            'hasta' => $hasta,
        ], static fn($v) => $v !== null && $v !== '');

        $total = $this->consentRepo->contarFiltrados($filters);
        $totalPages = max(1, (int) ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $limit;
        $consentimientos = $this->consentRepo->listarFiltrados($limit, $offset, $filters);
        $activos = $this->consentRepo->contarActivos();

        return $this->view->response('lopdp/consentimientos/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'consentimientos' => $consentimientos,
            'activos' => $activos,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
        ]);
    }

    public function verConsentimiento(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $consentimiento = $this->consentRepo->findById($id);
        if ($consentimiento === null) {
            return $this->view->response('errors/404', [], 404);
        }

        return $this->view->response('lopdp/consentimientos/show', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'consentimiento' => $consentimiento,
            'ipAceptacion' => $this->formatIp((string) ($consentimiento['ip_aceptacion'] ?? '')),
        ]);
    }

    public function revocarConsentimiento(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $motivo = trim((string) ($request->post['motivo'] ?? ''));
        $returnTo = (string) ($request->post['return_to'] ?? '/lopdp/consentimientos');

        try {
            $this->consentManager->revocar($id, $motivo, $user->id, $request->clientIp());
            $this->flash->success('Consentimiento revocado correctamente.');
            return Response::redirect($this->safeRedirect($request, $returnTo));
        } catch (DomainException $e) {
            $consentimiento = $this->consentRepo->findById($id);
            if ($consentimiento === null) {
                return $this->view->response('errors/404', [], 404);
            }

            return $this->view->response('lopdp/consentimientos/show', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'consentimiento' => $consentimiento,
                'ipAceptacion' => $this->formatIp((string) ($consentimiento['ip_aceptacion'] ?? '')),
                'error' => $e->getMessage(),
            ]);
        }
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

    private function safeRedirect(Request $request, string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '' || !str_starts_with($trimmed, '/')) {
            return '/lopdp/consentimientos';
        }

        return $this->securePath($request, $trimmed);
    }

    private function formatIp(string $packed): ?string
    {
        if ($packed === '') {
            return null;
        }

        $decoded = @inet_ntop($packed);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        return $decoded;
    }

    private function normalizeConsentStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['activo', 'revocado', 'rechazado'], true) ? $status : '';
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$y, $m, $d] = array_map(static fn(string $p): int => (int)$p, explode('-', $value));
        if (!checkdate($m, $d, $y)) {
            return null;
        }

        return $value;
    }
}
