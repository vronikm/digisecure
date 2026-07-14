<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Controller;

use DigiSecure\Expediente\Application\DescargarDocumentoService;
use DigiSecure\Expediente\Application\RevisarDocumentoService;
use DigiSecure\Expediente\Application\SubirDocumentoService;
use DigiSecure\Expediente\Domain\Enum\EstadoDocumento;
use DigiSecure\Expediente\Domain\Enum\EstadoExpediente;
use DigiSecure\Expediente\Infrastructure\ExpedienteRepository;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Kernel\Http\FlashBag;
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
        private readonly DescargarDocumentoService $descargarService,
        private readonly FlashBag $flash,
    ) {
    }

    // ======================== DESCARGAR DOCUMENTO ========================

    /**
     * GET /expedientes/documento/{docId}/descargar
     *
     * Sirve el archivo físico del documento al usuario autenticado,
     * siempre dentro del contexto del tenant. Audita cada descarga.
     */
    public function descargarDocumento(Request $request, string $docId): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $doc = $this->expRepo->findDocumentoById($docId);
        if ($doc === null) {
            return $this->view->response('errors/404', [], 404);
        }
        if (!$this->documentoTieneTipoActivo($doc)) {
            return $this->view->response('errors/404', [], 404);
        }
        if ($this->documentoEsBloqueB($doc) && !$this->puedeVerBloqueB($user)) {
            return $this->view->response('errors/404', [], 404);
        }

        $expediente = $this->expRepo->findExpedienteById((string) $doc['expediente_id']);
        $ficha = $expediente !== null ? $this->fichaRepo->findFichaById((string) $expediente['ficha_id']) : null;
        if (!$this->aspirantePuedeAccederFicha($user, $ficha)) {
            return $this->view->response('errors/404', [], 404);
        }

        try {
            $file = $this->descargarService->execute($docId, $user->id, $request->clientIp());
        } catch (DomainException $e) {
            return $this->view->response('errors/404', [
                'mensaje' => $e->getMessage(),
            ], 404);
        }

        // Si el cliente ya tiene la versión correcta (cache), no reenviar
        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $file['etag']) {
            return new Response(
                status: Response::STATUS_NOT_MODIFIED,
                body: '',
                headers: ['ETag' => $file['etag']],
            );
        }

        $contenido = @file_get_contents($file['path']);
        if ($contenido === false) {
            return $this->view->response('errors/500', [], 500);
        }

        $nombreDescarga = addslashes($file['nombre_descarga']);
        $inline = in_array($file['mime'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
        $disposition = ($inline ? 'inline' : 'attachment') . "; filename=\"{$nombreDescarga}\"";

        return new Response(
            status: Response::STATUS_OK,
            body: $contenido,
            headers: [
                'Content-Type' => $file['mime'],
                'Content-Length' => (string) $file['tamano'],
                'Content-Disposition' => $disposition,
                'ETag' => $file['etag'],
                'Cache-Control' => 'private, max-age=300, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
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
            'mostrarBloqueB' => $this->puedeVerBloqueB($user),
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
            'mostrarBloqueB' => $this->puedeVerBloqueB($user),
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
        if (!$this->aspirantePuedeAccederFicha($user, $ficha)) {
            return $this->view->response('errors/404', [], 404);
        }

        // Buscar o crear expediente
        $expediente = $this->expRepo->findExpedienteByFichaId($fichaId);
        if ($expediente === null) {
            $expId = $this->expRepo->createExpediente($fichaId, $user->id);
            $expediente = $this->expRepo->findExpedienteById($expId);
        }
        if ($expediente === null) {
            return $this->view->response('errors/404', [], 404);
        }

        $puedeVerBloqueB = $this->puedeVerBloqueB($user);

        // Cargar documentos por bloque
        $docsA = $this->expRepo->getDocumentos($expediente['id'], 'A');
        $docsB = $this->expRepo->getDocumentos($expediente['id'], 'B');

        // Cargar tipos para ver cuáles faltan
        $tiposA = $this->expRepo->getDocumentoTipos('A');
        $tiposB = $this->expRepo->getDocumentoTipos('B');
        $codigosActivos = [];
        foreach (array_merge($tiposA, $tiposB) as $tipo) {
            $codigo = (string) ($tipo['codigo'] ?? '');
            if ($codigo !== '') {
                $codigosActivos[$codigo] = true;
            }
        }

        $docsA = array_values(array_filter(
            $docsA,
            static fn(array $doc): bool => isset($codigosActivos[(string) ($doc['tipo_codigo'] ?? '')]),
        ));
        $docsB = array_values(array_filter(
            $docsB,
            static fn(array $doc): bool => isset($codigosActivos[(string) ($doc['tipo_codigo'] ?? '')]),
        ));
        if (!$puedeVerBloqueB) {
            $docsB = [];
            $tiposB = [];
        }

        $resumenA = $this->expRepo->resumenActivosByBloque((string) $expediente['id'], 'A');
        $resumenB = $this->expRepo->resumenActivosByBloque((string) $expediente['id'], 'B');
        $bloqueACompleto = $resumenA['total'] > 0 && $resumenA['aceptados'] >= $resumenA['total'];
        $bloqueBCompleto = $resumenB['total'] === 0 || $resumenB['aceptados'] >= $resumenB['total'];
        $expediente['bloque_a_total'] = $resumenA['total'];
        $expediente['bloque_a_aceptados'] = $resumenA['aceptados'];
        $expediente['bloque_b_total'] = $resumenB['total'];
        $expediente['bloque_b_aceptados'] = $resumenB['aceptados'];
        $expediente['bloque_b_desbloqueado'] = $bloqueACompleto ? 1 : 0;
        $expediente['estado'] = $bloqueACompleto && $bloqueBCompleto
            ? EstadoExpediente::COMPLETO->value
            : ($bloqueACompleto ? EstadoExpediente::BLOQUE_A_COMPLETO->value : EstadoExpediente::INCOMPLETO->value);

        // Mapear por codigo para no perder documentos si el tipo global
        // paso a override del tenant al activar/desactivar.
        $docsPorTipo = [];
        foreach (array_merge($docsA, $docsB) as $d) {
            $codigo = (string) ($d['tipo_codigo'] ?? '');
            if ($codigo !== '') {
                $docsPorTipo[$codigo] ??= [];
                $docsPorTipo[$codigo][] = $d;
            }
            $tipoKey = (string) $d['documento_tipo_id'];
            $docsPorTipo[$tipoKey] ??= [];
            $docsPorTipo[$tipoKey][] = $d;
        }
        foreach ($docsPorTipo as &$docsTipo) {
            usort($docsTipo, static fn(array $a, array $b): int => ((int) ($a['documento_indice'] ?? 1)) <=> ((int) ($b['documento_indice'] ?? 1)));
        }
        unset($docsTipo);

        // Documentos por vencer (30 días)
        $porVencer = $this->expRepo->getDocumentosPorVencer(30);
        // Filtrar solo los de este expediente
        $porVencer = array_filter(
            $porVencer,
            fn($d) => $d['expediente_id'] === $expediente['id']
                && isset($codigosActivos[(string) ($d['tipo_codigo'] ?? '')])
                && ($puedeVerBloqueB || (string) ($d['bloque'] ?? '') !== 'B'),
        );

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
            'mostrarBloqueB' => $puedeVerBloqueB,
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
        if ((string) ($tipo['bloque'] ?? '') === 'B' && !$this->puedeCargarBloqueB($user)) {
            return $this->view->response('errors/403', [], 403);
        }

        $docsExistentes = $this->expRepo->findDocumentosByTipo($id, $tipoId);
        $docExistente = $docsExistentes[0] ?? null;
        $ficha = $this->fichaRepo->findFichaById($expediente['ficha_id']);
        if ($ficha === null || !$this->aspirantePuedeAccederFicha($user, $ficha)) {
            return $this->view->response('errors/404', [], 404);
        }

        return $this->view->response('expediente/upload', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'expediente' => $expediente,
            'ficha' => $ficha,
            'tipo' => $tipo,
            'docExistente' => $docExistente,
            'docsExistentes' => $docsExistentes,
            'puedeValidarDocumentos' => $this->puedeValidarDocumentos($user),
        ]);
    }

    public function upload(Request $request, CsrfGuard $csrf, string $id, string $tipoId): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $input = $request->post;

        $expediente = $this->expRepo->findExpedienteById($id);
        $ficha = $expediente !== null ? $this->fichaRepo->findFichaById((string) $expediente['ficha_id']) : null;
        if ($expediente === null || $ficha === null || !$this->aspirantePuedeAccederFicha($user, $ficha)) {
            return $this->view->response('errors/404', [], 404);
        }
        $tipo = $this->expRepo->findDocumentoTipoById($tipoId);
        if ($tipo === null) {
            return $this->view->response('errors/404', [], 404);
        }
        if ((string) ($tipo['bloque'] ?? '') === 'B' && !$this->puedeCargarBloqueB($user)) {
            return $this->view->response('errors/403', [], 403);
        }

        try {
            $files = $this->uploadedDocumentFiles($request, max(1, (int) ($tipo['numero_documentos'] ?? 1)));
            if ($files === []) {
                throw new DomainException('Selecciona al menos un archivo para cargar.');
            }

            $this->ensureDocumentSlotsAreReplaceable($id, $tipoId, $files);

            foreach ($files as $indice => $file) {
                $this->subirService->execute(
                    $id,
                    $tipoId,
                    $input,
                    $file,
                    $user->id,
                    $request->clientIp(),
                    $this->puedeValidarDocumentos($user),
                    $this->puedeCargarBloqueB($user),
                    (int) $indice,
                );
            }

            $mensaje = (string) ($tipo['bloque'] ?? '') === 'B'
                ? sprintf('%d documento(s) de Bloque B cargado(s) y aceptado(s) correctamente.', count($files))
                : sprintf('%d documento(s) subido(s) correctamente.', count($files));
            $this->flash->success($mensaje);
            return Response::redirect(
                $this->securePath($request, "/guardias/{$expediente['ficha_id']}/expediente")
            );
        } catch (DomainException $e) {
            return $this->view->response('expediente/upload', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
                'expediente' => $expediente,
                'ficha' => $ficha,
                'tipo' => $tipo,
                'docExistente' => $this->expRepo->findDocumentoByTipo($id, $tipoId),
                'docsExistentes' => $this->expRepo->findDocumentosByTipo($id, $tipoId),
                'puedeValidarDocumentos' => $this->puedeValidarDocumentos($user),
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
        if (!$this->documentoTieneTipoActivo($doc)) {
            return $this->view->response('errors/404', [], 404);
        }
        if ($this->documentoEsBloqueB($doc) && !$this->puedeVerBloqueB($user)) {
            return $this->view->response('errors/404', [], 404);
        }

        $expediente = $this->expRepo->findExpedienteById((string) $doc['expediente_id']);
        $ficha = $expediente !== null ? $this->fichaRepo->findFichaById((string) $expediente['ficha_id']) : null;
        if ($expediente === null || $ficha === null || !$this->aspirantePuedeAccederFicha($user, $ficha)) {
            return $this->view->response('errors/404', [], 404);
        }

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

        if ($user->esAspiranteGuardia()) {
            return $this->view->response('errors/403', [], 403);
        }

        $docActual = $this->expRepo->findDocumentoById($docId);
        if ($docActual === null || !$this->documentoTieneTipoActivo($docActual)) {
            return $this->view->response('errors/404', [], 404);
        }
        if ($this->documentoEsBloqueB($docActual) && !$this->puedeVerBloqueB($user)) {
            return $this->view->response('errors/404', [], 404);
        }

        $accion = (string) ($request->post['accion'] ?? '');
        $observacion = (string) ($request->post['observacion'] ?? '');

        try {
            $this->revisarService->execute($docId, $accion, $observacion, $user->id, $request->clientIp());

            $doc = $this->expRepo->findDocumentoById($docId);
            $expediente = $this->expRepo->findExpedienteById($doc['expediente_id']);
            $msg = $accion === 'aceptar'
                ? 'Documento aceptado.'
                : ($accion === 'rechazar' ? 'Documento rechazado.' : 'Revision registrada.');
            $this->flash->success($msg);
            return Response::redirect(
                $this->securePath($request, "/guardias/{$expediente['ficha_id']}/expediente")
            );
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

    private function puedeValidarDocumentos(AuthenticatedUser $user): bool
    {
        return $user->tienePermiso('expedientes.validar') || $user->tienePermiso('expedientes.revisar');
    }

    private function puedeCargarBloqueB(AuthenticatedUser $user): bool
    {
        if ($user->esAspiranteGuardia()) {
            return false;
        }

        if ($this->esAdminOperativo($user)) {
            return true;
        }

        return $user->tienePermiso('expedientes.cargar') || $user->tienePermiso('expedientes.subir');
    }

    private function puedeVerBloqueB(AuthenticatedUser $user): bool
    {
        if ($user->esAspiranteGuardia()) {
            return false;
        }

        if ($this->esAdminOperativo($user)) {
            return true;
        }

        return $this->puedeCargarBloqueB($user) || $this->puedeValidarDocumentos($user);
    }

    private function esAdminOperativo(AuthenticatedUser $user): bool
    {
        return $user->esSuperAdmin() || $user->tieneRol('admin_tenant');
    }

    /**
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private function uploadedDocumentFiles(Request $request, int $maxDocumentos): array
    {
        $files = [];

        $legacy = $request->file('archivo');
        if ($legacy !== null && (int) ($legacy['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $files[1] = $legacy;
        }

        for ($i = 1; $i <= $maxDocumentos; $i++) {
            $file = $request->file('archivo_' . $i);
            if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[$i] = $file;
        }

        ksort($files);
        return $files;
    }

    /**
     * @param array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     */
    private function ensureDocumentSlotsAreReplaceable(string $expedienteId, string $tipoId, array $files): void
    {
        $docsPorIndice = [];
        foreach ($this->expRepo->findDocumentosByTipo($expedienteId, $tipoId) as $doc) {
            $indice = max(1, (int) ($doc['documento_indice'] ?? 1));
            $docsPorIndice[$indice] ??= $doc;
        }

        foreach (array_keys($files) as $indice) {
            $doc = $docsPorIndice[(int) $indice] ?? null;
            if ($doc !== null && (string) ($doc['estado'] ?? '') === EstadoDocumento::ACEPTADO->value) {
                throw new DomainException('El documento aceptado no puede reemplazarse. Solo puedes visualizarlo.');
            }
        }
    }

    /**
     * @param array<string, mixed>|null $ficha
     */
    private function aspirantePuedeAccederFicha(AuthenticatedUser $user, ?array $ficha): bool
    {
        if (!$user->esAspiranteGuardia()) {
            return true;
        }

        return $ficha !== null && (string) ($ficha['created_by'] ?? '') === $user->id;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function documentoTieneTipoActivo(array $doc): bool
    {
        $codigo = (string) ($doc['tipo_codigo'] ?? '');
        return $codigo !== '' && $this->expRepo->findDocumentoTipoByCodigo($codigo) !== null;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function documentoEsBloqueB(array $doc): bool
    {
        return (string) ($doc['bloque'] ?? '') === 'B';
    }
}
