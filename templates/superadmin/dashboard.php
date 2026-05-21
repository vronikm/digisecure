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

$eventoTone = [
    'creado'    => 'tone-info',
    'activado'  => 'tone-success',
    'expirado'  => 'tone-warning',
    'reenviado' => 'tone-info',
    'fallo'     => 'tone-danger',
];
?>

<?php $view->section('title') ?>Panel Superadmin | DigiSecure<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-grid-1x2-fill"></i></span>
        <div>
            <h1>Panel del operador</h1>
            <small>Vista global de la plataforma DigiSecure</small>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="ds-kpi-grid ds-kpi-grid-solid">
    <div class="ds-kpi-card">
        <span class="ds-kpi-icon"><i class="bi bi-buildings-fill"></i></span>
        <div style="flex: 1;">
            <div class="ds-kpi-label">Empresas</div>
            <div class="ds-kpi-value"><?= $totalTenants ?></div>
            <div style="display: flex; gap: .25rem; flex-wrap: wrap; margin-top: .35rem;">
                <span class="ds-badge tone-success no-dot" style="font-size: .65rem;"><?= $activos ?> activas</span>
                <?php if ($pendientes > 0): ?>
                    <span class="ds-badge tone-warning no-dot" style="font-size: .65rem;"><?= $pendientes ?> pendientes</span>
                <?php endif ?>
                <?php if ($suspendidos > 0): ?>
                    <span class="ds-badge tone-danger no-dot" style="font-size: .65rem;"><?= $suspendidos ?> susp.</span>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-people-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Usuarios</div>
            <div class="ds-kpi-value"><?= $totalUsuarios ?></div>
            <div class="ds-cell-sub" style="margin-top: .25rem;"><?= $usuariosActivos ?> activos</div>
        </div>
    </div>
    <div class="ds-kpi-card tone-purple">
        <span class="ds-kpi-icon"><i class="bi bi-person-badge-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Fichas de guardias</div>
            <div class="ds-kpi-value"><?= $totalFichas ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-rocket-takeoff-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Registros publicos</div>
            <div class="ds-kpi-value"><?= $totalRegistros ?></div>
        </div>
    </div>
</div>

<!-- Charts row -->
<div class="row g-4" style="margin-bottom: 0;">
    <div class="col-lg-5">
        <div class="ds-card" style="margin-bottom: 1.25rem;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-pie-chart-fill"></i></span>
                <div>
                    <div class="ds-card-title">Empresas por plan</div>
                    <div class="ds-card-subtitle">Distribucion del catalogo</div>
                </div>
            </div>
            <div class="ds-card-body" style="display: flex; align-items: center; justify-content: center; min-height: 280px;">
                <canvas id="chartPlan" width="260" height="260"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="ds-card" style="margin-bottom: 1.25rem;">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-graph-up-arrow"></i></span>
                <div>
                    <div class="ds-card-title">Registros ultimos 30 dias</div>
                    <div class="ds-card-subtitle">Intentos de auto-registro por dia</div>
                </div>
            </div>
            <div class="ds-card-body" style="min-height: 280px;">
                <canvas id="chartRegistros"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Registros recientes -->
<div class="ds-card">
    <div class="ds-card-header">
        <span class="ds-card-mark"><i class="bi bi-clock-history"></i></span>
        <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
            <div>
                <div class="ds-card-title">Registros recientes</div>
                <div class="ds-card-subtitle">Ultimos intentos desde el portal publico</div>
            </div>
            <a href="<?= $view->url('/admin/registros') ?>" class="ds-btn ds-btn-outline ds-btn-sm">
                Ver todos<i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php if ($registrosRecientes === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state" style="padding: 1.75rem 1rem;">
            <div class="ds-empty-icon"><i class="bi bi-inbox"></i></div>
            <p>Aun no hay registros de empresa.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Empresa</th>
                    <th>RUC</th>
                    <th>Email</th>
                    <th>Evento</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registrosRecientes as $r):
                    $tone = $eventoTone[(string) $r['evento']] ?? 'tone-muted';
                ?>
                <tr>
                    <td><span class="ds-cell-mono"><?= $e(substr((string) $r['created_at'], 0, 16)) ?></span></td>
                    <td><div class="ds-cell-name"><?= $e((string) $r['razon_social']) ?></div></td>
                    <td><span class="ds-cell-mono"><?= $e((string) $r['ruc']) ?></span></td>
                    <td><span class="ds-cell-mono"><?= $e((string) $r['email_admin']) ?></span></td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e((string) $r['evento']) ?></span></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>

