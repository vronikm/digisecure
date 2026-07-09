<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Controller;

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\TipoFicha;
use DigiSecure\Ficha\Application\ActualizarFichaService;
use DigiSecure\Ficha\Application\CompletarFichaService;
use DigiSecure\Ficha\Application\CrearFichaService;
use DigiSecure\Ficha\Application\EliminarFichaService;
use DigiSecure\Ficha\Application\EnviarRevisionFichaService;
use DigiSecure\Ficha\Application\GenerarDeclaracionLealtadService;
use DigiSecure\Ficha\Application\GenerarFormatoHuellasService;
use DigiSecure\Ficha\Application\RecuperarFichaService;
use DigiSecure\Ficha\Infrastructure\CroquisStorage;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Container\Container;
use DigiSecure\Kernel\Error\ErrorHandler;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Subscription\Application\VerificarLimiteService;

/**
 * Controller CRUD de Fichas del Guardia.
 *
 * Rutas:
 *   GET    /guardias              → listado
 *   GET    /guardias/crear        → formulario de creación
 *   POST   /guardias/crear        → procesar creación
 *   GET    /guardias/{id}         → ver detalle
 *   GET    /guardias/{id}/editar  → formulario de edición
 *   POST   /guardias/{id}/editar  → procesar edición
 *   POST   /guardias/{id}/completar → marcar como COMPLETA
 */
