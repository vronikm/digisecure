<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Controller;

use DigiSecure\Expediente\Application\RevisarDocumentoService;
use DigiSecure\Expediente\Application\SubirDocumentoService;
use DigiSecure\Expediente\Domain\Enum\EstadoExpediente;
use DigiSecure\Expediente\Infrastructure\ExpedienteRepository;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Controller del Expediente Documental del Guardia.
 *
 * Rutas:
 *   GET    /expedientes                       → listado
 *   GET    /expedientes/pendientes            → filtro rápido: solo incompletos
 *   GET    /guardias/{fichaId}/expediente      → dashboard expediente de una ficha
 *   GET    /expedientes/{id}/documento/{tipoId}→ formulario subir documento
 *   POST   /expedientes/{id}/documento/{tipoId}→ procesar subida
 *   GET    /expedientes/documento/{docId}      → ver detalle documento
 *   POST   /expedientes/documento/{docId}/revisar → aceptar/rechazar
 */
final class ExpedienteController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly ExpedienteRepository $expRepo,
        private readonly FichaRepository $fichaRepo,
        private readonly SubirDocumentoService $subirService,
        private readonly RevisarDocumentoService $revisarService,
    ) {
    }

    // ======================== LISTADO GENERAL ========================

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $page = max(1, (int) $request->query('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = array_filter([
            'estado' => $request->query('estado'),
            'busqueda' => $request->query('q'),
        ]);

        $expedientes = $this->expRepo->listExpedientes($limit, $offset, $filters);
        $total = $this->expRepo->countExpedientes($filters);
        $totalPages = (int) ceil($total / $limit);

        return $this->view->response('expediente/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'expedientes' => $expedientes,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
        ]);
    }

    public function pendientes(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $page = max(1, (int) $request->query('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = ['estado' => EstadoExpediente::INCOMPLETO->value];
        if ($request->query('q')) {
            $filters['busqueda'] = $request->query('q');
        }

        $expedientes = $this->expRepo->listExpedientes($limit, $offset, $filters);
        $total = $this->expRepo->countExpedientes($filters);
        $totalPages = (int) ceil($total / $limit);

        return $this->view->response('expediente/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'expedientes' => $expedientes,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
            'titulo' => 'Expedientes Pendientes',
        ]);
    }

    // ======================== DASHBOARD EXPEDIENTE ========================

    public function dashboard(Request $request, CsrfGuard $csrf, string $fichaId): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $ficha = $this->fichaRepo->findFichaById($fichaId);
        if ($ficha === null) {
            return $this->view->response('errors/404', [], 404);
        }

        // Buscar o crear expediente
        $expediente = $this->expRepo->findExpedienteByFichaId($fichaId);
        if ($expediente === null) {
            $expId = $this->expRepo->createExpediente($fichaId, $user->id);
            $expediente = $this->expRepo->findExpedienteById($expId);
        }

        // Cargar documentos por bloque
        $docsA = $this->expRepo->getDocumentos($expediente['id'], 'A');
        $docsB = $this->expRepo->getDocumentos($expediente['id'], 'B');

        // Cargar tipos para ver cuáles faltan
        $tiposA = $this->expRepo->getDocumentoTipos('A');
        $tiposB = $this->expRepo->getDocumentoTipos('B');

        // Mapear documentos existentes por tipo_id
        $docsPorTipo = [];
        foreach (array_merge($docsA, $docsB) as $d) {
            $docsPorTipo[$d['documento_tipo_id']] = $d;
        }

        // Documentos por vencer (30 días)
        $porVencer = $this->expRepo->getDocumentosPorVencer(30);
        // Filtrar solo los de este expediente
        $porVencer = array_filter($porVencer, fn($d) => $d['expediente_id'] === $expediente['id']);

        return $this->view->response('expediente/dashboard', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'ficha' => $ficha,
            'expediente' => $expediente,
            'tiposA' => $tiposA,
            'tiposB' => $tiposB,
            'docsPorTipo' => $docsPorTipo,
            'porVencer' => array_values($porVencer),
        ]);
    }

    // ======================== SUBIR DOCUMENTO ========================

    public function showUploadForm(Request $request, CsrfGuard $csrf, string $id, string $tipoId): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $expediente = $this->expRepo->findExpedienteById($id);
        if ($expediente === null) {
            return $this->view->response('errors/404', [], 404);
        }

        $tipo = $this->expRepo->findDocumentoTipoById($tipoId);
        if ($tipo === null) {
            return $this->view->response('errors/404', [], 404);
        }

        $docExistente = $this->expRepo->findDocumentoByTipo($id, $tipoId);
        $ficha = $this->fichaRepo->findFichaById($expediente['ficha_id']);

        return $this->view->response('expediente/upload', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'expediente' => $expediente,
            'ficha' => $ficha,
            'tipo' => $tipo,
            'docExistente' => $docExistente,
        ]);
    }

    public function upload(Request $request, CsrfGuard $csrf, string $id, string $tipoId): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $file = $request->file('archivo');
        $input = $request->post;

        try {
            $this->subirService->execute($id, $tipoId, $input, $file, $user->id, $request->clientIp());

            $expediente = $this->expRepo->findExpedienteById($id);
            return Response::redirect("/guardias/{$expediente['ficha_id']}/expediente");
        } catch (DomainException $e) {
            $expediente = $this->expRepo->findExpedienteById($id);
            $tipo = $this->expRepo->findDocumentoTipoById($tipoId);
            $ficha = $expediente ? $this->fichaRepo->findFichaById($expediente['ficha_id']) : null;

            return $this->view->response('expediente/upload', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'expediente' => $expediente,
                'ficha' => $ficha,
                'tipo' => $tipo,
                'docExistente' => $this->expRepo->findDocumentoByTipo($id, $tipoId),
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ======================== VER DOCUMENTO ========================

    public function showDocumento(Request $request, CsrfGuard $csrf, string $docId): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $doc = $this->expRepo->findDocumentoById($docId);
        if ($doc === null) {
            return $this->view->response('errors/404', [], 404);
        }

        $expediente = $this->expRepo->findExpedienteById($doc['expediente_id']);
        $ficha = $this->fichaRepo->findFichaById($expediente['ficha_id']);

        return $this->view->response('expediente/documento', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'doc' => $doc,
            'expediente' => $expediente,
            'ficha' => $ficha,
        ]);
    }

    // ======================== REVISAR DOCUMENTO ========================

    public function revisar(Request $request, CsrfGuard $csrf, string $docId): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $accion = (string) ($request->post['accion'] ?? '');
        $observacion = (string) ($request->post['observacion'] ?? '');

        try {
            $this->revisarService->execute($docId, $accion, $observacion, $user->id, $request->clientIp());

            $doc = $this->expRepo->findDocumentoById($docId);
            $expediente = $this->expRepo->findExpedienteById($doc['expediente_id']);
            return Response::redirect("/guardias/{$expediente['ficha_id']}/expediente");
        } catch (DomainException $e) {
            $doc = $this->expRepo->findDocumentoById($docId);
            if ($doc === null) {
                return $this->view->response('errors/404', [], 404);
            }
            $expediente = $this->expRepo->findExpedienteById($doc['expediente_id']);
            $ficha = $this->fichaRepo->findFichaById($expediente['ficha_id']);

            return $this->view->response('expediente/documento', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'doc' => $doc,
                'expediente' => $expediente,
                'ficha' => $ficha,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
