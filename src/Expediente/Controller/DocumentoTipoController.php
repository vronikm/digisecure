<?php

declare(strict_types=1);

namespace DigiSecure\Expediente\Controller;

use DigiSecure\Expediente\Application\ConfigurarDocumentoTipoService;
use DigiSecure\Expediente\Infrastructure\DocumentoTipoRepository;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Gestión del catálogo de tipos de documento por tenant.
 *
 * Rutas:
 *   GET  /configuracion/documentos              → listado de tipos efectivos
 *   GET  /configuracion/documentos/{codigo}      → form de edición
 *   POST /configuracion/documentos/{codigo}      → guardar configuración
 *   POST /configuracion/documentos/{codigo}/toggle → activar/desactivar
 */
final class DocumentoTipoController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly DocumentoTipoRepository $tipoRepo,
        private readonly ConfigurarDocumentoTipoService $configurarService,
        private readonly FlashBag $flash,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $bloque = $request->query('bloque');
        $bloque = in_array($bloque, ['A', 'B'], true) ? $bloque : null;

        $tipos = $this->tipoRepo->listEfectivos($bloque);

        $kpis = [
            'total' => count($tipos),
            'activos' => count(array_filter($tipos, static fn(array $t) => (int)$t['activo'] === 1)),
            'con_vencimiento' => count(array_filter($tipos, static fn(array $t) => (int)$t['tiene_vencimiento'] === 1 && (int)$t['activo'] === 1)),
            'bloque_a' => count(array_filter($tipos, static fn(array $t) => $t['bloque'] === 'A' && (int)$t['activo'] === 1)),
            'bloque_b' => count(array_filter($tipos, static fn(array $t) => $t['bloque'] === 'B' && (int)$t['activo'] === 1)),
        ];

        return $this->view->response('documento-tipos/index', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'tipos' => $tipos,
            'kpis' => $kpis,
            'bloqueFilter' => $bloque,
        ]);
    }

    public function create(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        return $this->view->response('documento-tipos/create', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'input' => [
                'bloque' => 'A',
                'orden' => 0,
                'obligatorio' => 1,
                'numero_documentos' => 1,
                'requiere_validacion_externa' => 0,
                'tiene_vencimiento' => 0,
                'formatos_permitidos' => 'pdf',
                'tamano_maximo_mb' => '10',
                'activo' => 1,
            ],
        ]);
    }

    public function store(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $this->configurarService->crear(
                $request->post,
                $user->id,
                $request->clientIp(),
            );
            $this->flash->success('Tipo de documento creado correctamente.');
            return Response::redirect('/configuracion/documentos');
        } catch (DomainException $e) {
            return $this->view->response('documento-tipos/create', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/configuracion/documentos/nuevo',
                'input' => $request->post,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function edit(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $codigo = (string) $request->attribute('route.codigo');

        $tipo = $this->tipoRepo->findByCodigo($codigo);
        if ($tipo === null) {
            return $this->view->response('errors/404', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => $request->path,
            ], 404);
        }

        return $this->view->response('documento-tipos/edit', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'tipo' => $tipo,
        ]);
    }

    public function update(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $codigo = (string) $request->attribute('route.codigo');

        try {
            $this->configurarService->execute(
                $codigo,
                $request->post,
                $user->id,
                $request->clientIp(),
            );
            $this->flash->success('Configuracion de tipo de documento guardada.');
            return Response::redirect('/configuracion/documentos');
        } catch (DomainException $e) {
            $tipo = $this->tipoRepo->findByCodigo($codigo);
            return $this->view->response('documento-tipos/edit', [
                'user' => $user,
                'csrfToken' => $csrf->token(),
                'currentPath' => '/configuracion/documentos/' . $codigo,
                'tipo' => $tipo !== null ? array_merge($tipo, $request->post) : $request->post,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function toggle(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $codigo = (string) $request->attribute('route.codigo');

        $tipo = $this->tipoRepo->findByCodigo($codigo);
        if ($tipo === null) {
            return Response::redirect('/configuracion/documentos');
        }

        $nuevoActivo = (int)$tipo['activo'] === 1 ? 0 : 1;

        try {
            $this->configurarService->execute(
                $codigo,
                ['activo' => $nuevoActivo],
                $user->id,
                $request->clientIp(),
            );
            $this->flash->success($nuevoActivo === 1 ? 'Tipo de documento activado.' : 'Tipo de documento desactivado.');
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo cambiar el estado del tipo');
        }

        return Response::redirect('/configuracion/documentos');
    }
}
