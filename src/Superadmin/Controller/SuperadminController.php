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
use DigiSecure\Subscription\Infrastructure\SuscripcionCicloRepository;
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

    public function tenants(Request $request): Response
    {
        $busqueda = trim((string) $request->query('q', '')) ?: null;
        $estado = trim((string) $request->query('estado', '')) ?: null;
        $plan = trim((string) $request->query('plan', '')) ?: null;
        $pagina = max(1, (int) $request->query('pagina', '1'));

        $resultado = $this->repo->listarTenants($busqueda, $estado, $plan, $pagina, 20);
        $planes = $this->planRepo->listarActivos(false);

        return $this->view->response('superadmin/tenants', [
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

    public function tenantDetalle(Request $request, string $id): Response
    {
        $tenant = $this->repo->findTenant($id);
        if ($tenant === null) {
            return Response::html('<h1>Tenant no encontrado</h1>', 404);
        }

        $uso = $this->repo->usoTenant($id);
        $usuarios = $this->repo->usuariosTenant($id);
        $ciclos = $this->repo->ciclosTenant($id);
        $registros = $this->repo->registrosTenant($id);
        $planes = $this->planRepo->listarActivos(false);

        return $this->view->response('superadmin/tenant-detalle', [
            'tenant' => $tenant,
            'uso' => $uso,
            'usuarios' => $usuarios,
            'ciclos' => $ciclos,
            'registros' => $registros,
            'planes' => $planes,
        ]);
    }

    // ============================ Cambiar estado ============================

    public function cambiarEstado(Request $request, CsrfGuard $csrf, string $id): Response
    {
        $nuevoEstado = trim((string) $request->post('estado', ''));
        $estadosValidos = ['activo', 'suspendido', 'cancelado', 'pendiente'];

        if (!in_array($nuevoEstado, $estadosValidos, true)) {
            $this->flash->error('Estado invalido.', 'No se pudo actualizar el tenant');
            return Response::redirect("/admin/tenants/{$id}");
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

        return Response::redirect("/admin/tenants/{$id}");
    }

    // ============================ Cambiar plan ============================

    public function cambiarPlan(Request $request, CsrfGuard $csrf, string $id): Response
    {
        $planCodigo = trim((string) $request->post('plan_codigo', ''));
        $meses = max(1, (int) $request->post('meses', '1'));

        $plan = $this->planRepo->findByCodigo($planCodigo);
        if ($plan === null || !$plan->activo) {
            $this->flash->error('El plan seleccionado no existe o esta inactivo.');
            return Response::redirect("/admin/tenants/{$id}");
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

        return Response::redirect("/admin/tenants/{$id}");
    }

    // ============================ Log de registros ============================

    public function registros(Request $request): Response
    {
        $registros = $this->repo->registrosRecientes(50);

        return $this->view->response('superadmin/registros', [
            'registros' => $registros,
        ]);
    }
}