<!-- Quick links -->
<div class="row g-4" style="margin-top: .25rem;">
    <div class="col-md-4">
        <a href="<?= $view->url('/admin/tenants') ?>" class="ds-card" style="text-decoration: none; display: block; height: 100%; transition: transform .15s, box-shadow .15s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 12px 30px rgba(9,29,53,.12)';"
           onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div class="ds-card-body" style="text-align: center; padding: 1.75rem 1.25rem;">
                <span style="width: 56px; height: 56px; display: inline-flex; align-items: center; justify-content: center; border-radius: 16px; background: rgba(47,128,237,.12); color: #2f80ed; font-size: 1.6rem; margin-bottom: .75rem;">
                    <i class="bi bi-buildings"></i>
                </span>
                <div style="font-weight: 700; color: var(--ds-text); font-size: 1rem;">Gestionar empresas</div>
                <div class="ds-cell-sub" style="margin-top: .25rem;">Listado, filtros y acciones</div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= $view->url('/admin/registros') ?>" class="ds-card" style="text-decoration: none; display: block; height: 100%; transition: transform .15s, box-shadow .15s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 12px 30px rgba(9,29,53,.12)';"
           onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div class="ds-card-body" style="text-align: center; padding: 1.75rem 1.25rem;">
                <span style="width: 56px; height: 56px; display: inline-flex; align-items: center; justify-content: center; border-radius: 16px; background: rgba(37,99,235,.12); color: #2563eb; font-size: 1.6rem; margin-bottom: .75rem;">
                    <i class="bi bi-journal-text"></i>
                </span>
                <div style="font-weight: 700; color: var(--ds-text); font-size: 1rem;">Log de registros</div>
                <div class="ds-cell-sub" style="margin-top: .25rem;">Intentos de auto-registro</div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= $view->url('/auditoria') ?>" class="ds-card" style="text-decoration: none; display: block; height: 100%; transition: transform .15s, box-shadow .15s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 12px 30px rgba(9,29,53,.12)';"
           onmouseout="this.style.transform='';this.style.boxShadow='';">
            <div class="ds-card-body" style="text-align: center; padding: 1.75rem 1.25rem;">
                <span style="width: 56px; height: 56px; display: inline-flex; align-items: center; justify-content: center; border-radius: 16px; background: rgba(217,119,6,.14); color: #d97706; font-size: 1.6rem; margin-bottom: .75rem;">
                    <i class="bi bi-shield-exclamation"></i>
                </span>
                <div style="font-weight: 700; color: var(--ds-text); font-size: 1rem;">Auditoria global</div>
                <div class="ds-cell-sub" style="margin-top: .25rem;">Eventos del sistema</div>
            </div>
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    const colors = ['#2f80ed', '#0ea5a4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'];

    const planLabels = <?= json_encode(array_map(fn($r) => $r['plan_codigo'], $tenantsPorPlan)) ?>;
    const planData = <?= json_encode(array_map(fn($r) => (int) $r['cantidad'], $tenantsPorPlan)) ?>;
    new Chart(document.getElementById('chartPlan'), {
        type: 'doughnut',
        data: {
            labels: planLabels,
            datasets: [{ data: planData, backgroundColor: colors.slice(0, planLabels.length), borderWidth: 2, hoverOffset: 6 }],
        },
        options: { responsive: true, maintainAspectRatio: true, cutout: '58%', plugins: { legend: { position: 'bottom', labels: { padding: 14 } } } },
    });

    const regLabels = <?= json_encode(array_map(fn($r) => $r['dia'], $registrosPorDia)) ?>;
    const regData = <?= json_encode(array_map(fn($r) => (int) $r['cantidad'], $registrosPorDia)) ?>;
    new Chart(document.getElementById('chartRegistros'), {
        type: 'bar',
        data: {
            labels: regLabels.map(d => { const p = d.split('-'); return p[2] + '/' + p[1]; }),
            datasets: [{ label: 'Registros', data: regData, backgroundColor: 'rgba(47,128,237,.6)', borderRadius: 6, barPercentage: .7 }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }, x: { grid: { display: false } } },
        },
    });
})();
</script>
<?php $view->endSection() ?>
