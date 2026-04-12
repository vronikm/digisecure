<?php
/**
 * Dashboard principal con KPIs reales y mini-gráficos.
 *
 * Variables: $user (AuthenticatedUser), $csrfToken, $currentPath, $stats
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Security\Auth\AuthenticatedUser $user */
/** @var array<string, mixed> $stats */

$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Dashboard — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-speedometer2 me-1"></i>Dashboard</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
        <p class="text-muted mb-0">Bienvenido, <?= $e($user->nombres) ?>.</p>
    </div>
    <div class="text-muted small">
        <i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i') ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Guardias Activos</div>
                        <div class="fs-3 fw-bold mt-1 text-primary"><?= (int)($stats['guardias_activos'] ?? 0) ?></div>
                        <div class="text-muted small">
                            <?= (int)($stats['fichas_revision'] ?? 0) ?> en revisión |
                            <?= (int)($stats['fichas_borrador'] ?? 0) ?> borrador
                        </div>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-person-badge fs-1 text-primary opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Docs por Vencer</div>
                        <div class="fs-3 fw-bold mt-1 text-warning"><?= (int)($stats['docs_por_vencer'] ?? 0) ?></div>
                        <div class="text-muted small">próximos 30 días</div>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-exclamation-triangle fs-1 text-warning opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-danger border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Docs Vencidos</div>
                        <div class="fs-3 fw-bold mt-1 text-danger"><?= (int)($stats['docs_vencidos'] ?? 0) ?></div>
                        <div class="text-muted small">requieren renovación</div>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-x-octagon fs-1 text-danger opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Expedientes OK</div>
                        <div class="fs-3 fw-bold mt-1 text-success"><?= (int)($stats['expedientes_completos'] ?? 0) ?></div>
                        <div class="text-muted small">
                            <?= (int)($stats['expedientes_incompletos'] ?? 0) ?> incompletos
                        </div>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle fs-1 text-success opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Actividad reciente -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-activity me-2"></i>Actividad reciente</span>
                <?php if ($user->tienePermiso('auditoria.ver')): ?>
                    <a href="<?= $view->url('/auditoria') ?>" class="btn btn-sm btn-outline-primary">Ver todo</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($stats['actividad_reciente'])): ?>
                    <p class="text-muted text-center my-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay actividad reciente registrada.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Evento</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($stats['actividad_reciente'] as $act): ?>
                                <tr>
                                    <td class="small text-muted"><?= $e($act['fecha']) ?></td>
                                    <td><?= $e($act['evento']) ?></td>
                                    <td class="small text-muted"><?= $e($act['usuario']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel lateral -->
    <div class="col-lg-4">
        <!-- Mini chart fichas -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-pie-chart me-2"></i>Fichas por Estado
            </div>
            <div class="card-body d-flex justify-content-center">
                <canvas id="miniChartFichas" style="max-height:180px;max-width:180px"></canvas>
            </div>
        </div>

        <!-- Info del tenant -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-building me-2"></i>Empresa
            </div>
            <div class="card-body">
                <dl class="mb-0 small">
                    <dt>Empresa</dt>
                    <dd><?= $e($user->tenantNombre) ?></dd>
                    <dt>Tu rol</dt>
                    <dd>
                        <?php foreach ($user->roles as $rol): ?>
                            <span class="badge bg-info"><?= $e($rol['nombre']) ?></span>
                        <?php endforeach; ?>
                    </dd>
                    <dt>Permisos activos</dt>
                    <dd><span class="badge bg-secondary"><?= count($user->permisos) ?></span></dd>
                </dl>
            </div>
        </div>

        <!-- Accesos rápidos -->
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">
                <i class="bi bi-lightning me-2"></i>Accesos rápidos
            </div>
            <div class="list-group list-group-flush">
                <?php if ($user->tienePermiso('guardias.crear')): ?>
                    <a href="<?= $view->url('/guardias/crear') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-person-plus text-primary me-2"></i>Nueva ficha de guardia
                    </a>
                <?php endif; ?>
                <?php if ($user->tienePermiso('expedientes.ver')): ?>
                    <a href="<?= $view->url('/expedientes/pendientes') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-clipboard-check text-warning me-2"></i>Expedientes pendientes
                    </a>
                <?php endif; ?>
                <?php if ($user->tienePermiso('reportes.ver')): ?>
                    <a href="<?= $view->url('/reportes/kpis') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-graph-up text-success me-2"></i>Ver KPIs completos
                    </a>
                <?php endif; ?>
                <?php if ($user->tienePermiso('usuarios.ver')): ?>
                    <a href="<?= $view->url('/usuarios') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-people text-info me-2"></i>Gestionar usuarios
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('miniChartFichas'), {
    type: 'doughnut',
    data: {
        labels: ['Completa', 'En revisión', 'Borrador'],
        datasets: [{
            data: [
                <?= (int)($stats['guardias_activos'] ?? 0) ?>,
                <?= (int)($stats['fichas_revision'] ?? 0) ?>,
                <?= (int)($stats['fichas_borrador'] ?? 0) ?>
            ],
            backgroundColor: ['#27ae60', '#f39c12', '#6c757d'],
        }]
    },
    options: {
        responsive: true,
        cutout: '60%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
});
</script>
<?php $view->endSection() ?>
