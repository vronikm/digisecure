<?php
/**
 * Dashboard de KPIs con lectura ejecutiva.
 *
 * Variables: $user, $csrfToken, $currentPath, $kpi
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$totales = $kpi['totales'];
$fichasTotal = (int) ($totales['fichas'] ?? 0);
$expedientesTotal = (int) ($totales['expedientes'] ?? 0);
$docsSubidos = (int) ($totales['docs_subidos'] ?? 0);
$docsAceptados = (int) ($totales['docs_aceptados'] ?? 0);

$fichasCompletas = (int) ($kpi['fichas_por_estado']['COMPLETA'] ?? 0);
$expedientesCompletos = (int) ($kpi['expedientes_por_estado']['COMPLETO'] ?? 0);
$docsPendientes = (int) ($kpi['docs_por_estado']['PENDIENTE'] ?? 0);
$docsRevision = (int) ($kpi['docs_por_estado']['EN_REVISION'] ?? 0);
$docsRechazados = (int) ($kpi['docs_por_estado']['RECHAZADO'] ?? 0);
$docsVencidos = (int) ($kpi['docs_por_estado']['VENCIDO'] ?? 0);
$docsProximos = array_sum(array_map(static fn(array $row): int => (int) ($row['total'] ?? 0), $kpi['docs_vencimiento_proximos'] ?? []));

$docsAceptadosPct = $docsSubidos > 0 ? (int) round(($docsAceptados / $docsSubidos) * 100) : 0;
$fichasCompletasPct = $fichasTotal > 0 ? (int) round(($fichasCompletas / $fichasTotal) * 100) : 0;
$expedientesCompletosPct = $expedientesTotal > 0 ? (int) round(($expedientesCompletos / $expedientesTotal) * 100) : 0;
?>

<?php $view->section('title') ?>KPIs | DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-report-hero {
        overflow: hidden;
        border: 0;
        border-radius: 28px;
        background:
            radial-gradient(circle at top right, rgba(14, 165, 164, 0.18), transparent 34%),
            linear-gradient(135deg, #15304f, #1b4b72);
        color: #fff;
        box-shadow: 0 18px 34px rgba(18, 45, 77, 0.16);
    }

    .ds-report-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.9rem;
        padding: 0.38rem 0.72rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .ds-report-hero p {
        color: rgba(255, 255, 255, 0.76);
    }

    .ds-insight-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.85rem;
        margin-top: 1.5rem;
    }

    .ds-insight-card {
        padding: 1rem;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .ds-insight-card strong {
        display: block;
        font-size: 1.7rem;
        line-height: 1;
    }

    .ds-summary-card,
    .ds-chart-card {
        border: 0;
        border-radius: 24px;
        box-shadow: 0 16px 30px rgba(18, 45, 77, 0.08);
    }

    .ds-summary-card .card-body,
    .ds-chart-card .card-body {
        padding: 1.15rem;
    }

    .ds-summary-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .ds-summary-icon {
        width: 48px;
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        font-size: 1.15rem;
    }

    .ds-summary-note {
        margin-top: 0.6rem;
        color: #607489;
        font-size: 0.83rem;
    }

    .ds-chart-card .card-header,
    .ds-focus-card .card-header {
        padding: 1rem 1.15rem 0;
        background: transparent;
        border: 0;
    }

    .ds-chart-card .card-header h2,
    .ds-focus-card .card-header h2 {
        font-size: 1.05rem;
        margin: 0;
    }

    .ds-focus-card {
        border: 0;
        border-radius: 24px;
        background: linear-gradient(180deg, rgba(14, 165, 164, 0.08), #fff);
        box-shadow: 0 16px 30px rgba(18, 45, 77, 0.08);
    }

    .ds-focus-metric + .ds-focus-metric {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(21, 48, 79, 0.08);
    }

    .ds-focus-metric .progress {
        height: 10px;
        margin-top: 0.55rem;
        border-radius: 999px;
        background: rgba(21, 48, 79, 0.08);
    }

    .ds-focus-list {
        display: grid;
        gap: 0.7rem;
        margin-top: 1rem;
    }

    .ds-focus-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.7rem 0.8rem;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.8);
    }

    .ds-canvas-wrap {
        position: relative;
        min-height: 300px;
    }

    .ds-canvas-wrap--sm {
        min-height: 250px;
    }

    @media (max-width: 991.98px) {
        .ds-insight-strip {
            grid-template-columns: 1fr;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item active">KPIs y Reportes</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<section class="card ds-report-hero mb-4">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
            <div>
                <span class="ds-report-eyebrow"><i class="bi bi-bar-chart-line"></i>Tablero ejecutivo</span>
                <h1 class="h3 mb-3">KPIs con lectura clara para operacion y cumplimiento.</h1>
                <p class="mb-0">
                    Este panel prioriza avance documental, estado de fichas y proximos vencimientos
                    para que la toma de decisiones sea mas rapida.
                </p>
            </div>
            <div class="d-flex flex-column align-items-lg-end gap-2">
                <span class="badge text-bg-light px-3 py-2">Actualizacion con datos del sistema</span>
                <a href="<?= $view->url('/reportes/exportar') ?>" class="btn btn-light">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Exportar reporte
                </a>
            </div>
        </div>

        <div class="ds-insight-strip">
            <div class="ds-insight-card">
                <span class="small text-white-50">Cumplimiento documental</span>
                <strong><?= $docsAceptadosPct ?>%</strong>
                <span class="small text-white-50"><?= $docsAceptados ?> aceptados de <?= $docsSubidos ?> cargados</span>
            </div>
            <div class="ds-insight-card">
                <span class="small text-white-50">Fichas completas</span>
                <strong><?= $fichasCompletasPct ?>%</strong>
                <span class="small text-white-50"><?= $fichasCompletas ?> completas del total registrado</span>
            </div>
            <div class="ds-insight-card">
                <span class="small text-white-50">Expedientes completos</span>
                <strong><?= $expedientesCompletosPct ?>%</strong>
                <span class="small text-white-50"><?= $expedientesCompletos ?> expedientes listos para operar</span>
            </div>
        </div>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <section class="card ds-summary-card">
            <div class="card-body">
                <div class="ds-summary-top">
                    <div>
                        <div class="text-uppercase small fw-bold text-primary">Total fichas</div>
                        <div class="display-6 fw-semibold mb-0"><?= $fichasTotal ?></div>
                    </div>
                    <span class="ds-summary-icon" style="background: rgba(47, 128, 237, 0.12); color: #2f80ed;">
                        <i class="bi bi-person-vcard"></i>
                    </span>
                </div>
                <div class="ds-summary-note">Base actual de personal y movimientos de ficha.</div>
            </div>
        </section>
    </div>
    <div class="col-sm-6 col-xl-3">
        <section class="card ds-summary-card">
            <div class="card-body">
                <div class="ds-summary-top">
                    <div>
                        <div class="text-uppercase small fw-bold text-info">Expedientes</div>
                        <div class="display-6 fw-semibold mb-0"><?= $expedientesTotal ?></div>
                    </div>
                    <span class="ds-summary-icon" style="background: rgba(52, 152, 219, 0.12); color: #3498db;">
                        <i class="bi bi-folder2-open"></i>
                    </span>
                </div>
                <div class="ds-summary-note">Muestra el universo documental gestionado por la empresa.</div>
            </div>
        </section>
    </div>
    <div class="col-sm-6 col-xl-3">
        <section class="card ds-summary-card">
            <div class="card-body">
                <div class="ds-summary-top">
                    <div>
                        <div class="text-uppercase small fw-bold text-success">Docs aceptados</div>
                        <div class="display-6 fw-semibold mb-0"><?= $docsAceptados ?></div>
                    </div>
                    <span class="ds-summary-icon" style="background: rgba(39, 174, 96, 0.12); color: #27ae60;">
                        <i class="bi bi-check2-circle"></i>
                    </span>
                </div>
                <div class="ds-summary-note">Carga ya validada y util para cumplimiento.</div>
            </div>
        </section>
    </div>
    <div class="col-sm-6 col-xl-3">
        <section class="card ds-summary-card">
            <div class="card-body">
                <div class="ds-summary-top">
                    <div>
                        <div class="text-uppercase small fw-bold text-warning">Por atender</div>
                        <div class="display-6 fw-semibold mb-0"><?= $docsPendientes + $docsRevision + $docsRechazados + $docsVencidos ?></div>
                    </div>
                    <span class="ds-summary-icon" style="background: rgba(242, 153, 74, 0.14); color: #f2994a;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </span>
                </div>
                <div class="ds-summary-note">Pendientes, en revision, rechazados o vencidos.</div>
            </div>
        </section>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <section class="card ds-chart-card h-100">
            <div class="card-header">
                <h2><i class="bi bi-bar-chart me-2 text-primary"></i>Fichas creadas por mes</h2>
                <p class="text-muted small mb-0">Ultimos 12 meses para ver ritmo de altas y actualizaciones.</p>
            </div>
            <div class="card-body">
                <div class="ds-canvas-wrap">
                    <canvas id="chartFichasMes"></canvas>
                </div>
            </div>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="card ds-focus-card h-100">
            <div class="card-header">
                <h2><i class="bi bi-lightning-charge me-2 text-warning"></i>Lectura rapida</h2>
                <p class="text-muted small mb-0">Sintesis de salud operacional y cumplimiento.</p>
            </div>
            <div class="card-body">
                <div class="ds-focus-metric">
                    <div class="d-flex justify-content-between small">
                        <span>Documentos aceptados</span>
                        <strong><?= $docsAceptadosPct ?>%</strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: <?= $docsAceptadosPct ?>%"></div>
                    </div>
                </div>

                <div class="ds-focus-metric">
                    <div class="d-flex justify-content-between small">
                        <span>Fichas completas</span>
                        <strong><?= $fichasCompletasPct ?>%</strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-primary" style="width: <?= $fichasCompletasPct ?>%"></div>
                    </div>
                </div>

                <div class="ds-focus-metric">
                    <div class="d-flex justify-content-between small">
                        <span>Expedientes completos</span>
                        <strong><?= $expedientesCompletosPct ?>%</strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-info" style="width: <?= $expedientesCompletosPct ?>%"></div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">Proximos vencimientos</strong>
                        <span class="badge text-bg-warning"><?= $docsProximos ?></span>
                    </div>
                    <div class="ds-focus-list">
                        <?php if (($kpi['docs_vencimiento_proximos'] ?? []) === []): ?>
                            <div class="ds-focus-item">
                                <span class="text-muted small">No hay documentos por vencer en el rango analizado.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($kpi['docs_vencimiento_proximos'], 0, 4) as $row): ?>
                                <div class="ds-focus-item">
                                    <span class="small text-muted">Semana <?= $e($row['semana']) ?></span>
                                    <strong><?= (int) ($row['total'] ?? 0) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <section class="card ds-chart-card h-100">
            <div class="card-header">
                <h2><i class="bi bi-pie-chart me-2 text-success"></i>Fichas por estado</h2>
            </div>
            <div class="card-body">
                <div class="ds-canvas-wrap ds-canvas-wrap--sm">
                    <canvas id="chartFichasEstado"></canvas>
                </div>
            </div>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="card ds-chart-card h-100">
            <div class="card-header">
                <h2><i class="bi bi-diagram-3 me-2 text-info"></i>Fichas por tipo</h2>
            </div>
            <div class="card-body">
                <div class="ds-canvas-wrap ds-canvas-wrap--sm">
                    <canvas id="chartFichasTipo"></canvas>
                </div>
            </div>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="card ds-chart-card h-100">
            <div class="card-header">
                <h2><i class="bi bi-folder-check me-2 text-primary"></i>Expedientes por estado</h2>
            </div>
            <div class="card-body">
                <div class="ds-canvas-wrap ds-canvas-wrap--sm">
                    <canvas id="chartExpedientes"></canvas>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <section class="card ds-chart-card h-100">
            <div class="card-header">
                <h2><i class="bi bi-stack me-2 text-warning"></i>Documentos por estado</h2>
            </div>
            <div class="card-body">
                <div class="ds-canvas-wrap ds-canvas-wrap--sm">
                    <canvas id="chartDocsEstado"></canvas>
                </div>
            </div>
        </section>
    </div>
    <div class="col-lg-8">
        <section class="card ds-chart-card h-100">
            <div class="card-header">
                <h2><i class="bi bi-calendar-event me-2 text-danger"></i>Documentos por vencer</h2>
                <p class="text-muted small mb-0">Ventana de 90 dias agrupada por semana para anticipar carga operativa.</p>
            </div>
            <div class="card-body">
                <div class="ds-canvas-wrap">
                    <canvas id="chartVencimientos"></canvas>
                </div>
            </div>
        </section>
    </div>
</div>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(() => {
    const colors = {
        primary: '#2f80ed',
        accent: '#0ea5a4',
        success: '#27ae60',
        warning: '#f39c12',
        danger: '#e74c3c',
        info: '#3498db',
        secondary: '#7f8c8d',
        dark: '#34495e'
    };

    const makeDoughnut = (id, labels, data, palette) => {
        const node = document.getElementById(id);
        if (!node) {
            return;
        }
        new Chart(node, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: palette,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 14
                        }
                    }
                }
            }
        });
    };

    makeDoughnut(
        'chartFichasEstado',
        ['Borrador', 'En revision', 'Completa', 'Anulada'],
        [
            <?= (int) ($kpi['fichas_por_estado']['BORRADOR'] ?? 0) ?>,
            <?= (int) ($kpi['fichas_por_estado']['EN_REVISION'] ?? 0) ?>,
            <?= (int) ($kpi['fichas_por_estado']['COMPLETA'] ?? 0) ?>,
            <?= (int) ($kpi['fichas_por_estado']['ANULADA'] ?? 0) ?>
        ],
        [colors.secondary, colors.warning, colors.success, colors.danger]
    );

    makeDoughnut(
        'chartFichasTipo',
        ['Ingreso', 'Reingreso', 'Actualizacion'],
        [
            <?= (int) ($kpi['fichas_por_tipo']['INGRESO'] ?? 0) ?>,
            <?= (int) ($kpi['fichas_por_tipo']['REINGRESO'] ?? 0) ?>,
            <?= (int) ($kpi['fichas_por_tipo']['ACTUALIZACION'] ?? 0) ?>
        ],
        [colors.primary, colors.info, colors.accent]
    );

    makeDoughnut(
        'chartExpedientes',
        ['Incompleto', 'Bloque A completo', 'Completo'],
        [
            <?= (int) ($kpi['expedientes_por_estado']['INCOMPLETO'] ?? 0) ?>,
            <?= (int) ($kpi['expedientes_por_estado']['BLOQUE_A_COMPLETO'] ?? 0) ?>,
            <?= (int) ($kpi['expedientes_por_estado']['COMPLETO'] ?? 0) ?>
        ],
        [colors.secondary, colors.info, colors.success]
    );

    makeDoughnut(
        'chartDocsEstado',
        ['Pendiente', 'En revision', 'Aceptado', 'Rechazado', 'Vencido'],
        [
            <?= (int) ($kpi['docs_por_estado']['PENDIENTE'] ?? 0) ?>,
            <?= (int) ($kpi['docs_por_estado']['EN_REVISION'] ?? 0) ?>,
            <?= (int) ($kpi['docs_por_estado']['ACEPTADO'] ?? 0) ?>,
            <?= (int) ($kpi['docs_por_estado']['RECHAZADO'] ?? 0) ?>,
            <?= (int) ($kpi['docs_por_estado']['VENCIDO'] ?? 0) ?>
        ],
        [colors.secondary, colors.warning, colors.success, colors.danger, colors.dark]
    );

    const fichasMes = <?= json_encode($kpi['fichas_por_mes'], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const chartFichasMes = document.getElementById('chartFichasMes');
    if (chartFichasMes) {
        new Chart(chartFichasMes, {
            type: 'bar',
            data: {
                labels: fichasMes.map((row) => row.mes),
                datasets: [{
                    label: 'Fichas creadas',
                    data: fichasMes.map((row) => row.total),
                    backgroundColor: 'rgba(47, 128, 237, 0.72)',
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    const vencimientos = <?= json_encode($kpi['docs_vencimiento_proximos'], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const chartVencimientos = document.getElementById('chartVencimientos');
    if (chartVencimientos) {
        new Chart(chartVencimientos, {
            type: 'bar',
            data: {
                labels: vencimientos.map((row) => 'Sem. ' + row.semana),
                datasets: [{
                    label: 'Documentos por vencer',
                    data: vencimientos.map((row) => row.total),
                    backgroundColor: 'rgba(242, 153, 74, 0.72)',
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
})();
</script>
<?php $view->endSection() ?>
