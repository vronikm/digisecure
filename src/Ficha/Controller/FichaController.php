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
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
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
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly FichaRepository $fichaRepo,
        private readonly CrearFichaService $crearService,
        private readonly ActualizarFichaService $actualizarService,
        private readonly EnviarRevisionFichaService $enviarRevisionService,
        private readonly CompletarFichaService $completarService,
        private readonly EliminarFichaService $eliminarService,
        private readonly RecuperarFichaService $recuperarService,
        private readonly GenerarDeclaracionLealtadService $declaracionLealtadService,
        private readonly GenerarFormatoHuellasService $formatoHuellasService,
        private readonly Encryption $encryption,
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

        return $this->view->response('ficha/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'guardias' => $guardias,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => ['busqueda' => $busqueda],
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

        return $this->view->response('ficha/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'cursos' => $cursos,
            'input' => $input,
            'prefillFromLast' => $prefillFromLast,
            'prefillByCedulaUrl' => '/guardias/prefill/cedula',
            'formEnableCedulaPrefill' => true,
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

        $latestFicha = $this->fichaRepo->findLatestByCedulaHash(hash('sha256', $cedulaRaw));
        if ($latestFicha === null) {
            return Response::json(['found' => false]);
        }

        $baseFichaId = (string) ($latestFicha['id'] ?? '');
        if ($baseFichaId === '') {
            return Response::json(['found' => false]);
        }

        $input = $this->buildCreatePrefillFromFicha($baseFichaId);
        if ($input === []) {
            return Response::json(['found' => false]);
        }

        return Response::json([
            'found' => true,
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
            $this->verificarLimite->verificarCrearGuardia();
            $result = $this->crearService->execute($input, $user->id, $request->clientIp());
            $this->flash->success('Ficha de guardia creada correctamente.');
            return Response::redirect($this->securePath($request, "/guardias/{$result['id']}"));
        } catch (DomainException $e) {
            $cursos = $this->loadCatalogoCursos();
            return $this->view->response('ficha/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/guardias/crear',
                'cursos' => $cursos,
                'input' => $input,
                'error' => $e->getMessage(),
                'prefillByCedulaUrl' => '/guardias/prefill/cedula',
                'formEnableCedulaPrefill' => true,
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
        ]);
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
        ]);
    }

    public function update(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;

        try {
            $this->actualizarService->execute($id, $input, $user->id, $request->clientIp());
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
            $this->enviarRevisionService->execute($id, $user->id, $request->clientIp());
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
            $incumplimientos = $this->completarService->execute($id, $user->id, $request->clientIp());

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
            $this->eliminarService->execute($id, $user->id, $request->clientIp());
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
            $this->recuperarService->execute($id, $user->id, $request->clientIp());
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
            $result = $this->declaracionLealtadService->execute($id, $user->id, $request->clientIp());
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
            $result = $this->formatoHuellasService->execute($id, $user->id, $request->clientIp());
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

    /**
     * @return list<array<string, mixed>>
     */
    private function loadCatalogoCursos(): array
    {
        return $this->db->fetchAll(
            'SELECT id, codigo, nombre FROM catalogo_cursos WHERE activo = 1 ORDER BY orden',
        );
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
            return $this->encryption->decrypt($ciphertext);
        } catch (\Throwable) {
            return '[cifrado]';
        }
    }
}
