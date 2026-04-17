<?php
/**
 * Dashboard global del superadmin (Fase 13).
 *
 * Variables: $user, $csrfToken, $currentPath, $stats, $tenantsPorPlan,
 *            $registrosPorDia, $registrosRecientes
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
/** @var \DigiSecure\Security\Auth\AuthenticatedUser $user */
/** @var array<string, int> $stats */
/** @var list<array{plan_codigo: string, cantidad: int}> $tenantsPorPlan */
/** @var list<array{dia: string, cantidad: int}> $registrosPorDia */
/** @var list<array<string, mixed>> $registrosRecientes */

$view->layout('layout/app');
$e = ViewRenderer::e(...);

$totalTenants = $stats['total_tenants'] ?? 0;
$activos = $stats['tenants_activos'] ?? 0;
$pendientes = $stats['tenants_pendientes'] ?? 0;
$suspendidos = $stats['tenants_suspendidos'] ?? 0;
$totalUsuarios = $stats['total_usuarios'] ?? 0;
$usuariosActivos = $stats['usuarios_activos'] ?? 0;
$totalFichas = $stats['total_fichas'] ?? 0;
$totalRegistros = $stats['total_registros'] ?? 0;

$eventoBadge = [
    'creado'    => 'bg-primary',
    'activado'  => 'bg-success',
    'expirado'  => 'bg-warning text-dark',
    'reenviado' => 'bg-info text-dark',
    'fallo'     => 'bg-danger',
];
?>

<?php $view->section('title') ?>Panel Superadmin | DigiSecure<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="mb-4">
    <h4 class="fw-bold mb-1"><i class="bi bi-grid-1x2-fill me-2 text-primary"></i>Panel del Operador</h4>
    <p class="text-muted mb-0">Vista global de la plataforma DigiSecure.</p>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(47,128,237,.12)">
                        <i class="bi bi-buildings-fill text-primary fs-5"></i>
                    </div>
                    <div class="ms-auto text-end">
                        <div class="fs-3 fw-bold"><?= $totalTenants ?></div>
                        <small class="text-muted">Empresas</small>
                    </div>
                </div>
                <div class="d-flex gap-2 small">
                    <span class="badge bg-success-subtle text-success"><?= $activos ?> activas</span>
                    <span class="badge bg-warning-subtle text-warning"><?= $pendientes ?> pendientes</span>
                    <?php if ($suspendidos > 0): ?>
                        <span class="badge bg-danger-subtle text-danger"><?= $suspendidos ?> susp.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(14,165,164,.12)">
                        <i class="bi bi-people-fill fs-5" style="color:#0ea5a4"></i>
                    </div>
                    <div class="ms-auto text-end">
                        <div class="fs-3 fw-bold"><?= $totalUsuarios ?></div>
                        <small class="text-muted">Usuarios</small>
                    </div>
                </div>
                <span class="badge bg-success-subtle text-success small"><?= $usuariosActivos ?> activos</span>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(39,174,96,.12)">
                        <i class="bi bi-person-badge-fill text-success fs-5"></i>
                    </div>
                    <div class="ms-auto text-end">
                        <div class="fs-3 fw-bold"><?= $totalFichas ?></div>
                        <small class="text-muted">Fichas de guardias</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px;background:rgba(242,153,74,.12)">
                        <i class="bi bi-rocket-takeoff-fill fs-5" style="color:#f2994a"></i>
                    </div>
                    <div class="ms-auto text-end">
                        <div class="fs-3 fw-bold"><?= $totalRegistros ?></div>
                        <small class="text-muted">Registros públicos</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts row -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-pie-chart-fill text-primary me-1"></i>Empresas por plan</h6>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center" style="min-height:260px">
                <canvas id="chartPlan" width="260" height="260"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pb-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-graph-up-arrow text-success me-1"></i>Registros últimos 30 días</h6>
            </div>
            <div class="card-body" style="min-height:260px">
                <canvas id="chartRegistros"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Registros recientes -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
        <h6 class="fw-semibold mb-0"><i class="bi bi-clock-history text-info me-1"></i>Registros recientes</h6>
        <a href="<?= $view->url('/admin/registros') ?>" class="btn btn-sm btn-outline-primary">Ver todos</a>
    </div>
    <div class="card-body p-0">
        <?php if ($registrosRecientes === []): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                Aún no hay registros de empresa.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Fecha</th><th>Empresa</th><th>RUC</th><th>Email</th><th>Evento</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrosRecientes as $r): ?>
                            <tr>
                                <td class="text-muted small"><?= $e(substr((string) $r['created_at'], 0, 16)) ?></td>
                                <td class="fw-semibold"><?= $e((string) $r['razon_social']) ?></td>
                                <td class="font-monospace small"><?= $e((string) $r['ruc']) ?></td>
                                <td class="small"><?= $e((string) $r['email_admin']) ?></td>
                                <td><span class="badge <?= $eventoBadge[(string) $r['evento']] ?? 'bg-secondary' ?>"><?= $e((string) $r['evento']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick links -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="<?= $view->url('/admin/tenants') ?>" class="card border-0 shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-buildings fs-2 text-primary d-block mb-2"></i>
                <div class="fw-semibold">Gestionar empresas</div>
                <small class="text-muted">Listado, filtros y acciones</small>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= $view->url('/admin/registros') ?>" class="card border-0 shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-journal-text fs-2 text-info d-block mb-2"></i>
                <div class="fw-semibold">Log de registros</div>
                <small class="text-muted">Intentos de auto-registro</small>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= $view->url('/auditoria') ?>" class="card border-0 shadow-sm text-decoration-none h-100">
            <div class="card-body text-center py-4">
                <i class="bi bi-shield-exclamation fs-2 text-warning d-block mb-2"></i>
                <div class="fw-semibold">Auditoría global</div>
                <small class="text-muted">Eventos del sistema</small>
            </div>
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function(){
    const colors = ['#2f80ed','#0ea5a4','#27ae60','#f2994a','#e74c3c','#9b59b6','#7f8c8d'];

    // Doughnut: tenants por plan
    const planLabels = <?= json_encode(array_map(fn($r) => $r['plan_codigo'], $tenantsPorPlan)) ?>;
    const planData = <?= json_encode(array_map(fn($r) => (int)$r['cantidad'], $tenantsPorPlan)) ?>;
    new Chart(document.getElementById('chartPlan'), {
        type: 'doughnut',
        data: {
            labels: planLabels,
            datasets: [{ data: planData, backgroundColor: colors.slice(0, planLabels.length), borderWidth: 2, hoverOffset: 6 }]
        },
        options: { responsive: true, maintainAspectRatio: true, cutout: '58%', plugins: { legend: { position:'bottom', labels:{padding:14} } } }
    });

    // Bar: registros por día
    const regLabels = <?= json_encode(array_map(fn($r) => $r['dia'], $registrosPorDia)) ?>;
    const regData = <?= json_encode(array_map(fn($r) => (int)$r['cantidad'], $registrosPorDia)) ?>;
    new Chart(document.getElementById('chartRegistros'), {
        type: 'bar',
        data: {
            labels: regLabels.map(d => { const p = d.split('-'); return p[2]+'/'+p[1]; }),
            datasets: [{ label:'Registros', data: regData, backgroundColor:'rgba(47,128,237,.6)', borderRadius:4, barPercentage:.7 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend:{ display:false } },
            scales: { y:{ beginAtZero:true, ticks:{ stepSize:1, precision:0 } }, x:{ grid:{ display:false } } }
        }
    });
})();
</script>
<?php $view->endSection() ?>
