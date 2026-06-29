<?php

declare(strict_types=1);

namespace DigiSecure\Catalogo\Controller;

use DigiSecure\Catalogo\Domain\TipoInstitucion;
use DigiSecure\Catalogo\Infrastructure\InstitucionFinancieraRepository;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Infrastructure\IdGenerator;

/**
 * CRUD del catálogo global de instituciones financieras (bancos / cooperativas).
 *
 * Rutas (permiso configuracion.instituciones):
 *   GET  /configuracion/instituciones-financieras              → listado
 *   GET  /configuracion/instituciones-financieras/nueva        → form alta
 *   POST /configuracion/instituciones-financieras              → crear
 *   GET  /configuracion/instituciones-financieras/{id}/editar  → form edición
 *   POST /configuracion/instituciones-financieras/{id}         → actualizar
 *   POST /configuracion/instituciones-financieras/{id}/eliminar → eliminar
 */
final class InstitucionFinancieraController
{
    private const BASE_PATH = '/configuracion/instituciones-financieras';

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly InstitucionFinancieraRepository $repo,
        private readonly FlashBag $flash,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $q = trim((string) $request->query('q', ''));
        $items = $this->repo->listAll($q);

        $kpis = [
            'total' => count($items),
            'bancos' => count(array_filter($items, static fn(array $i): bool => $i['tipo'] === 'BANCO')),
            'cooperativas' => count(array_filter($items, static fn(array $i): bool => $i['tipo'] === 'COOPERATIVA')),
            'activas' => count(array_filter($items, static fn(array $i): bool => (int) $i['activo'] === 1)),
        ];

        return $this->view->response('instituciones-financieras/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'items' => $items,
            'kpis' => $kpis,
            'q' => $q,
        ]);
    }

    public function create(Request $request, CsrfGuard $csrf): Response
    {
        return $this->renderForm($request, $csrf, 'crear', [
            'id' => '',
            'nombre' => '',
            'tipo' => 'BANCO',
            'orden' => 0,
            'activo' => 1,
        ]);
    }

    public function store(Request $request, CsrfGuard $csrf): Response
    {
        $data = $this->sanitize($request->post);
        $error = $this->validate($data, '');
        if ($error !== null) {
            return $this->renderForm($request, $csrf, 'crear', array_merge($data, ['id' => '']), $error);
        }

        $data['id'] = IdGenerator::ulid();
        $this->repo->create($data);
        $this->flash->success('Institución financiera creada.');

        return Response::redirect(self::BASE_PATH);
    }

    public function edit(Request $request, CsrfGuard $csrf): Response
    {
        $id = (string) $request->attribute('route.id');
        $item = $this->repo->findById($id);
        if ($item === null) {
            $this->flash->error('La institución financiera no existe.');
            return Response::redirect(self::BASE_PATH);
        }

        return $this->renderForm($request, $csrf, 'editar', $item);
    }

    public function update(Request $request, CsrfGuard $csrf): Response
    {
        $id = (string) $request->attribute('route.id');
        $item = $this->repo->findById($id);
        if ($item === null) {
            $this->flash->error('La institución financiera no existe.');
            return Response::redirect(self::BASE_PATH);
        }

        $data = $this->sanitize($request->post);
        $error = $this->validate($data, $id);
        if ($error !== null) {
            return $this->renderForm($request, $csrf, 'editar', array_merge($data, ['id' => $id]), $error);
        }

        $this->repo->update($id, $data);
        $this->flash->success('Institución financiera actualizada.');

        return Response::redirect(self::BASE_PATH);
    }

    public function delete(Request $request, CsrfGuard $csrf): Response
    {
        $id = (string) $request->attribute('route.id');
        $item = $this->repo->findById($id);
        if ($item !== null) {
            $this->repo->delete($id);
            $this->flash->success('Institución financiera eliminada.');
        }

        return Response::redirect(self::BASE_PATH);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{nombre: string, tipo: string, orden: int, activo: int}
     */
    private function sanitize(array $post): array
    {
        $tipo = TipoInstitucion::tryFrom((string) ($post['tipo'] ?? 'BANCO'))?->value ?? TipoInstitucion::BANCO->value;

        return [
            'nombre' => trim((string) ($post['nombre'] ?? '')),
            'tipo' => $tipo,
            'orden' => max(0, (int) ($post['orden'] ?? 0)),
            'activo' => isset($post['activo']) && (string) $post['activo'] !== '0' ? 1 : 0,
        ];
    }

    /**
     * @param array{nombre: string, tipo: string, orden: int, activo: int} $data
     */
    private function validate(array $data, string $exceptId): ?string
    {
        if ($data['nombre'] === '') {
            return 'El nombre es obligatorio.';
        }
        if (mb_strlen($data['nombre']) > 150) {
            return 'El nombre no debe superar los 150 caracteres.';
        }
        if ($this->repo->existsByNombre($data['nombre'], $exceptId)) {
            return 'Ya existe una institución financiera con ese nombre.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderForm(Request $request, CsrfGuard $csrf, string $modo, array $item, ?string $error = null): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        return $this->view->response('instituciones-financieras/form', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'modo' => $modo,
            'item' => $item,
            'error' => $error,
        ]);
    }
}
