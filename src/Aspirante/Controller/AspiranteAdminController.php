<?php

declare(strict_types=1);

namespace DigiSecure\Aspirante\Controller;

use DigiSecure\Aspirante\Application\ActualizarAspiranteService;
use DigiSecure\Aspirante\Application\GestionarAspirantesService;
use DigiSecure\Aspirante\Infrastructure\AspiranteEventLogger;
use DigiSecure\Aspirante\Infrastructure\AspiranteRepository;
use DigiSecure\Aspirante\Infrastructure\VerificationMailer;
use DigiSecure\Ficha\Infrastructure\CroquisStorage;
use DigiSecure\Kernel\Config\Config;
use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Audit\AuditLogger;
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
 *   GET  /reclutamiento/{id}/editar  formulario de correccion de datos
 *   POST /reclutamiento/{id}/editar  guarda la correccion (y reenvia el enlace)
 *   POST /reclutamiento/{id}/contratar promueve al aspirante a empleado
 *   POST /reclutamiento/{id}/aprobar alias legacy de contratar
 *   POST /reclutamiento/{id}/rechazar marca descartado
 */
final class AspiranteAdminController
{
    /** Valor visible cuando un dato cifrado no se puede descifrar (p. ej. clave rotada). */
    private const NO_DISPONIBLE = 'No disponible';

