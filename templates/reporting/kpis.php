<?php
/**
 * Dashboard de KPIs con gráficos Chart.js 4.
 *
 * Variables: $user, $csrfToken, $currentPath, $kpi
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$totales = $kpi['totales'];
?>

<?php $view->section('title') ?>KPIs — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">KPIs y Reportes</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-graph-up me-2"></i>KPIs y Reportes</h4>
        <p class="text-muted mb-0">Indicadores clave de rendimiento del sistema</p>
    </div>
    <a href="<?= $view->url('/reportes/exportar') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-file-earmark-arrow-down me-1"></i>Exportar
    </a>
</div>

<!-- Totales -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-primary border-4">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold text-primary"><?= $totales['fichas'] ?></div>
                <div class="text-muted small text-uppercase">Total Fichas</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-info border-4">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold text-info"><?= $totales['expedientes'] ?></div>
                <div class="text-muted small text-uppercase">Total Expedientes</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-success border-4">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold text-success"><?= $totales['docs_aceptados'] ?></div>
                <div class="text-muted small text-uppercase">Docs Aceptados</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-secondary border-4">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold"><?= $totales['docs_subidos'] ?></div>
                <div class="text-muted small text-uppercase">Docs Subidos</div>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos fila 1 -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Fichas por Estado</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartFichasEstado" style="max-height:250px"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Fichas por Tipo</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartFichasTipo" style="max-height:250px"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Expedientes por Estado</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartExpedientes" style="max-height:250px"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos fila 2 -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Fichas Creadas por Mes (últimos 12 meses)</div>
            <div class="card-body">
                <canvas id="chartFichasMes" style="max-height:300px"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Documentos por Estado</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="chartDocsEstado" style="max-height:250px"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Gráfico vencimientos -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header"><i class="bi bi-calendar-event me-2"></i>Documentos por Vencer (próximos 90 días)</div>
            <div class="card-body">
                <canvas id="chartVencimientos" style="max-height:250px"></canvas>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const DS = {
    primary: '#1a3a5c', accent: '#2e86de', success: '#27ae60',
    warning: '#f39c12', danger: '#e74c3c', info: '#3498db',
    secondary: '#6c757d', dark: '#343a40',
};

// -- Fichas por Estado (Doughnut) --
new Chart(document.getElementById('chartFichasEstado'), {
    type: 'doughnut',
    data: {
        labels: ['Borrador', 'En revisión', 'Completa', 'Anulada'],
        datasets: [{
            data: [
                <?= (int)($kpi['fichas_por_estado']['BORRADOR'] ?? 0) ?>,
                <?= (int)($kpi['fichas_por_estado']['EN_REVISION'] ?? 0) ?>,
                <?= (int)($kpi['fichas_por_estado']['COMPLETA'] ?? 0) ?>,
                <?= (int)($kpi['fichas_por_estado']['ANULADA'] ?? 0) ?>
            ],
            backgroundColor: [DS.secondary, DS.warning, DS.success, DS.danger],
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// -- Fichas por Tipo (Doughnut) --
new Chart(document.getElementById('chartFichasTipo'), {
    type: 'doughnut',
    data: {
        labels: ['Ingreso', 'Reingreso', 'Actualización'],
        datasets: [{
            data: [
                <?= (int)($kpi['fichas_por_tipo']['INGRESO'] ?? 0) ?>,
                <?= (int)($kpi['fichas_por_tipo']['REINGRESO'] ?? 0) ?>,
                <?= (int)($kpi['fichas_por_tipo']['ACTUALIZACION'] ?? 0) ?>
            ],
            backgroundColor: [DS.accent, DS.info, DS.primary],
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// -- Expedientes por Estado (Doughnut) --
new Chart(document.getElementById('chartExpedientes'), {
    type: 'doughnut',
    data: {
        labels: ['Incompleto', 'Bloque A completo', 'Completo'],
        datasets: [{
            data: [
                <?= (int)($kpi['expedientes_por_estado']['INCOMPLETO'] ?? 0) ?>,
                <?= (int)($kpi['expedientes_por_estado']['BLOQUE_A_COMPLETO'] ?? 0) ?>,
                <?= (int)($kpi['expedientes_por_estado']['COMPLETO'] ?? 0) ?>
            ],
            backgroundColor: [DS.secondary, DS.info, DS.success],
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// -- Documentos por Estado (Doughnut) --
new Chart(document.getElementById('chartDocsEstado'), {
    type: 'doughnut',
    data: {
        labels: ['Pendiente', 'En revisión', 'Aceptado', 'Rechazado', 'Vencido'],
        datasets: [{
            data: [
                <?= (int)($kpi['docs_por_estado']['PENDIENTE'] ?? 0) ?>,
                <?= (int)($kpi['docs_por_estado']['EN_REVISION'] ?? 0) ?>,
                <?= (int)($kpi['docs_por_estado']['ACEPTADO'] ?? 0) ?>,
                <?= (int)($kpi['docs_por_estado']['RECHAZADO'] ?? 0) ?>,
                <?= (int)($kpi['docs_por_estado']['VENCIDO'] ?? 0) ?>
            ],
            backgroundColor: [DS.secondary, DS.warning, DS.success, DS.danger, DS.dark],
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

// -- Fichas por Mes (Bar) --
const fichasMes = <?= json_encode($kpi['fichas_por_mes'], JSON_HEX_TAG) ?>;
new Chart(document.getElementById('chartFichasMes'), {
    type: 'bar',
    data: {
        labels: fichasMes.map(r => r.mes),
        datasets: [{
            label: 'Fichas creadas',
            data: fichasMes.map(r => r.total),
            backgroundColor: DS.accent + '99',
            borderColor: DS.accent,
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// -- Vencimientos próximos (Bar) --
const vencData = <?= json_encode($kpi['docs_vencimiento_proximos'], JSON_HEX_TAG) ?>;
new Chart(document.getElementById('chartVencimientos'), {
    type: 'bar',
    data: {
        labels: vencData.map(r => 'Sem. ' + r.semana),
        datasets: [{
            label: 'Documentos por vencer',
            data: vencData.map(r => r.total),
            backgroundColor: DS.warning + '99',
            borderColor: DS.warning,
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});
</script>
<?php $view->endSection() ?>
