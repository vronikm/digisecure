<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Controller;

use DigiSecure\Aspirante\Application\GestionarAspirantesService;
use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Ficha\Infrastructure\CroquisStorage;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Crypto\Encryption;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;

/**
 * Panel de reclutamiento: revision y aprobacion de aspirantes (Fase 4).
 *
 * Rutas (permiso aspirantes.gestionar):
 *   GET  /reclutamiento              listado + filtros + KPIs
 *   GET  /reclutamiento/{id}         detalle del aspirante
 *   POST /reclutamiento/{id}/contratar promueve al aspirante a empleado
 *   POST /reclutamiento/{id}/aprobar alias legacy de contratar
 *   POST /reclutamiento/{id}/rechazar marca descartado
 */
final class AspiranteAdminController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AspiranteRepository $repo,
        private readonly GestionarAspirantesService $service,
        private readonly FlashBag $flash,
        private readonly Encryption $encryption,
        private readonly CroquisStorage $croquisStorage,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $page = max(1, (int) $request->query('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'q'          => $request->query('q'),
            'estado'     => $request->query('estado'),
            'verificado' => $request->query('verificado'),
        ];

        $aspirantes = $this->repo->listarAdmin($filters, $limit, $offset);
        $total = $this->repo->contarAdmin($filters);

        return $this->view->response('reclutamiento/index', [
            'user'        => $user,
            'csrfToken'   => $csrf->token(),
            'currentPath' => $request->path,
            'aspirantes'  => $aspirantes,
            'total'       => $total,
            'page'        => $page,
            'totalPages'  => max(1, (int) ceil($total / $limit)),
            'filters'     => $filters,
            'kpis'        => $this->repo->kpisAdmin(),
        ]);
    }

    public function show(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $aspirante = $this->repo->findDetalle($id);
        if ($aspirante === null) {
            return $this->view->response('errors/404', [
                'user'        => $user,
                'csrfToken'   => $csrf->token(),
                'currentPath' => $request->path,
            ], Response::STATUS_NOT_FOUND);
        }

        return $this->view->response('reclutamiento/show', [
            'user'        => $user,
            'csrfToken'   => $csrf->token(),
            'currentPath' => $request->path,
            'aspirante'   => $aspirante,
            'ficha'       => $this->buildFichaRevision($aspirante),
        ]);
    }

    /**
     * Arma (solo lectura) la ficha que el aspirante completó, para revisarla en
     * el detalle de reclutamiento. Devuelve null si aún no la ha creado.
     *
     * @param array<string, mixed> $aspirante
     * @return array<string, mixed>|null
     */
    private function buildFichaRevision(array $aspirante): ?array
    {
        $usuarioId = (string) ($aspirante['usuario_id'] ?? '');
        if ($usuarioId === '') {
            return null;
        }

        $ficha = $this->repo->findFichaDeUsuario($usuarioId);
        if ($ficha === null) {
            return null;
        }
        $fichaId = (string) $ficha['id'];

        $ficha['cedula_display'] = $this->decryptSafe($ficha['cedula_cifrada'] ?? null);
        $cuentaBancaria = $this->decryptSafe($ficha['cuenta_bancaria_cifrada'] ?? null);
        $ficha['cuenta_bancaria_display'] = $cuentaBancaria === '[cifrado]' ? '' : $cuentaBancaria;
        $ficha['foto_data_uri']           = $this->imagenDataUri($ficha['foto_archivo_id'] ?? null);
        $ficha['croquis_data_uri']        = $this->imagenDataUri($ficha['croquis_archivo_id'] ?? null);
        $ficha['trabajos']                = $this->repo->fichaTrabajos($fichaId);
        $ficha['hijos']                   = $this->repo->fichaHijos($fichaId);
        $ficha['referencias']             = $this->repo->fichaReferencias($fichaId);
        $ficha['cursos']                  = $this->repo->fichaCursos($fichaId);
        $ficha['expediente']              = $this->repo->findExpedienteDeFicha($fichaId);

        return $ficha;
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

    /**
     * Lee la imagen del storage por su archivo_id y la devuelve como data URI
     * (embebida). Evita depender de los endpoints tenant-scoped de imágenes,
     * inaccesibles para el reclutador (la ficha vive en otro tenant).
     */
    private function imagenDataUri(mixed $archivoId): string
    {
        if (empty($archivoId) || !is_string($archivoId)) {
            return '';
        }
        $file = $this->croquisStorage->find($archivoId);
        if ($file === null) {
            return '';
        }
        $binario = @file_get_contents($file['path']);
        if ($binario === false || $binario === '') {
            return '';
        }
        return 'data:' . $file['mime'] . ';base64,' . base64_encode($binario);
    }

    public function aprobar(Request $request, CsrfGuard $csrf, string $id): Response
    {
        return $this->contratar($request, $csrf, $id);
    }

    public function contratar(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        try {
            $this->service->contratar($id, $user->tenantId, $user->id, $request->clientIp());
            $this->flash->success('Aspirante contratado: ahora consta como empleado en Guardias.');

            $aspirante = $this->repo->findDetalle($id);
            $usuarioId = (string) ($aspirante['usuario_id'] ?? '');
            $ficha = $usuarioId !== '' ? $this->repo->findFichaDeUsuario($usuarioId) : null;
            $destino = $ficha !== null && !empty($ficha['id']) ? '/guardias/' . (string) $ficha['id'] : '/guardias';
            return Response::redirect($this->securePath($request, $destino));
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo contratar al aspirante');
        }

        return Response::redirect($this->securePath($request, "/reclutamiento/{$id}"));
    }

    public function rechazar(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $motivo = (string) $request->post('motivo', '');

        try {
            $this->service->rechazar($id, $motivo, $user->id, $request->clientIp());
            $this->flash->success('Aspirante rechazado.');
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage(), 'No se pudo rechazar al aspirante');
        }

        return Response::redirect($this->securePath($request, "/reclutamiento/{$id}"));
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
}