    /** Campos del formulario de correccion. */
    private const CAMPOS_EDICION = [
        'tipo_documento', 'documento', 'apellidos', 'nombres', 'celular',
        'correo', 'correo_confirmacion', 'estatura', 'fecha_nacimiento',
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly AspiranteRepository $repo,
        private readonly GestionarAspirantesService $service,
        private readonly FlashBag $flash,
        private readonly Encryption $encryption,
        private readonly CroquisStorage $croquisStorage,
        private readonly ActualizarAspiranteService $actualizar,
        private readonly AspiranteEventLogger $eventos,
        private readonly VerificationMailer $mailer,
        private readonly AuditLogger $audit,
        private readonly Config $config,
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
            'estadosEditables' => ActualizarAspiranteService::ESTADOS_EDITABLES,
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
            'puedeEditar' => ActualizarAspiranteService::esEditable($aspirante),
        ]);
    }

    public function edit(Request $request, CsrfGuard $csrf, string $id): Response
    {
        $aspirante = $this->repo->findDetalle($id);
        if ($aspirante === null) {
            return $this->notFound($request, $csrf);
        }
        if (!ActualizarAspiranteService::esEditable($aspirante)) {
            $this->flash->warning(
                'Los datos de un aspirante contratado o descartado se corrigen desde Usuarios o Guardias.',
                'Edición no disponible',
            );
            return Response::redirect($this->securePath($request, "/reclutamiento/{$id}"));
        }

        return $this->renderEdit($request, $csrf, $aspirante, []);
    }

    public function update(Request $request, CsrfGuard $csrf, string $id): Response
    {
        /** @var AuthenticatedUser $user */
        $user = $request->attribute('auth_user');

        $input = [];
        foreach (self::CAMPOS_EDICION as $campo) {
            $valor = $request->post($campo, '');
            $input[$campo] = is_scalar($valor) ? (string) $valor : '';
        }
        $input['reenviar_enlace'] = $request->post('reenviar_enlace') !== null;

        try {
            $r = $this->actualizar->execute($id, $input);
        } catch (DomainException $e) {
            $aspirante = $this->repo->findDetalle($id);
            if ($aspirante === null) {
                return $this->notFound($request, $csrf);
            }
            return $this->renderEdit($request, $csrf, $aspirante, $input, $e->getMessage());
        }

        $ip = $request->clientIp();
        $ua = $request->userAgent();

        if ($r['cambios'] === [] && $r['verificacion_token'] === null) {
            $this->flash->info('No había cambios que guardar.');
            return Response::redirect($this->securePath($request, "/reclutamiento/{$id}"));
        }

        // La auditoria vive aqui (como en el registro publico) para que el
        // servicio siga siendo testeable sin base de datos.
        if ($r['cambios'] !== []) {
            $detalles = [
                'campos' => $r['cambios'],
                'usuario_sincronizado' => $r['usuario_actualizado'],
            ];
            if ($r['correo_cambiado']) {
                $detalles['correo_anterior'] = $r['correo_anterior'];
                $detalles['correo_nuevo'] = $r['correo'];
            }
            $this->audit->log(
                'aspirante.datos_actualizados',
                $user->id,
                'aspirante',
                $id,
                detalles: $detalles,
                // Cambiar el correo de login de una cuenta existente es sensible.
                nivel: $r['correo_cambiado'] && $r['usuario_actualizado'] ? 'warning' : 'info',
                ip: $ip,
                userAgent: $ua,
            );
            $this->eventos->registrar(
                AspiranteEventLogger::DATOS_ACTUALIZADOS,
                $r['correo'],
                $id,
                $ip,
                $ua,
                ['campos' => $r['cambios'], 'por_usuario_id' => $user->id],
            );
        }

        if ($r['verificacion_token'] !== null) {
            $enviado = $this->enviarEnlaceVerificacion($id, $r, $ip, $ua);
            if ($enviado) {
                $this->flash->success(
                    "Se envió un nuevo enlace de verificación a {$r['correo']}. Vence en 48 horas.",
                    $r['cambios'] !== [] ? 'Datos actualizados' : 'Enlace reenviado',
                );
            } else {
                $this->flash->warning(
                    "No se pudo enviar el enlace a {$r['correo']}. El aspirante puede solicitarlo desde \"Reenviar enlace\" en la pantalla de inicio.",
                    $r['cambios'] !== [] ? 'Datos actualizados, correo no enviado' : 'Correo no enviado',
                );
            }
        } elseif ($r['correo_cambiado'] && $r['usuario_actualizado']) {
            $this->flash->success("El aspirante ahora inicia sesión con {$r['correo']}.", 'Datos actualizados');
        } else {
            $this->flash->success('Datos del aspirante actualizados.');
        }

        return Response::redirect($this->securePath($request, "/reclutamiento/{$id}"));
    }

    /**
     * @param array{nombre_completo: string, correo: string, correo_cambiado: bool, verificacion_token: ?string} $r
     */
    private function enviarEnlaceVerificacion(string $id, array $r, string $ip, string $ua): bool
    {
        $enlace = rtrim($this->config->string('app.url', ''), '/') . '/verificar?token=' . $r['verificacion_token'];
        $origen = ['origen' => 'reclutamiento', 'correo_corregido' => $r['correo_cambiado']];

        $this->eventos->registrar(AspiranteEventLogger::REENVIO_LINK, $r['correo'], $id, $ip, $ua, $origen);
        $resultado = $this->mailer->enviarVerificacion($r['correo'], $r['nombre_completo'], $enlace);
        if ($resultado['success']) {
            $this->eventos->registrar(AspiranteEventLogger::LINK_ENVIADO, $r['correo'], $id, $ip, $ua, $origen);
        }

        return $resultado['success'];
    }

    /**
     * @param array<string, mixed> $aspirante
     * @param array<string, mixed> $input Valores enviados (vacío = datos actuales).
     */
    private function renderEdit(Request $request, CsrfGuard $csrf, array $aspirante, array $input, ?string $error = null): Response
    {
        $usuarioId = trim((string) ($aspirante['usuario_id'] ?? ''));

        return $this->view->response('reclutamiento/edit', [
            'user'          => $request->attribute('auth_user'),
            'csrfToken'     => $csrf->token(),
            'currentPath'   => $request->path,
            'aspirante'     => $aspirante,
            'input'         => $input,
            'error'         => $error,
            'tieneFicha'    => $usuarioId !== '' && $this->repo->findFichaDeUsuario($usuarioId) !== null,
        ], $error !== null ? Response::STATUS_UNPROCESSABLE : Response::STATUS_OK);
    }

    private function notFound(Request $request, CsrfGuard $csrf): Response
    {
        return $this->view->response('errors/404', [
            'user'        => $request->attribute('auth_user'),
            'csrfToken'   => $csrf->token(),
            'currentPath' => $request->path,
        ], Response::STATUS_NOT_FOUND);
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
        $ficha['cuenta_bancaria_display'] = $cuentaBancaria === self::NO_DISPONIBLE ? '' : $cuentaBancaria;
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
            return self::NO_DISPONIBLE;
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
