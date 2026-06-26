<?php
declare(strict_types=1);

namespace DigiSecure\Infrastructure\Import\Controller;

use DigiSecure\Infrastructure\Import\GemesegExcelImportService;
use DigiSecure\Infrastructure\Import\GemesegStagingProcessorService;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Auth\AuthenticatedUser;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;
use Throwable;

final class ImportacionGemesegController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly GemesegExcelImportService $service,
        private readonly GemesegStagingProcessorService $processor,
        private readonly FlashBag $flash,
    ) {
    }

    public function index(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        return $this->renderForm($request, $csrf, $user);
    }

    public function analyze(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');
        $stored = null;

        try {
            $stored = $this->service->storeUploadedFile(
                $request->file('archivo'),
                (string) $request->post('source', ''),
            );
            $analysis = $this->service->analyzeFile($stored['path']);
            $validation = $this->service->databaseValidation($analysis, $stored['source']);

            return $this->renderForm($request, $csrf, $user, [
                'analysis' => $analysis,
                'validation' => $validation,
                'tempToken' => $stored['token'],
                'source' => $stored['source'],
            ]);
        } catch (DomainException $e) {
            if (is_array($stored)) {
                $this->service->cleanupToken((string) $stored['token']);
            }
            return $this->renderForm($request, $csrf, $user, ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            if (is_array($stored)) {
                $this->service->cleanupToken((string) $stored['token']);
            }
            return $this->renderForm($request, $csrf, $user, [
                'error' => 'No se pudo analizar el Excel. Verifica el archivo e intenta nuevamente.',
            ]);
        }
    }

    public function apply(Request $request, CsrfGuard $csrf): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $token = trim((string) $request->post('temp_token', ''));
        $source = $this->service->normalizeSource((string) $request->post('source', ''));
        $confirm = (string) $request->post('confirmar', '') === '1';

        if (!$confirm) {
            $this->flash->warning('Confirma la carga antes de guardar el staging cifrado.');
            return Response::redirect($this->securePath($request, '/importaciones/gemeseg'));
        }

        try {
            $path = $this->service->tempPathFromToken($token);
            $analysis = $this->service->analyzeFile($path);
            $result = $this->service->stageAnalysis($analysis, $source, $user->id, $request->clientIp());
            $this->service->cleanupToken($token);

            $message = "Staging cifrado guardado: {$result['inserted']} fila(s) insertada(s), {$result['skipped']} omitida(s).";
            if ($result['errors'] > 0) {
                $this->flash->warning($message . " {$result['errors']} fila(s) tuvieron error interno.");
            } else {
                $this->flash->success($message);
            }
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage());
        } catch (Throwable) {
            $this->flash->error('No se pudo guardar el staging cifrado del Excel.');
        }

        return Response::redirect($this->securePath($request, '/importaciones/gemeseg'));
    }

    public function process(Request $request): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $source = $this->service->normalizeSource((string) $request->post('source', ''));
        $confirm = (string) $request->post('confirmar_procesar', '') === '1';

        if (!$confirm) {
            $this->flash->warning('Confirma el procesamiento antes de convertir staging en registros del sistema.');
            return Response::redirect($this->securePath($request, '/importaciones/gemeseg'));
        }

        try {
            $result = $this->processor->process($source, $user->id, $request->clientIp());
            if ((int) $result['read_rows'] === 0) {
                $this->flash->info('No hay filas pendientes para procesar en esa fuente.');
            } else {
                $this->flash->success(sprintf(
                    'Procesamiento GEMESEG: %d fila(s) procesada(s), %d ficha(s) creada(s), %d ficha(s) actualizada(s), %d curso(s), %d vacuna(s), %d documento(s).',
                    (int) $result['processed_rows'],
                    (int) $result['created_fichas'],
                    (int) $result['updated_fichas'],
                    (int) $result['courses_created'],
                    (int) $result['vaccines_created'],
                    (int) $result['documents_created'],
                ));
                if ((int) $result['omitted_rows'] > 0 || (int) $result['error_rows'] > 0) {
                    $this->flash->warning(sprintf(
                        '%d fila(s) omitida(s) y %d con error quedaron para revision.',
                        (int) $result['omitted_rows'],
                        (int) $result['error_rows'],
                    ));
                }
            }
        } catch (DomainException $e) {
            $this->flash->error($e->getMessage());
        } catch (Throwable) {
            $this->flash->error('No se pudo procesar el staging GEMESEG.');
        }

        return Response::redirect($this->securePath($request, '/importaciones/gemeseg'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderForm(Request $request, CsrfGuard $csrf, AuthenticatedUser $user, array $data = []): Response
    {
        $recent = [];
        try {
            $recent = $this->service->recentStagingSources();
        } catch (Throwable) {
            $recent = [];
        }

        return $this->view->response('importaciones/gemeseg', array_merge([
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'recentSources' => $recent,
        ], $data));
    }

    private function securePath(Request $request, string $path): string
    {
        $formatter = $request->attribute('secure_url');
        if (!\is_callable($formatter)) {
            return $path;
        }

        try {
            $secure = $formatter($path);
        } catch (Throwable) {
            return $path;
        }

        return \is_string($secure) && $secure !== '' ? $secure : $path;
    }
}