final class FichaController
{
    private bool $cryptoIssueReported = false;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly FichaRepository $fichaRepo,
        private readonly Container $container,
        private readonly ErrorHandler $errorHandler,
        private readonly PdoConnection $db,
        private readonly VerificarLimiteService $verificarLimite,
        private readonly FlashBag $flash,
    ) {
    }

    // ======================== LISTADO ========================

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $page = max(1, (int)$request->query('page', 1));
        $limit = 25;
        $busqueda = trim((string) $request->query('q', ''));
        $filters = [];

        if ($busqueda !== '') {
            $filters['busqueda'] = $busqueda;
            $digits = preg_replace('/\D+/', '', $busqueda) ?? '';
            if (strlen($digits) === 10) {
                try {
                    $filters['cedula_hash'] = hash('sha256', $digits);
                } catch (DomainException) {
                    // Si la cédula no valida, se mantiene búsqueda textual.
                }
            }
        }

        $listError = false;
        try {
            $total = $this->fichaRepo->countGuardias($filters);
            $totalPages = max(1, (int)ceil($total / $limit));
            if ($page > $totalPages) {
                $page = $totalPages;
            }

            $offset = ($page - 1) * $limit;
            $guardias = $this->fichaRepo->listGuardias($limit, $offset, $filters);
            foreach ($guardias as &$guardia) {
                $guardia['cedula_display'] = $this->decryptSafe($guardia['cedula_cifrada'] ?? null);
            }
            unset($guardia);
        } catch (\Throwable $e) {
            $this->errorHandler->report($e);
            $listError = true;
            $total = 0;
            $totalPages = 1;
            $page = 1;
            $guardias = [];
        }

        return $this->view->response('ficha/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'guardias' => $guardias,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => ['busqueda' => $busqueda],
            'listError' => $listError,
        ]);
    }

    public function historial(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $fichaBase = $this->fichaRepo->findFichaById($id);
        if ($fichaBase === null) {
            return $this->view->response('errors/404', [], 404);
        }

        $filters = $this->normalizeHistorialFilters($request);
        $fichas = $this->fichaRepo->findByCedulaHashFiltered((string) $fichaBase['cedula_hash'], $filters);
        $guardia = [
            'id' => $fichaBase['id'],
            'nombres' => $fichaBase['nombres'] ?? '',
            'apellidos' => $fichaBase['apellidos'] ?? '',
            'fecha_nacimiento' => $fichaBase['fecha_nacimiento'] ?? null,
            'celular' => $fichaBase['celular'] ?? null,
            'email' => $fichaBase['email'] ?? null,
            'cedula_display' => $this->decryptSafe($fichaBase['cedula_cifrada'] ?? null),
        ];

        $exportQuery = \http_build_query(\array_filter([
            'estado' => $filters['estado'] ?? '',
            'tipo_ficha' => $filters['tipo_ficha'] ?? '',
            'desde' => $filters['desde'] ?? '',
            'hasta' => $filters['hasta'] ?? '',
        ], static fn(string $v): bool => $v !== ''));
        $exportPath = "/guardias/{$id}/fichas/exportar" . ($exportQuery !== '' ? '?' . $exportQuery : '');

        return $this->view->response('ficha/history', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'guardia' => $guardia,
            'fichas' => $fichas,
            'filters' => $filters,
            'exportPath' => $exportPath,
        ]);
    }

    public function exportarHistorial(Request $request, string $id): Response
    {
        $fichaBase = $this->fichaRepo->findFichaById($id);
        if ($fichaBase === null) {
            return $this->view->response('errors/404', [], 404);
        }

        $filters = $this->normalizeHistorialFilters($request);
        $fichas = $this->fichaRepo->findByCedulaHashFiltered((string) $fichaBase['cedula_hash'], $filters);

        $guardia = [
            'nombres' => (string) ($fichaBase['nombres'] ?? ''),
            'apellidos' => (string) ($fichaBase['apellidos'] ?? ''),
            'fecha_nacimiento' => (string) ($fichaBase['fecha_nacimiento'] ?? ''),
            'celular' => (string) ($fichaBase['celular'] ?? ''),
            'email' => (string) ($fichaBase['email'] ?? ''),
            'cedula_display' => $this->decryptSafe($fichaBase['cedula_cifrada'] ?? null),
        ];

        $csv = $this->buildHistorialCsv($guardia, $fichas);
        $safeName = preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim(($guardia['apellidos'] . '_' . $guardia['nombres']))));
        $filename = 'historial_guardia_' . trim((string) $safeName, '_') . '_' . date('Ymd_His') . '.csv';

        return new Response(
            body: $csv,
            status: Response::STATUS_OK,
            headers: [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    // ======================== CREAR ========================

    public function create(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $cursos = $this->loadCatalogoCursos();
        $input = [];
        $prefillFromLast = false;
        $baseFichaId = trim((string) $request->query('base_ficha', ''));
        if ($baseFichaId !== '') {
            $input = $this->buildCreatePrefillFromFicha($baseFichaId);
            $prefillFromLast = $input !== [];
        }

        // Aspirante en su primer ingreso: se precargan datos del registro, pero
        // todos los campos quedan editables para corregirlos antes de guardar.
        $registroLock = $this->aspiranteRegistroLock($user);
        if ($registroLock !== null) {
            $input = array_merge($input, $registroLock['input']);
        }

        return $this->view->response('ficha/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'cursos' => $cursos,
            'instituciones' => $this->loadInstitucionesFinancieras(),
            'input' => $input,
            'prefillFromLast' => $prefillFromLast,
            'lockedFields' => [],
            'prefillByCedulaUrl' => '/guardias/prefill/cedula',
            'formEnableCedulaPrefill' => true,
            'googleMapsKey' => $this->googleMapsKey(),
        ]);
    }

    public function prefillByCedula(Request $request): Response
    {
        $cedulaRaw = preg_replace('/\D+/', '', (string) $request->query('cedula', '')) ?? '';
        if (strlen($cedulaRaw) !== 10) {
            return Response::json([
                'found' => false,
                'error' => 'cedula_invalida',
            ], Response::STATUS_BAD_REQUEST);
        }

        $cedulaHash = hash('sha256', $cedulaRaw);
        $latestFicha = $this->fichaRepo->findLatestByCedulaHash($cedulaHash);
        if ($latestFicha === null) {
            // La cédula no existe => Ingreso.
            return Response::json([
                'found' => false,
                'tipo_ficha' => TipoFicha::INGRESO->value,
            ]);
        }

        // La cédula ya existe. Se decide según la baja registrada en la última ficha:
        //   - tiene fecha de baja Y motivo de baja => Reingreso (empleado dado de baja).
        //   - sin fecha ni motivo de baja          => Actualización (empleado vigente).
        $tieneFechaBaja = trim((string) ($latestFicha['fecha_baja'] ?? '')) !== '';
        $tieneMotivoBaja = trim((string) ($latestFicha['motivo_baja'] ?? '')) !== '';
        $dadoDeBaja = $tieneFechaBaja && $tieneMotivoBaja;
        $tipoFicha = ($dadoDeBaja ? TipoFicha::REINGRESO : TipoFicha::ACTUALIZACION)->value;

        $baseFichaId = (string) ($latestFicha['id'] ?? '');
        $input = $baseFichaId !== '' ? $this->buildCreatePrefillFromFicha($baseFichaId) : [];
        if ($input === []) {
            // No hay datos para prefijar, pero igual informamos el Tipo de Ficha.
            return Response::json([
                'found' => false,
                'tipo_ficha' => $tipoFicha,
            ]);
        }

        return Response::json([
            'found' => true,
            'tipo_ficha' => $tipoFicha,
            'input' => $input,
            'source' => [
                'id' => $baseFichaId,
                'correlativo' => (string) ($latestFicha['correlativo'] ?? ''),
                'nombres' => (string) ($latestFicha['nombres'] ?? ''),
                'apellidos' => (string) ($latestFicha['apellidos'] ?? ''),
            ],
        ]);
    }

    public function store(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;

        try {
            $this->verificarLimiteCrearGuardia();
            $result = $this->crearFichaService()->execute($input, $user->id, $request->clientIp());
            $this->flash->success('Ficha de guardia creada correctamente.');
            return Response::redirect($this->securePath($request, "/guardias/{$result['id']}"));
        } catch (DomainException $e) {
            $cursos = $this->loadCatalogoCursos();
            return $this->view->response('ficha/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/guardias/crear',
                'cursos' => $cursos,
                'instituciones' => $this->loadInstitucionesFinancieras(),
                'input' => $input,
                'error' => $e->getMessage(),
                'lockedFields' => [],
                'prefillByCedulaUrl' => '/guardias/prefill/cedula',
                'formEnableCedulaPrefill' => true,
                'googleMapsKey' => $this->googleMapsKey(),
            ]);
        } catch (\Throwable $e) {
            $this->errorHandler->report($e);
            $requestId = $this->errorHandler->requestId();
            $cursos = $this->safeLoadCatalogoCursos();
            $instituciones = $this->safeLoadInstitucionesFinancieras();
            return $this->view->response('ficha/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/guardias/crear',
                'cursos' => $cursos,
                'instituciones' => $instituciones,
                'input' => $input,
                'error' => 'No se pudo guardar la ficha en este momento. Revisa los datos e intenta nuevamente. Si el problema continua, reporta este Request ID al equipo tecnico: ' . $requestId,
                'lockedFields' => [],
                'prefillByCedulaUrl' => '/guardias/prefill/cedula',
                'formEnableCedulaPrefill' => true,
                'googleMapsKey' => $this->googleMapsKey(),
            ]);
        }
    }

    // ======================== VER DETALLE ========================

    public function show(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $ficha = $this->fichaRepo->findFichaById($id);
        if ($ficha === null) {
            return $this->view->response('errors/404', [], 404);
        }

        // Descifrar datos sensibles para display
        $ficha['cedula_display'] = $this->decryptSafe($ficha['cedula_cifrada']);
        $ficha['cuenta_bancaria_display'] = $this->decryptSafe($ficha['cuenta_bancaria_cifrada']);

        // Cargar relaciones
        $trabajos = $this->fichaRepo->getTrabajosAnteriores($id);
        $hijos = $this->fichaRepo->getHijosMenores($id);
        $referencias = $this->fichaRepo->getReferencias($id);
        $cursos = $this->fichaRepo->getCursos($id);

        return $this->view->response('ficha/show', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'ficha' => $ficha,
            'trabajos' => $trabajos,
            'hijos' => $hijos,
            'referencias' => $referencias,
            'cursos' => $cursos,
            'googleMapsKey' => $this->googleMapsKey(),
        ]);
    }

    public function croquisImagen(Request $request, string $id): Response
    {
        $ficha = $this->fichaRepo->findFichaById($id);
        if ($ficha === null || empty($ficha['croquis_archivo_id'])) {
            return $this->view->response('errors/404', [], 404);
        }

        $file = $this->croquisStorage()->find((string) $ficha['croquis_archivo_id']);
        if ($file === null) {
            return $this->view->response('errors/404', [], 404);
        }

        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $file['etag']) {
            return new Response(
                status: Response::STATUS_NOT_MODIFIED,
                headers: ['ETag' => $file['etag']],
            );
        }

        $content = @file_get_contents($file['path']);
        if ($content === false) {
            return $this->view->response('errors/404', [], 404);
        }

        $extension = match ($file['mime']) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return new Response(
            status: Response::STATUS_OK,
            body: $content,
            headers: [
                'Content-Type' => $file['mime'],
                'Content-Length' => (string) $file['size'],
                'Content-Disposition' => 'inline; filename="croquis-' . addslashes((string) ($ficha['correlativo'] ?? $id)) . '.' . $extension . '"',
                'Cache-Control' => 'private, max-age=300',
                'ETag' => $file['etag'],
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function fotoImagen(Request $request, string $id): Response
    {
        $ficha = $this->fichaRepo->findFichaById($id);
        if ($ficha === null || empty($ficha['foto_archivo_id'])) {
            return $this->view->response('errors/404', [], 404);
        }

        $file = $this->croquisStorage()->find((string) $ficha['foto_archivo_id']);
        if ($file === null) {
            return $this->view->response('errors/404', [], 404);
        }

        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $file['etag']) {
            return new Response(
                status: Response::STATUS_NOT_MODIFIED,
                headers: ['ETag' => $file['etag']],
            );
        }

        $content = @file_get_contents($file['path']);
        if ($content === false) {
            return $this->view->response('errors/404', [], 404);
        }

        $extension = match ($file['mime']) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return new Response(
            status: Response::STATUS_OK,
            body: $content,
            headers: [
                'Content-Type' => $file['mime'],
                'Content-Length' => (string) $file['size'],
                'Content-Disposition' => 'inline; filename="foto-' . addslashes((string) ($ficha['correlativo'] ?? $id)) . '.' . $extension . '"',
                'Cache-Control' => 'private, max-age=300',
                'ETag' => $file['etag'],
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    // ======================== EDITAR ========================

    public function edit(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $ficha = $this->fichaRepo->findFichaById($id);
        if ($ficha === null) {
            return $this->view->response('errors/404', [], 404);
        }

        $ficha['cedula_display'] = $this->decryptSafe($ficha['cedula_cifrada']);
        $ficha['cuenta_bancaria_display'] = $this->decryptSafe($ficha['cuenta_bancaria_cifrada']);

        $trabajos = $this->fichaRepo->getTrabajosAnteriores($id);
        $hijos = $this->fichaRepo->getHijosMenores($id);
        $referencias = $this->fichaRepo->getReferencias($id);
        $fichasCursos = $this->fichaRepo->getCursos($id);
        $catalogoCursos = $this->loadCatalogoCursos();

        return $this->view->response('ficha/edit', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'ficha' => $ficha,
            'trabajos' => $trabajos,
            'hijos' => $hijos,
            'referencias' => $referencias,
            'fichasCursos' => $fichasCursos,
            'cursos' => $catalogoCursos,
            'instituciones' => $this->loadInstitucionesFinancieras(),
            'googleMapsKey' => $this->googleMapsKey(),
            'expedienteResumen' => $this->buildExpedienteResumen($id),
            'expedienteUrl' => '/guardias/' . $id . '/expediente',
        ]);
    }

    /**
     * Resumen del expediente documental (Bloque A/B) para integrarlo en la
     * fase "Educación y Cursos" del formulario de edición.
     *
     * @return array{bloqueA: array{total:int,aceptados:int}, bloqueB: array{total:int,aceptados:int}}|null
     */
    private function buildExpedienteResumen(string $fichaId): ?array
    {
        $expRepo = $this->container->get(\DigiSecure\Expediente\Infrastructure\ExpedienteRepository::class);
        if (!$expRepo instanceof \DigiSecure\Expediente\Infrastructure\ExpedienteRepository) {
            return null;
        }

        $expediente = $expRepo->findExpedienteByFichaId($fichaId);

        return [
            'bloqueA' => [
                'total' => count($expRepo->getDocumentoTipos('A')),
                'aceptados' => $expediente !== null ? $expRepo->countAceptadosByBloque((string) $expediente['id'], 'A') : 0,
            ],
            'bloqueB' => [
                'total' => count($expRepo->getDocumentoTipos('B')),
                'aceptados' => $expediente !== null ? $expRepo->countAceptadosByBloque((string) $expediente['id'], 'B') : 0,
            ],
        ];
    }

    public function update(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;

        try {
            $this->actualizarFichaService()->execute($id, $input, $user->id, $request->clientIp());
            $this->flash->success('Ficha actualizada correctamente.');
            return Response::redirect($this->securePath($request, "/guardias/{$id}/editar"));
        } catch (DomainException $e) {
            $ficha = $this->fichaRepo->findFichaById($id) ?? [];
            $catalogoCursos = $this->loadCatalogoCursos();
            return $this->view->response('ficha/edit', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => "/guardias/{$id}/editar",
                'ficha' => array_merge($ficha, $input),
                'trabajos' => $input['trabajos_anteriores'] ?? [],
                'hijos' => $input['hijos_menores'] ?? [],
                'referencias' => $input['referencias'] ?? [],
                'fichasCursos' => $input['cursos'] ?? [],
                'cursos' => $catalogoCursos,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ======================== ENVIAR A REVISION ========================

    public function enviarRevision(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $this->enviarRevisionService()->execute($id, $user->id, $request->clientIp());
            $this->flash->success('Ficha enviada a revision correctamente.');
            return Response::redirect($this->securePath($request, "/guardias/{$id}"));
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo enviar la ficha a revision');
            return Response::redirect($this->securePath($request, "/guardias/{$id}"));
        }
    }

    // ======================== COMPLETAR ========================

    public function completar(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $incumplimientos = $this->completarService()->execute($id, $user->id, $request->clientIp());

            if ($incumplimientos !== []) {
                // Volver al detalle con los incumplimientos
                $ficha = $this->fichaRepo->findFichaById($id);
                $ficha['cedula_display'] = $this->decryptSafe($ficha['cedula_cifrada'] ?? '');
                $ficha['cuenta_bancaria_display'] = $this->decryptSafe($ficha['cuenta_bancaria_cifrada'] ?? '');

                return $this->view->response('ficha/show', [
                    'user' => $user,
                    'csrfToken' => $csrf->token(),
                    'currentPath' => "/guardias/{$id}",
                    'ficha' => $ficha,
                    'trabajos' => $this->fichaRepo->getTrabajosAnteriores($id),
                    'hijos' => $this->fichaRepo->getHijosMenores($id),
                    'referencias' => $this->fichaRepo->getReferencias($id),
                    'cursos' => $this->fichaRepo->getCursos($id),
                    'incumplimientos' => $incumplimientos,
                ]);
            }

            $this->flash->success('Ficha marcada como COMPLETA.');
            return Response::redirect($this->securePath($request, "/guardias/{$id}"));
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo completar la ficha');
            return Response::redirect($this->securePath($request, "/guardias/{$id}"));
        }
    }

    // ======================== ELIMINAR (LOGICO) ========================

    public function destroy(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $this->eliminarService()->execute($id, $user->id, $request->clientIp());
            $this->flash->success('Ficha eliminada correctamente.');
            return Response::redirect('/guardias');
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo eliminar la ficha');
            return Response::redirect($this->securePath($request, "/guardias/{$id}"));
        }
    }

    public function recover(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $this->recuperarService()->execute($id, $user->id, $request->clientIp());
            $this->flash->success('Ficha recuperada correctamente (estado Borrador).');
            return Response::redirect('/guardias');
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo recuperar la ficha');
            return Response::redirect($this->securePath($request, "/guardias/{$id}"));
        }
    }

    // ======================== PDFs ========================

    public function descargarDeclaracionLealtad(Request $request, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $result = $this->declaracionLealtadService()->execute($id, $user->id, $request->clientIp());
        } catch (DomainException) {
            return $this->view->response('errors/404', [], 404);
        }

        return new Response(
            status: Response::STATUS_OK,
            body: $result['pdf'],
            headers: [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
                'Content-Length' => (string)strlen($result['pdf']),
                'Cache-Control' => 'private, no-store',
                'Pragma' => 'no-cache',
            ],
        );
    }

    public function descargarFormatoHuellas(Request $request, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $result = $this->formatoHuellasService()->execute($id, $user->id, $request->clientIp());
        } catch (DomainException) {
            return $this->view->response('errors/404', [], 404);
        }

        return new Response(
            status: Response::STATUS_OK,
            body: $result['pdf'],
            headers: [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
                'Content-Length' => (string)strlen($result['pdf']),
                'Cache-Control' => 'private, no-store',
                'Pragma' => 'no-cache',
            ],
        );
    }

    // ======================== HELPERS ========================

    private function verificarLimiteCrearGuardia(): void
    {
        try {
            $this->verificarLimite->verificarCrearGuardia();
        } catch (DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->errorHandler->report($e);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadCatalogoCursos(): array
    {
        return $this->db->fetchAll(
            'SELECT id, codigo, nombre FROM catalogo_cursos WHERE activo = 1 ORDER BY orden',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function safeLoadCatalogoCursos(): array
    {
        try {
            return $this->loadCatalogoCursos();
        } catch (\Throwable $e) {
            $this->errorHandler->report($e);
            return [];
        }
    }

    /**
     * Instituciones financieras activas para el campo Banco (bancos primero,
     * luego cooperativas). Catálogo global compartido por todos los tenants.
     *
     * @return list<array<string, mixed>>
     */
    private function loadInstitucionesFinancieras(): array
    {
        return $this->db->fetchAll(
            "SELECT nombre, tipo FROM instituciones_financieras
             WHERE activo = 1
             ORDER BY FIELD(tipo, 'BANCO', 'COOPERATIVA'), orden, nombre",
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function safeLoadInstitucionesFinancieras(): array
    {
        try {
            return $this->loadInstitucionesFinancieras();
        } catch (\Throwable $e) {
            $this->errorHandler->report($e);
            return [];
        }
    }

    /**
     * API key de Google Maps (config maps.google_key / env GOOGLE_MAPS_API_KEY).
     * Vacía si no está configurada; las vistas degradan a un aviso.
     */
    private function googleMapsKey(): string
    {
        $config = $this->container->get(\DigiSecure\Kernel\Config\Config::class);
        return $config instanceof \DigiSecure\Kernel\Config\Config
            ? $config->string('maps.google_key', '')
            : '';
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

    /**
     * Si el usuario autenticado es un aspirante (guardia auto-registrado, en su
     * primer ingreso al sistema), devuelve los datos de su registro para prefijar
     * y BLOQUEAR los campos correspondientes de la ficha. Devuelve null para
     * cualquier otro usuario o si no se encuentra el registro.
     *
     * @return array{input: array<string, string>, locked: list<string>}|null
     */
    private function aspiranteRegistroLock(AuthenticatedUser $user): ?array
    {
        if (!$user->tieneRol('aspirante')) {
            return null;
        }

        $repo = $this->container->get(\DigiSecure\Aspirante\Infrastructure\AspiranteRepository::class);
        if (!$repo instanceof \DigiSecure\Aspirante\Infrastructure\AspiranteRepository) {
            return null;
        }

        $registro = $repo->findRegistroByUsuarioId($user->id);
        if ($registro === null) {
            return null;
        }

        // La estatura se guarda en cm (entero); la ficha la maneja en metros.
        $estatura = $registro['estatura_cm'] > 0
            ? number_format($registro['estatura_cm'] / 100, 2, '.', '')
            : '';

        $input = [
            'cedula'           => $registro['documento'],
            'apellidos'        => $registro['apellidos'],
            'nombres'          => $registro['nombres'],
            'fecha_nacimiento' => $registro['fecha_nacimiento'],
            'estatura'         => $estatura,
            'celular'          => $registro['celular'],
            'email'            => $registro['correo'],
        ];

        // Solo se bloquean los campos que realmente traen dato del registro, para
        // no dejar inservible un campo que quedara vacío.
        $locked = array_values(array_keys(array_filter(
            $input,
            static fn(string $v): bool => $v !== '',
        )));

        return ['input' => $input, 'locked' => $locked];
    }

    /**
     * Construye un prefill para crear una nueva ficha usando la ultima ficha
     * registrada del mismo guardia (por cedula_hash).
     *
     * @return array<string, mixed>
     */
    private function buildCreatePrefillFromFicha(string $baseFichaId): array
    {
        $baseFicha = $this->fichaRepo->findFichaById($baseFichaId);
        if ($baseFicha === null) {
            return [];
        }

        $sourceFicha = $baseFicha;
        $cedulaHash = (string) ($baseFicha['cedula_hash'] ?? '');
        if ($cedulaHash !== '') {
            $historial = $this->fichaRepo->findByCedulaHashFiltered($cedulaHash);
            $latestId = (string) ($historial[0]['id'] ?? '');
            if ($latestId !== '') {
                $latestFicha = $this->fichaRepo->findFichaById($latestId);
                if ($latestFicha !== null) {
                    $sourceFicha = $latestFicha;
                }
            }
        }

        $input = [];
        $scalarKeys = [
            'nombres',
            'apellidos',
            'genero',
            'fecha_nacimiento',
            'lugar_nacimiento_ciudad',
            'lugar_nacimiento_provincia',
            'tipo_sangre',
            'estatura',
            'estado_civil',
            'celular',
            'email',
            'domicilio_ciudad',
            'domicilio_tipo_vivienda',
            'domicilio_telefono',
            'domicilio_calle',
            'domicilio_numero',
            'domicilio_barrio',
            'domicilio_referencia',
            'contacto_emergencia_nombre',
            'contacto_emergencia_telefono',
            'banco',
            'tipo_cuenta',
            'instruccion_primaria',
            'instruccion_secundaria',
            'instruccion_superior',
            'fecha_ultimo_reentrenamiento',
            'croquis_latitud',
            'croquis_longitud',
        ];

        foreach ($scalarKeys as $key) {
            if (\array_key_exists($key, $sourceFicha)) {
                $input[$key] = $sourceFicha[$key];
            }
        }

        $input['cedula'] = $this->decryptSafe($sourceFicha['cedula_cifrada'] ?? null);
        $input['cuenta_bancaria'] = $this->decryptSafe($sourceFicha['cuenta_bancaria_cifrada'] ?? null);

        $sourceId = (string) ($sourceFicha['id'] ?? '');
        if ($sourceId === '') {
            return $input;
        }

        $input['trabajos_anteriores'] = array_map(
            static fn(array $t): array => [
                'empresa' => (string) ($t['empresa'] ?? ''),
                'cargo' => (string) ($t['cargo'] ?? ''),
                'tiempo' => (string) ($t['tiempo'] ?? ''),
                'direccion' => (string) ($t['direccion'] ?? ''),
                'telefonos' => (string) ($t['telefonos'] ?? ''),
            ],
            $this->fichaRepo->getTrabajosAnteriores($sourceId),
        );

        $input['hijos_menores'] = array_map(
            static fn(array $h): array => [
                'nombre' => (string) ($h['nombre'] ?? ''),
                'edad' => (string) ($h['edad'] ?? ''),
                'justifica_con_cedula' => (string) ($h['justifica_con_cedula'] ?? '0'),
            ],
            $this->fichaRepo->getHijosMenores($sourceId),
        );

        $input['referencias'] = array_map(
            static fn(array $r): array => [
                'parentesco' => (string) ($r['parentesco'] ?? 'OTRO'),
                'nombre' => (string) ($r['nombre'] ?? ''),
                'direccion' => (string) ($r['direccion'] ?? ''),
                'telefono' => (string) ($r['telefono'] ?? ''),
            ],
            $this->fichaRepo->getReferencias($sourceId),
        );

        $input['cursos'] = array_values(array_filter(array_map(
            static function (array $c): array {
                $cursoId = (string) ($c['curso_id'] ?? '');
                if ($cursoId === '') {
                    return [];
                }
                return [
                    'curso_id' => $cursoId,
                    'fecha_obtencion' => (string) ($c['fecha_obtencion'] ?? ''),
                    'observacion' => (string) ($c['observacion'] ?? ''),
                ];
            },
            $this->fichaRepo->getCursos($sourceId),
        )));

        return $input;
    }

    /**
     * @return array{estado: string, tipo_ficha: string, desde: string, hasta: string}
     */
    private function normalizeHistorialFilters(Request $request): array
    {
        $estado = strtoupper(trim((string) $request->query('estado', '')));
        $tipo = strtoupper(trim((string) $request->query('tipo_ficha', '')));
        $desde = $this->normalizeDate((string) $request->query('desde', '')) ?? '';
        $hasta = $this->normalizeDate((string) $request->query('hasta', '')) ?? '';

        $estadosValidos = array_map(static fn(EstadoFicha $e): string => $e->value, EstadoFicha::cases());
        $tiposValidos = array_map(static fn(TipoFicha $t): string => $t->value, TipoFicha::cases());

        if (!in_array($estado, $estadosValidos, true)) {
            $estado = '';
        }
        if (!in_array($tipo, $tiposValidos, true)) {
            $tipo = '';
        }

        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'estado' => $estado,
            'tipo_ficha' => $tipo,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
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

        [$y, $m, $d] = array_map(static fn(string $p): int => (int) $p, explode('-', $value));
        if (!checkdate($m, $d, $y)) {
            return null;
        }

        return $value;
    }

    /**
     * @param array{
     *   nombres: string,
     *   apellidos: string,
     *   fecha_nacimiento: string,
     *   celular: string,
     *   email: string,
     *   cedula_display: string
     * } $guardia
     * @param list<array<string, mixed>> $fichas
     */
    private function buildHistorialCsv(array $guardia, array $fichas): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'Cedula',
            'Apellidos',
            'Nombres',
            'Fecha nacimiento',
            'Celular',
            'Correo',
            'Correlativo',
            'Tipo ficha',
            'Estado',
            'Fecha emision',
            'Fecha revision',
            'Creada',
            'Actualizada',
        ], ';');

        foreach ($fichas as $ficha) {
            $tipo = (string) ($ficha['tipo_ficha'] ?? '');
            $estado = (string) ($ficha['estado'] ?? '');
            $tipoLabel = $tipo;
            $estadoLabel = $estado;

            try {
                if ($tipo !== '') {
                    $tipoLabel = TipoFicha::from($tipo)->label();
                }
            } catch (\ValueError) {
                $tipoLabel = $tipo;
            }

            try {
                if ($estado !== '') {
                    $estadoLabel = EstadoFicha::from($estado)->label();
                }
            } catch (\ValueError) {
                $estadoLabel = $estado;
            }

            fputcsv($stream, [
                $guardia['cedula_display'],
                $guardia['apellidos'],
                $guardia['nombres'],
                $guardia['fecha_nacimiento'],
                $guardia['celular'],
                $guardia['email'],
                (string) ($ficha['correlativo'] ?? ''),
                $tipoLabel,
                $estadoLabel,
                (string) ($ficha['fecha_emision'] ?? ''),
                (string) ($ficha['fecha_revision'] ?? ''),
                (string) ($ficha['created_at'] ?? ''),
                (string) ($ficha['updated_at'] ?? ''),
            ], ';');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv !== false ? $csv : '';
    }

    private function decryptSafe(mixed $ciphertext): string
    {
        if (empty($ciphertext) || !is_string($ciphertext)) {
            return '';
        }
        try {
            return $this->encryption()->decrypt($ciphertext);
        } catch (\Throwable $e) {
            if (!$this->cryptoIssueReported) {
                $this->errorHandler->report($e);
                $this->cryptoIssueReported = true;
            }
            return '[cifrado]';
        }
    }

    private function crearFichaService(): CrearFichaService
    {
        $service = $this->container->get(CrearFichaService::class);
        if (!$service instanceof CrearFichaService) {
            throw new \RuntimeException('No se pudo resolver CrearFichaService.');
        }
        return $service;
    }

    private function actualizarFichaService(): ActualizarFichaService
    {
        $service = $this->container->get(ActualizarFichaService::class);
        if (!$service instanceof ActualizarFichaService) {
            throw new \RuntimeException('No se pudo resolver ActualizarFichaService.');
        }
        return $service;
    }

    private function croquisStorage(): CroquisStorage
    {
        $storage = $this->container->get(CroquisStorage::class);
        if (!$storage instanceof CroquisStorage) {
            throw new \RuntimeException('No se pudo resolver CroquisStorage.');
        }
        return $storage;
    }

    private function enviarRevisionService(): EnviarRevisionFichaService
    {
        $service = $this->container->get(EnviarRevisionFichaService::class);
        if (!$service instanceof EnviarRevisionFichaService) {
            throw new \RuntimeException('No se pudo resolver EnviarRevisionFichaService.');
        }
        return $service;
    }

    private function completarService(): CompletarFichaService
    {
        $service = $this->container->get(CompletarFichaService::class);
        if (!$service instanceof CompletarFichaService) {
            throw new \RuntimeException('No se pudo resolver CompletarFichaService.');
        }
        return $service;
    }

    private function eliminarService(): EliminarFichaService
    {
        $service = $this->container->get(EliminarFichaService::class);
        if (!$service instanceof EliminarFichaService) {
            throw new \RuntimeException('No se pudo resolver EliminarFichaService.');
        }
        return $service;
    }

    private function recuperarService(): RecuperarFichaService
    {
        $service = $this->container->get(RecuperarFichaService::class);
        if (!$service instanceof RecuperarFichaService) {
            throw new \RuntimeException('No se pudo resolver RecuperarFichaService.');
        }
        return $service;
    }

    private function declaracionLealtadService(): GenerarDeclaracionLealtadService
    {
        $service = $this->container->get(GenerarDeclaracionLealtadService::class);
        if (!$service instanceof GenerarDeclaracionLealtadService) {
            throw new \RuntimeException('No se pudo resolver GenerarDeclaracionLealtadService.');
        }
        return $service;
    }

    private function formatoHuellasService(): GenerarFormatoHuellasService
    {
        $service = $this->container->get(GenerarFormatoHuellasService::class);
        if (!$service instanceof GenerarFormatoHuellasService) {
            throw new \RuntimeException('No se pudo resolver GenerarFormatoHuellasService.');
        }
        return $service;
    }

    private function encryption(): Encryption
    {
        $service = $this->container->get(Encryption::class);
        if (!$service instanceof Encryption) {
            throw new \RuntimeException('No se pudo resolver Encryption.');
        }
        return $service;
    }
}
