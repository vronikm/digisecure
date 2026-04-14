<?php

declare(strict_types=1);

namespace DigiSecure\Ficha\Controller;

use DigiSecure\Ficha\Application\ActualizarFichaService;
use DigiSecure\Ficha\Application\CompletarFichaService;
use DigiSecure\Ficha\Application\CrearFichaService;
use DigiSecure\Ficha\Application\GenerarDeclaracionLealtadService;
use DigiSecure\Ficha\Application\GenerarFormatoHuellasService;
use DigiSecure\Ficha\Infrastructure\FichaRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

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
        private readonly CompletarFichaService $completarService,
        private readonly GenerarDeclaracionLealtadService $declaracionLealtadService,
        private readonly GenerarFormatoHuellasService $formatoHuellasService,
        private readonly Encryption $encryption,
        private readonly PdoConnection $db,
    ) {
    }

    // ======================== LISTADO ========================

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $page = max(1, (int)$request->query('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = array_filter([
            'estado' => $request->query('estado'),
            'tipo_ficha' => $request->query('tipo_ficha'),
            'busqueda' => $request->query('q'),
        ]);

        $fichas = $this->fichaRepo->listFichas($limit, $offset, $filters);
        $total = $this->fichaRepo->countFichas($filters);
        $totalPages = (int)ceil($total / $limit);

        return $this->view->response('ficha/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'fichas' => $fichas,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters,
        ]);
    }

    // ======================== CREAR ========================

    public function create(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $cursos = $this->loadCatalogoCursos();

        return $this->view->response('ficha/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'cursos' => $cursos,
            'input' => [],
        ]);
    }

    public function store(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $input = $request->post;

        try {
            $result = $this->crearService->execute($input, $user->id, $request->clientIp());
            // Redirigir al detalle de la ficha creada
            return Response::redirect("/guardias/{$result['id']}");
        } catch (DomainException $e) {
            $cursos = $this->loadCatalogoCursos();
            return $this->view->response('ficha/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/guardias/crear',
                'cursos' => $cursos,
                'input' => $input,
                'error' => $e->getMessage(),
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
            return Response::redirect("/guardias/{$id}");
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
                'fichasCursos' => [],
                'cursos' => $catalogoCursos,
                'error' => $e->getMessage(),
            ]);
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

            return Response::redirect("/guardias/{$id}");
        } catch (DomainException $e) {
            return Response::redirect("/guardias/{$id}");
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
