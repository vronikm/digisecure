<?php

declare(strict_types=1);

namespace DigiSecure\Superadmin\Controller;

use DigiSecure\Kernel\Http\FlashBag;
use DigiSecure\Kernel\Http\Request;
use DigiSecure\Kernel\Http\Response;
use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Security\Audit\AuditLogger;
use DigiSecure\Security\Csrf\CsrfGuard;
use DigiSecure\Shared\Domain\DomainException;
use DigiSecure\Shared\Infrastructure\Clock;
use DigiSecure\Shared\Infrastructure\IdGenerator;
use DigiSecure\Subscription\Infrastructure\PlanRepository;
use DigiSecure\Superadmin\Infrastructure\SuperadminRepository;
use DigiSecure\Infrastructure\Persistence\PdoConnection;
use DigiSecure\Tenant\Domain\TenantContext;

/**
 * Panel de superadministrador (Fase 13).
 *
 * Todas las rutas /admin/* requieren permisos superadmin.*.
 * Las queries son globales (no filtradas por tenant).
 */
final class SuperadminController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly SuperadminRepository $repo,
        private readonly PlanRepository $planRepo,
        private readonly PdoConnection $db,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenantContext,
        private readonly FlashBag $flash,
    ) {
    }

    // ============================ Dashboard ============================

    public function dashboard(Request $request): Response
    {
        $stats = $this->repo->statsGlobales();
        $tenantsPorPlan = $this->repo->tenantsPorPlan();
        $registrosPorDia = $this->repo->registrosPorDia(30);
        $registrosRecientes = $this->repo->registrosRecientes(10);

        return $this->view->response('superadmin/dashboard', [
            'stats' => $stats,
            'tenantsPorPlan' => $tenantsPorPlan,
            'registrosPorDia' => $registrosPorDia,
            'registrosRecientes' => $registrosRecientes,
        ]);
    }

    // ============================ Listado tenants ============================

    public function tenants(Request $request, CsrfGuard $csrf): Response
    {
        $busqueda = trim((string) $request->query('q', '')) ?: null;
        $estado = trim((string) $request->query('estado', '')) ?: null;
        $plan = trim((string) $request->query('plan', '')) ?: null;
        $pagina = max(1, (int) $request->query('pagina', '1'));
        $user = $request->attribute('auth_user');

        $resultado = $this->repo->listarTenants($busqueda, $estado, $plan, $pagina, 20);
        $planes = $this->planRepo->listarActivos(false);

        return $this->view->response('superadmin/tenants', [
            'user' => $user,
            'csrfToken' => $csrf->token(),
            'tenants' => $resultado['rows'],
            'total' => $resultado['total'],
            'pagina' => $pagina,
            'porPagina' => 20,
            'busqueda' => $busqueda,
            'estadoFiltro' => $estado,
            'planFiltro' => $plan,
            'planes' => $planes,
        ]);
    }

    // ============================ Detalle tenant ============================

    public function tenantDetalle(Request $request, CsrfGuard $csrf, string $id): Response
    {
        $tenant = $this->repo->findTenant($id);
        if ($tenant === null) {
            return Response::html('<h1>Tenant no encontrado</h1>', 404);
        }

        $uso = $this->repo->usoTenant($id);
        $usuarios = $this->repo->usuariosTenant($id);
        $usuariosDisponibles = $this->repo->usuariosDisponiblesParaTenant($id, 150);
        $rolesDisponibles = $this->repo->rolesDisponiblesParaTenant($id);
        $ciclos = $this->repo->ciclosTenant($id);
        $registros = $this->repo->registrosTenant($id);
        $planes = $this->planRepo->listarActivos(false);

        return $this->view->response('superadmin/tenant-detalle', [
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'tenant' => $tenant,
            'uso' => $uso,
            'usuarios' => $usuarios,
            'usuariosDisponibles' => $usuariosDisponibles,
            'rolesDisponibles' => $rolesDisponibles,
            'ciclos' => $ciclos,
            'registros' => $registros,
            'planes' => $planes,
        ]);
    }

    // ============================ Crear tenant ============================

    public function tenantCrearForm(Request $request, CsrfGuard $csrf): Response
    {
        return $this->renderTenantCreateView(
            $csrf,
            $this->tenantCreateFormPath(),
            $this->tenantCreateBackPath(),
            $this->tenantCreateBackLabel(),
            [],
        );
    }

    public function tenantCrear(Request $request, CsrfGuard $csrf): Response
    {
        $formPath = $this->tenantCreateFormPath();
        $volverUrl = $this->tenantCreateBackPath();
        $volverLabel = $this->tenantCreateBackLabel();

        $input = $request->post;
        $planCodigo = trim((string) ($input['plan_codigo'] ?? 'trial'));
        $plan = $this->planRepo->findByCodigo($planCodigo);
        if ($plan === null || !$plan->activo) {
            $this->flash->error('Selecciona un plan valido y activo.');
            return $this->renderTenantCreateView($csrf, $formPath, $volverUrl, $volverLabel, $input);
        }

        $razonSocial = trim((string) ($input['razon_social'] ?? ''));
        $ruc = preg_replace('/\D/', '', (string) ($input['ruc'] ?? '')) ?? '';
        $email = mb_strtolower(trim((string) ($input['email_contacto'] ?? '')), 'UTF-8');
        $estado = trim((string) ($input['estado'] ?? 'activo'));

        if ($razonSocial === '' || mb_strlen($razonSocial) < 3) {
            $this->flash->error('La razon social es obligatoria (minimo 3 caracteres).');
            return $this->renderTenantCreateView($csrf, $formPath, $volverUrl, $volverLabel, $input);
        }
        if (!preg_match('/^\d{13}$/', $ruc)) {
            $this->flash->error('El RUC debe tener 13 digitos.');
            return $this->renderTenantCreateView($csrf, $formPath, $volverUrl, $volverLabel, $input);
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->flash->error('El email de contacto no es valido.');
            return $this->renderTenantCreateView($csrf, $formPath, $volverUrl, $volverLabel, $input);
        }
        if (!in_array($estado, ['activo', 'pendiente', 'suspendido', 'cancelado'], true)) {
            $estado = 'activo';
        }

        $inicio = trim((string) ($input['plan_inicio'] ?? ''));
        $venc = trim((string) ($input['plan_vencimiento'] ?? ''));
        if ($inicio === '') {
            $inicio = date('Y-m-d');
        }
        if ($venc === '') {
            $venc = (new \DateTimeImmutable($inicio))->modify('+30 days')->format('Y-m-d');
        }

        $maxUsuarios = max(1, (int) ($input['max_usuarios'] ?? $plan->maxUsuarios));
        $maxGuardias = max(1, (int) ($input['max_guardias'] ?? $plan->maxGuardias));
        $maxStorage = max(128, (int) ($input['max_storage_mb'] ?? $plan->maxStorageMb));

        $tenantId = IdGenerator::ulid();
        $now = Clock::nowString();
        $data = [
            'id' => $tenantId,
            'razon_social' => $razonSocial,
            'ruc' => $ruc,
            'nombre_comercial' => trim((string) ($input['nombre_comercial'] ?? '')) ?: null,
            'email_contacto' => $email,
            'telefono' => trim((string) ($input['telefono'] ?? '')) ?: null,
            'direccion' => trim((string) ($input['direccion'] ?? '')) ?: null,
            'ciudad' => trim((string) ($input['ciudad'] ?? '')) ?: null,
            'provincia' => trim((string) ($input['provincia'] ?? '')) ?: null,
            'pais' => strtoupper(trim((string) ($input['pais'] ?? 'EC'))) ?: 'EC',
            'estado' => $estado,
            'plan_codigo' => $plan->codigo,
            'plan_inicio' => $inicio,
            'plan_vencimiento' => $venc,
            'max_usuarios' => $maxUsuarios,
            'max_guardias' => $maxGuardias,
            'max_storage_mb' => $maxStorage,
            'timezone' => trim((string) ($input['timezone'] ?? 'America/Guayaquil')) ?: 'America/Guayaquil',
            'locale' => trim((string) ($input['locale'] ?? 'es_EC')) ?: 'es_EC',
            'notas' => trim((string) ($input['notas'] ?? '')) ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            $this->repo->crearTenant($data);
            $this->flash->success('Tenant creado correctamente.');

            $user = $request->attribute('auth_user');
            $this->audit->log(
                evento: 'superadmin.tenant.creado',
                usuarioId: $user?->id,
                entidadTipo: 'tenant',
                entidadId: $tenantId,
                detalles: [
                    'razon_social' => $razonSocial,
                    'plan_codigo' => $plan->codigo,
                    'estado' => $estado,
                ],
                nivel: 'warning',
                ip: $request->clientIp(),
            );

            if ($user?->tienePermiso('superadmin.tenants.ver')) {
                return Response::redirect($this->securePath($request, "/admin/tenants/{$tenantId}"));
            }

            return Response::redirect($formPath);
        } catch (\Throwable) {
            $this->flash->error('No se pudo crear el tenant. Verifica RUC/email unicos y datos obligatorios.');
            return $this->renderTenantCreateView($csrf, $formPath, $volverUrl, $volverLabel, $input);
        }
    }

    // ============================ Editar tenant ============================

    public function tenantEditarForm(Request $request, CsrfGuard $csrf, string $id): Response
    {
        $tenant = $this->repo->findTenant($id);
        if ($tenant === null) {
            $this->flash->error('Tenant no encontrado.');
            return Response::redirect('/admin/tenants');
        }

        return $this->view->response('superadmin/tenant-edit', [
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'tenant' => $tenant,
            'planes' => $this->planRepo->listarActivos(false),
            'input' => [],
        ]);
    }

    public function tenantActualizar(Request $request, CsrfGuard $csrf, string $id): Response
    {
        $tenant = $this->repo->findTenant($id);
        if ($tenant === null) {
            $this->flash->error('Tenant no encontrado.');
            return Response::redirect('/admin/tenants');
        }

        $input = $request->post;
        $planCodigo = trim((string) ($input['plan_codigo'] ?? (string) $tenant['plan_codigo']));
        $plan = $this->planRepo->findByCodigo($planCodigo);
        if ($plan === null || !$plan->activo) {
            $this->flash->error('Selecciona un plan valido y activo.');
            return $this->view->response('superadmin/tenant-edit', [
                'csrfToken' => $csrf->token(),
                'currentPath' => "/admin/tenants/{$id}/editar",
                'tenant' => array_merge($tenant, $input),
                'planes' => $this->planRepo->listarActivos(false),
                'input' => $input,
            ]);
        }

        $ruc = preg_replace('/\D+/', '', (string) ($input['ruc'] ?? (string) $tenant['ruc'])) ?? '';

        $update = [
            'razon_social' => trim((string) ($input['razon_social'] ?? (string) $tenant['razon_social'])),
            'ruc' => $ruc,
            'nombre_comercial' => trim((string) ($input['nombre_comercial'] ?? (string) ($tenant['nombre_comercial'] ?? ''))) ?: null,
            'email_contacto' => mb_strtolower(trim((string) ($input['email_contacto'] ?? (string) $tenant['email_contacto'])), 'UTF-8'),
            'telefono' => trim((string) ($input['telefono'] ?? (string) ($tenant['telefono'] ?? ''))) ?: null,
            'direccion' => trim((string) ($input['direccion'] ?? (string) ($tenant['direccion'] ?? ''))) ?: null,
            'ciudad' => trim((string) ($input['ciudad'] ?? (string) ($tenant['ciudad'] ?? ''))) ?: null,
            'provincia' => trim((string) ($input['provincia'] ?? (string) ($tenant['provincia'] ?? ''))) ?: null,
            'pais' => strtoupper(trim((string) ($input['pais'] ?? (string) ($tenant['pais'] ?? 'EC')))) ?: 'EC',
            'plan_codigo' => $plan->codigo,
            'plan_inicio' => trim((string) ($input['plan_inicio'] ?? (string) ($tenant['plan_inicio'] ?? ''))) ?: null,
            'plan_vencimiento' => trim((string) ($input['plan_vencimiento'] ?? (string) ($tenant['plan_vencimiento'] ?? ''))) ?: null,
            'max_usuarios' => max(1, (int) ($input['max_usuarios'] ?? (int) $tenant['max_usuarios'])),
            'max_guardias' => max(1, (int) ($input['max_guardias'] ?? (int) $tenant['max_guardias'])),
            'max_storage_mb' => max(128, (int) ($input['max_storage_mb'] ?? (int) $tenant['max_storage_mb'])),
            'timezone' => trim((string) ($input['timezone'] ?? (string) ($tenant['timezone'] ?? 'America/Guayaquil'))) ?: 'America/Guayaquil',
            'locale' => trim((string) ($input['locale'] ?? (string) ($tenant['locale'] ?? 'es_EC'))) ?: 'es_EC',
            'notas' => trim((string) ($input['notas'] ?? (string) ($tenant['notas'] ?? ''))) ?: null,
            'updated_at' => Clock::nowString(),
        ];

        if ($update['razon_social'] === '' || mb_strlen((string) $update['razon_social']) < 3) {
            $this->flash->error('La razon social es obligatoria (minimo 3 caracteres).');
            return $this->view->response('superadmin/tenant-edit', [
                'csrfToken' => $csrf->token(),
                'currentPath' => "/admin/tenants/{$id}/editar",
                'tenant' => array_merge($tenant, $input),
                'planes' => $this->planRepo->listarActivos(false),
                'input' => $input,
            ]);
        }
        if (!preg_match('/^\d{13}$/', (string) $update['ruc'])) {
            $this->flash->error('El RUC debe tener 13 digitos.');
            return $this->view->response('superadmin/tenant-edit', [
                'csrfToken' => $csrf->token(),
                'currentPath' => "/admin/tenants/{$id}/editar",
                'tenant' => array_merge($tenant, $input),
                'planes' => $this->planRepo->listarActivos(false),
                'input' => $input,
            ]);
        }
        if ((string) $update['email_contacto'] === '' || filter_var((string) $update['email_contacto'], FILTER_VALIDATE_EMAIL) === false) {
            $this->flash->error('El email de contacto no es valido.');
            return $this->view->response('superadmin/tenant-edit', [
                'csrfToken' => $csrf->token(),
                'currentPath' => "/admin/tenants/{$id}/editar",
                'tenant' => array_merge($tenant, $input),
                'planes' => $this->planRepo->listarActivos(false),
                'input' => $input,
            ]);
        }

        try {
            $this->repo->actualizarTenant($id, $update);
            $this->flash->success('Tenant actualizado correctamente.');

            $user = $request->attribute('auth_user');
            $this->audit->log(
                evento: 'superadmin.tenant.actualizado',
                usuarioId: $user?->id,
                entidadTipo: 'tenant',
                entidadId: $id,
                detalles: [
                    'razon_social' => $update['razon_social'],
                    'ruc_cambiado' => (string) $tenant['ruc'] !== (string) $update['ruc'],
                    'plan_codigo' => $update['plan_codigo'],
                ],
                nivel: 'warning',
                ip: $request->clientIp(),
            );

            return Response::redirect($this->securePath($request, "/admin/tenants/{$id}/editar"));
        } catch (\Throwable) {
            $this->flash->error('No se pudo actualizar el tenant. Verifica datos unicos y obligatorios.');
            return $this->view->response('superadmin/tenant-edit', [
                'csrfToken' => $csrf->token(),
                'currentPath' => "/admin/tenants/{$id}/editar",
                'tenant' => array_merge($tenant, $input),
                'planes' => $this->planRepo->listarActivos(false),
                'input' => $input,
            ]);
        }
    }

    // ======================== Asociar usuario -> tenant ========================

    public function asociarUsuarioTenant(Request $request, CsrfGuard $csrf, string $id): Response
    {
        $tenantDestino = $this->repo->findTenant($id);
        if ($tenantDestino === null) {
            $this->flash->error('Tenant destino no encontrado.');
            return Response::redirect('/admin/tenants');
        }

        $usuarioId = trim((string) $request->post('usuario_id', ''));
        $roleId = trim((string) $request->post('role_id', ''));

        if ($usuarioId === '') {
            $this->flash->error('Selecciona un usuario para asociar.');
            return Response::redirect($this->securePath($request, "/admin/tenants/{$id}"));
        }

        $usuario = $this->repo->findUsuarioGlobal($usuarioId);
        if ($usuario === null) {
            $this->flash->error('El usuario seleccionado no existe.');
            return Response::redirect($this->securePath($request, "/admin/tenants/{$id}"));
        }

        if ((string) $usuario['tenant_id'] === $id) {
            $this->flash->success('El usuario ya estaba asociado a este tenant.');
            return Response::redirect($this->securePath($request, "/admin/tenants/{$id}"));
        }

        if ($roleId !== '') {
            $roleValido = $this->db->fetchOne(
                'SELECT id FROM roles WHERE id = :rid AND (tenant_id = :tid OR tenant_id IS NULL)',
                ['rid' => $roleId, 'tid' => $id],
            );
            if ($roleValido === null) {
                $this->flash->error('El rol seleccionado no es valido para el tenant destino.');
                return Response::redirect($this->securePath($request, "/admin/tenants/{$id}"));
            }
        }

        try {
            $this->repo->asociarUsuarioATenant(
                $usuarioId,
                $id,
                $roleId !== '' ? $roleId : null,
                $request->attribute('auth_user')?->id,
            );

            $this->flash->success('Usuario asociado al tenant correctamente.');

            $user = $request->attribute('auth_user');
            $this->audit->log(
                evento: 'superadmin.usuario.asociado_tenant',
                usuarioId: $user?->id,
                entidadTipo: 'usuario',
                entidadId: $usuarioId,
                detalles: [
                    'tenant_origen_id' => $usuario['tenant_id'],
                    'tenant_origen' => $usuario['tenant_actual'],
                    'tenant_destino_id' => $id,
                    'tenant_destino' => $tenantDestino['razon_social'],
                    'role_id' => $roleId !== '' ? $roleId : null,
                ],
                nivel: 'warning',
                ip: $request->clientIp(),
            );
        } catch (\Throwable) {
            $this->flash->error('No se pudo asociar el usuario. Verifica que no exista conflicto de email en el tenant destino.');
        }

        return Response::redirect($this->securePath($request, "/admin/tenants/{$id}"));
    }

    // ============================ Cambiar estado ============================

    public function cambiarEstado(Request $request, CsrfGuard $csrf, string $id): Response
    {
        $nuevoEstado = trim((string) $request->post('estado', ''));
        $estadosValidos = ['activo', 'suspendido', 'cancelado', 'pendiente'];

        if (!in_array($nuevoEstado, $estadosValidos, true)) {
            $this->flash->error('Estado invalido.', 'No se pudo actualizar el tenant');
            return Response::redirect($this->securePath($request, "/admin/tenants/{$id}"));
        }

        $tenant = $this->repo->findTenant($id);
        if ($tenant === null) {
            $this->flash->error('El tenant indicado no existe.');
            return Response::redirect('/admin/tenants');
        }

        $this->repo->cambiarEstadoTenant($id, $nuevoEstado);
        $this->flash->success(
            "Estado actualizado a {$nuevoEstado} para {$tenant['razon_social']}.",
        );

        $user = $request->attribute('auth_user');
        $this->audit->log(
            evento: 'superadmin.tenant.estado_cambiado',
            usuarioId: $user?->id,
            entidadTipo: 'tenant',
            entidadId: $id,
            detalles: [
                'estado_anterior' => $tenant['estado'],
                'estado_nuevo' => $nuevoEstado,
                'razon_social' => $tenant['razon_social'],
            ],
            nivel: 'warning',
            ip: $request->clientIp(),
        );

        return Response::redirect($this->securePath($request, "/admin/tenants/{$id}"));
    }

    public function eliminarTenant(Request $request, string $id): Response
    {
        $tenant = $this->repo->findTenant($id);
        if ($tenant === null) {
            $this->flash->error('La empresa indicada no existe.');
            return Response::redirect('/admin/tenants');
        }

        if ((string) $tenant['estado'] === 'cancelado') {
            $this->flash->success('La empresa ya estaba eliminada del listado activo.');
            return Response::redirect('/admin/tenants');
        }

        $this->repo->cambiarEstadoTenant($id, 'cancelado');
        $this->flash->success('Empresa eliminada del listado activo. El historial queda conservado.');

        $user = $request->attribute('auth_user');
        $this->audit->log(
            evento: 'superadmin.tenant.eliminado',
            usuarioId: $user?->id,
            entidadTipo: 'tenant',
            entidadId: $id,
            detalles: [
                'estado_anterior' => $tenant['estado'],
                'estado_nuevo' => 'cancelado',
                'razon_social' => $tenant['razon_social'],
            ],
            nivel: 'warning',
            ip: $request->clientIp(),
        );

        return Response::redirect('/admin/tenants');
    }

    // ============================ Cambiar plan ============================

    public function cambiarPlan(Request $request, CsrfGuard $csrf, string $id): Response
    {
        $planCodigo = trim((string) $request->post('plan_codigo', ''));
        $meses = max(1, (int) $request->post('meses', '1'));

        $plan = $this->planRepo->findByCodigo($planCodigo);
        if ($plan === null || !$plan->activo) {
            $this->flash->error('El plan seleccionado no existe o esta inactivo.');
            return Response::redirect($this->securePath($request, "/admin/tenants/{$id}"));
        }

        $tenant = $this->repo->findTenant($id);
        if ($tenant === null) {
            $this->flash->error('El tenant indicado no existe.');
            return Response::redirect('/admin/tenants');
        }

        $inicio = new \DateTimeImmutable('today');
        $fin = $inicio->modify("+{$meses} months -1 day");

        $this->repo->cambiarPlanTenant(
            $id,
            $plan->codigo,
            $plan->maxUsuarios,
            $plan->maxGuardias,
            $plan->maxStorageMb,
            $inicio->format('Y-m-d'),
            $fin->format('Y-m-d'),
        );

        // Registrar ciclo de suscripción vía insert directo (no tenemos contexto del tenant)
        $cicloId = IdGenerator::ulid();
        $now = Clock::nowString();
        $precio = $meses >= 12 && $plan->precioAnualUsd > 0
            ? round((intdiv($meses, 12) * $plan->precioAnualUsd) + (($meses % 12) * $plan->precioMensualUsd), 2)
            : round($meses * $plan->precioMensualUsd, 2);

        $this->db->execute(
            'INSERT INTO suscripcion_ciclos
                (id, tenant_id, plan_codigo, tipo, fecha_inicio, fecha_fin,
                 precio_usd, estado_pago, metodo_pago, notas, created_by, created_at, updated_at)
             VALUES
                (:id, :tid, :pc, :tipo, :ini, :fin,
                 :precio, :ep, :mp, :notas, :cb, :ca, :ua)',
            [
                'id' => $cicloId, 'tid' => $id, 'pc' => $plan->codigo,
                'tipo' => 'upgrade', 'ini' => $inicio->format('Y-m-d'), 'fin' => $fin->format('Y-m-d'),
                'precio' => $precio, 'ep' => 'cortesia', 'mp' => 'superadmin',
                'notas' => 'Asignado por superadmin', 'cb' => $request->attribute('auth_user')?->id,
                'ca' => $now, 'ua' => $now,
            ],
        );

        $user = $request->attribute('auth_user');
        $this->audit->log(
            evento: 'superadmin.tenant.plan_cambiado',
            usuarioId: $user?->id,
            entidadTipo: 'tenant',
            entidadId: $id,
            detalles: [
                'plan_anterior' => $tenant['plan_codigo'],
                'plan_nuevo' => $plan->codigo,
                'meses' => $meses,
                'razon_social' => $tenant['razon_social'],
            ],
            nivel: 'warning',
            ip: $request->clientIp(),
        );

        $this->flash->success(
            "Plan actualizado a {$plan->nombre} ({$meses} mes" . ($meses === 1 ? '' : 'es') . ").",
            $tenant['razon_social'],
        );

        return Response::redirect($this->securePath($request, "/admin/tenants/{$id}"));
    }

    // ============================ Log de registros ============================

    public function registros(Request $request): Response
    {
        $registros = $this->repo->registrosRecientes(50);

        return $this->view->response('superadmin/registros', [
            'registros' => $registros,
        ]);
    }

    // ============================ Planes ============================

    public function planes(Request $request, CsrfGuard $csrf): Response
    {
        $busqueda = trim((string) $request->query('q', ''));
        $estado = trim((string) $request->query('estado', ''));
        $publico = trim((string) $request->query('publico', ''));

        $planes = $this->planRepo->listarCatalogoGlobal(
            $busqueda !== '' ? $busqueda : null,
            $estado !== '' ? $estado : null,
            $publico !== '' ? $publico : null,
        );

        $stats = [
            'total' => count($planes),
            'activos' => count(array_filter($planes, static fn(array $p): bool => (int) ($p['activo'] ?? 0) === 1)),
            'inactivos' => count(array_filter($planes, static fn(array $p): bool => (int) ($p['activo'] ?? 0) !== 1)),
            'publicos' => count(array_filter($planes, static fn(array $p): bool => (int) ($p['es_publico'] ?? 0) === 1)),
        ];

        return $this->view->response('superadmin/plans', [
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'planes' => $planes,
            'stats' => $stats,
            'busqueda' => $busqueda,
            'estadoFiltro' => $estado,
            'publicoFiltro' => $publico,
        ]);
    }

    public function planCrearForm(Request $request, CsrfGuard $csrf): Response
    {
        return $this->view->response('superadmin/plan-create', [
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'input' => [],
        ]);
    }

    public function planCrear(Request $request, CsrfGuard $csrf): Response
    {
        $input = $request->post;
        $validation = $this->validarPayloadPlan($input);

        if ($validation['ok'] !== true) {
            $this->flash->error((string) $validation['error']);
            return $this->view->response('superadmin/plan-create', [
                'csrfToken' => $csrf->token(),
                'currentPath' => '/admin/planes/crear',
                'input' => $input,
            ]);
        }

        $payload = $validation['payload'];
        $codigo = (string) $payload['codigo'];
        if ($this->planRepo->existsCodigo($codigo)) {
            $this->flash->error('Ya existe un plan con ese codigo.');
            return $this->view->response('superadmin/plan-create', [
                'csrfToken' => $csrf->token(),
                'currentPath' => '/admin/planes/crear',
                'input' => $input,
            ]);
        }

        $now = Clock::nowString();
        $planId = IdGenerator::ulid();
        $data = array_merge($payload, [
            'id' => $planId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $this->planRepo->create($data);
            $this->flash->success('Plan creado correctamente.');

            $user = $request->attribute('auth_user');
            $this->audit->log(
                evento: 'superadmin.plan.creado',
                usuarioId: $user?->id,
                entidadTipo: 'plan',
                entidadId: $planId,
                detalles: [
                    'codigo' => $codigo,
                    'nombre' => $payload['nombre'],
                    'activo' => (int) $payload['activo'],
                ],
                nivel: 'warning',
                ip: $request->clientIp(),
            );

            return Response::redirect($this->securePath($request, '/admin/planes'));
        } catch (\Throwable) {
            $this->flash->error('No se pudo crear el plan. Verifica datos unicos y formato de caracteristicas JSON.');
            return $this->view->response('superadmin/plan-create', [
                'csrfToken' => $csrf->token(),
                'currentPath' => '/admin/planes/crear',
                'input' => $input,
            ]);
        }
    }

    public function planEditarForm(Request $request, CsrfGuard $csrf, string $codigo): Response
    {
        $plan = $this->planRepo->findRawByCodigo($codigo);
        if ($plan === null) {
            $this->flash->error('Plan no encontrado.');
            return Response::redirect('/admin/planes');
        }

        return $this->view->response('superadmin/plan-edit', [
            'csrfToken' => $csrf->token(),
            'currentPath' => $request->path,
            'plan' => $plan,
            'input' => [],
        ]);
    }

    public function planActualizar(Request $request, CsrfGuard $csrf, string $codigo): Response
    {
        $plan = $this->planRepo->findRawByCodigo($codigo);
        if ($plan === null) {
            $this->flash->error('Plan no encontrado.');
            return Response::redirect('/admin/planes');
        }

        $input = $request->post;
        $validation = $this->validarPayloadPlan($input);
        if ($validation['ok'] !== true) {
            $this->flash->error((string) $validation['error']);
            return $this->view->response('superadmin/plan-edit', [
                'csrfToken' => $csrf->token(),
                'currentPath' => '/admin/planes/' . $codigo . '/editar',
                'plan' => array_merge($plan, $input),
                'input' => $input,
            ]);
        }

        $payload = $validation['payload'];
        $codigoNuevo = (string) $payload['codigo'];
        $planId = (string) ($plan['id'] ?? '');

        if ($planId === '') {
            $this->flash->error('No se pudo identificar el plan a actualizar.');
            return Response::redirect('/admin/planes');
        }

        if ($this->planRepo->existsCodigo($codigoNuevo, $planId)) {
            $this->flash->error('Ya existe otro plan con ese codigo.');
            return $this->view->response('superadmin/plan-edit', [
                'csrfToken' => $csrf->token(),
                'currentPath' => '/admin/planes/' . $codigo . '/editar',
                'plan' => array_merge($plan, $input),
                'input' => $input,
            ]);
        }

        $payload['updated_at'] = Clock::nowString();

        try {
            $this->planRepo->updateById($planId, $payload);
            $this->flash->success('Plan actualizado correctamente.');

            $user = $request->attribute('auth_user');
            $this->audit->log(
                evento: 'superadmin.plan.actualizado',
                usuarioId: $user?->id,
                entidadTipo: 'plan',
                entidadId: $planId,
                detalles: [
                    'codigo_anterior' => (string) $plan['codigo'],
                    'codigo_nuevo' => $codigoNuevo,
                    'nombre' => $payload['nombre'],
                ],
                nivel: 'warning',
                ip: $request->clientIp(),
            );

            return Response::redirect($this->securePath($request, '/admin/planes/' . $codigoNuevo . '/editar'));
        } catch (\Throwable) {
            $this->flash->error('No se pudo actualizar el plan. Verifica el formato JSON y la unicidad del codigo.');
            return $this->view->response('superadmin/plan-edit', [
                'csrfToken' => $csrf->token(),
                'currentPath' => '/admin/planes/' . $codigo . '/editar',
                'plan' => array_merge($plan, $input),
                'input' => $input,
            ]);
        }
    }

    public function planCambiarEstado(Request $request, CsrfGuard $csrf, string $codigo): Response
    {
        $plan = $this->planRepo->findRawByCodigo($codigo);
        if ($plan === null) {
            $this->flash->error('Plan no encontrado.');
            return Response::redirect('/admin/planes');
        }

        $nuevoEstado = trim((string) $request->post('estado', ''));
        if (!in_array($nuevoEstado, ['activo', 'inactivo'], true)) {
            $this->flash->error('Estado invalido para el plan.');
            return Response::redirect($this->securePath($request, '/admin/planes'));
        }

        $activo = $nuevoEstado === 'activo';
        $this->planRepo->cambiarEstado((string) $plan['codigo'], $activo, Clock::nowString());

        $user = $request->attribute('auth_user');
        $this->audit->log(
            evento: 'superadmin.plan.estado_cambiado',
            usuarioId: $user?->id,
            entidadTipo: 'plan',
            entidadId: (string) ($plan['id'] ?? $plan['codigo']),
            detalles: [
                'codigo' => (string) $plan['codigo'],
                'estado_nuevo' => $nuevoEstado,
            ],
            nivel: 'warning',
            ip: $request->clientIp(),
        );

        $this->flash->success('Estado del plan actualizado correctamente.');
        return Response::redirect($this->securePath($request, '/admin/planes'));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function renderTenantCreateView(
        CsrfGuard $csrf,
        string $formPath,
        string $volverUrl,
        string $volverLabel,
        array $input,
    ): Response {
        return $this->view->response('superadmin/tenant-create', [
            'csrfToken' => $csrf->token(),
            'currentPath' => $formPath,
            'planes' => $this->planRepo->listarActivos(false),
            'input' => $input,
            'formAction' => $formPath,
            'volverUrl' => $volverUrl,
            'volverLabel' => $volverLabel,
        ]);
    }

    private function tenantCreateFormPath(): string
    {
        return '/admin/tenants/crear';
    }

    private function tenantCreateBackPath(): string
    {
        return '/admin/tenants';
    }

    private function tenantCreateBackLabel(): string
    {
        return 'Volver al listado';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok:true,payload:array<string, scalar|null>}|array{ok:false,error:string}
     */
    private function validarPayloadPlan(array $input): array
    {
        $codigo = mb_strtolower(trim((string) ($input['codigo'] ?? '')), 'UTF-8');
        $codigo = preg_replace('/\s+/', '-', $codigo) ?? $codigo;
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $descripcion = trim((string) ($input['descripcion'] ?? ''));
        $nivel = max(0, min(255, (int) ($input['nivel'] ?? 0)));
        $precioMensual = max(0, round((float) ($input['precio_mensual_usd'] ?? 0), 2));
        $precioAnual = max(0, round((float) ($input['precio_anual_usd'] ?? 0), 2));
        $maxUsuarios = max(1, (int) ($input['max_usuarios'] ?? 1));
        $maxGuardias = max(1, (int) ($input['max_guardias'] ?? 1));
        $maxStorage = max(128, (int) ($input['max_storage_mb'] ?? 128));
        $orden = max(0, (int) ($input['orden'] ?? 0));
        $esPublico = ((string) ($input['es_publico'] ?? '0')) === '1' ? 1 : 0;
        $activo = ((string) ($input['activo'] ?? '0')) === '1' ? 1 : 0;

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/', $codigo)) {
            return ['ok' => false, 'error' => 'El codigo del plan es invalido (usa solo minusculas, numeros, guion o guion bajo).'];
        }
        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') < 3) {
            return ['ok' => false, 'error' => 'El nombre del plan es obligatorio (minimo 3 caracteres).'];
        }

        $caracteristicasRaw = trim((string) ($input['caracteristicas_json'] ?? ''));
        if ($caracteristicasRaw === '') {
            $caracteristicasRaw = '{}';
        }
        $caracteristicas = json_decode($caracteristicasRaw, true);
        if (!is_array($caracteristicas)) {
            return ['ok' => false, 'error' => 'Caracteristicas JSON invalido. Usa un objeto JSON valido.'];
        }
        $caracteristicasJson = json_encode($caracteristicas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($caracteristicasJson === false) {
            return ['ok' => false, 'error' => 'No se pudo serializar el JSON de caracteristicas.'];
        }

        return [
            'ok' => true,
            'payload' => [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'nivel' => $nivel,
                'precio_mensual_usd' => number_format($precioMensual, 2, '.', ''),
                'precio_anual_usd' => number_format($precioAnual, 2, '.', ''),
                'max_usuarios' => $maxUsuarios,
                'max_guardias' => $maxGuardias,
                'max_storage_mb' => $maxStorage,
                'caracteristicas' => $caracteristicasJson,
                'es_publico' => $esPublico,
                'activo' => $activo,
                'orden' => $orden,
            ],
        ];
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
