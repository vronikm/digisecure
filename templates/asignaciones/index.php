<?php
/**
 * Listado de asignaciones guardia <-> puesto.
 *
 * Variables: $user, $csrfToken, $currentPath, $asignaciones,
 *            $total, $page, $totalPages, $filters, $kpis, $clientes
 */

use DigiSecure\Asignacion\Domain\Enum\EstadoAsignacion;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$hasFilters = !empty($filters['q']) || !empty($filters['estado']) || !empty($filters['cliente_id']);

$qs = http_build_query(array_filter([
    'q' => $filters['q'] ?? '',
    'estado' => $filters['estado'] ?? '',
    'cliente_id' => $filters['cliente_id'] ?? '',
]));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';

$today = date('Y-m-d');
$cobertura = $kpis['plazas_total'] > 0
    ? min(100, (int) round($kpis['plazas_cubiertas'] / $kpis['plazas_total'] * 100))
    : 0;
$coberturaTone = $cobertura >= 90 ? 'tone-success' : ($cobertura >= 60 ? 'tone-warning' : 'tone-danger');
?>

<?php $view->section('title') ?>Asignaciones â€” DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-link-45deg me-1"></i>Asignaciones</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-link-45deg"></i></span>
        <div>
            <h1>Asignaciones</h1>
            <small><?= (int) $kpis['plazas_cubiertas'] ?> de <?= (int) $kpis['plazas_total'] ?> plazas cubiertas (<?= $cobertura ?>%)</small>
        </div>
    </div>
    <?php if ($user->tienePermiso('asignaciones.crear')): ?>
    <a href="<?= $view->url('/asignaciones/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nueva asignacion
    </a>
    <?php endif ?>
</div>

<!-- KPIs -->
<div class="ds-kpi-grid ds-kpi-grid-solid">
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-check2-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Activas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['activas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card <?= $coberturaTone ?>">
        <span class="ds-kpi-icon"><i class="bi bi-pie-chart-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Cobertura de plazas</div>
            <div class="ds-kpi-value"><?= $cobertura ?>%</div>
            <div class="ds-cell-sub" style="margin-top:.25rem;"><?= (int) $kpis['plazas_cubiertas'] ?> / <?= (int) $kpis['plazas_total'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-clock-history"></i></span>
        <div>
            <div class="ds-kpi-label">Por vencer (14d)</div>
            <div class="ds-kpi-value"><?= (int) $kpis['por_vencer'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-muted">
        <span class="ds-kpi-icon"><i class="bi bi-archive"></i></span>
        <div>
            <div class="ds-kpi-label">Finalizadas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['finalizadas'] ?></div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="ds-filter-bar">
    <form method="get" action="<?= $view->url('/asignaciones') ?>" class="ds-filter-row ds-form-filter">
<div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Nombre del guardia, correlativo, puesto o cliente..."
                       value="<?= $e((string) ($filters['q'] ?? '')) ?>">
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Cliente</label>
                <select name="cliente_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($clientes as $cl): ?>
                    <option value="<?= $e($cl['id']) ?>" <?= ($filters['cliente_id'] ?? '') === $cl['id'] ? 'selected' : '' ?>>
                        <?= $e($cl['razon_social']) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (EstadoAsignacion::cases() as $est): ?>
                    <option value="<?= $est->value ?>" <?= ($filters['estado'] ?? '') === $est->value ? 'selected' : '' ?>>
                        <?= $e($est->label()) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="ds-filter-actions">
            <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                <i class="bi bi-search"></i>Buscar
            </button>
            <?php if ($hasFilters): ?>
            <a href="<?= $view->url('/asignaciones') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                <i class="bi bi-x-lg"></i>Limpiar
            </a>
            <?php endif ?>
        </div>
    </form>
</div>

<?php if ($asignaciones === []): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-link-45deg"></i></div>
        <h3>No hay asignaciones para mostrar</h3>
        <p>
            <?php if ($hasFilters): ?>
                Ninguna asignacion coincide con los criterios. Ajusta los filtros o limpialos.
            <?php else: ?>
                Crea la primera asignacion vinculando un guardia (ficha COMPLETA) a un puesto activo.
            <?php endif ?>
        </p>
        <?php if ($user->tienePermiso('asignaciones.crear') && !$hasFilters): ?>
        <a href="<?= $view->url('/asignaciones/crear') ?>" class="ds-btn ds-btn-primary">
            <i class="bi bi-plus-lg"></i>Crear primera asignacion
        </a>
        <?php endif ?>
    </div>
</div>
<?php else: ?>
<div class="ds-table-card">
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Guardia</th>
                    <th>Puesto / Cliente</th>
                    <th>Periodo</th>
                    <th>Rol en sitio</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($asignaciones as $a):
                    $estado = EstadoAsignacion::tryFrom((string) $a['estado']);
                    $tone = $estado ? $estado->tone() : 'tone-muted';
                    $vencidaProxima = $estado === EstadoAsignacion::ACTIVA
                        && !empty($a['fecha_fin'])
                        && $a['fecha_fin'] >= $today
                        && (strtotime((string) $a['fecha_fin']) - time()) <= 14 * 86400;
                ?>
                <tr>
                    <td>
                        <a href="<?= $view->url('/asignaciones/' . $a['id']) ?>"
                           class="ds-cell-name" style="text-decoration: none;">
                            <?= $e((string) $a['ficha_apellidos']) ?> <?= $e((string) $a['ficha_nombres']) ?>
                        </a>
                        <div class="ds-cell-sub"><span class="ds-cell-mono"><?= $e((string) $a['ficha_correlativo']) ?></span></div>
                    </td>
                    <td>
                        <div class="ds-cell-name" style="font-weight: 500;"><?= $e((string) $a['puesto_nombre']) ?></div>
                        <div class="ds-cell-sub"><?= $e((string) $a['cliente_razon_social']) ?></div>
                    </td>
                    <td>
                        <div class="ds-cell-mono" style="font-weight: 600; color: var(--ds-text);">
                            <?= date('d/m/Y', strtotime((string) $a['fecha_inicio'])) ?>
                        </div>
                        <div class="ds-cell-sub">
                            <?php if (!empty($a['fecha_fin'])): ?>
                                hasta <?= date('d/m/Y', strtotime((string) $a['fecha_fin'])) ?>
                                <?php if ($vencidaProxima): ?>
                                    <span class="ds-badge tone-warning no-dot" style="font-size: .65rem; margin-left: .25rem;">por vencer</span>
                                <?php endif ?>
                            <?php else: ?>
                                <span style="color:#059669;">vigente</span>
                            <?php endif ?>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($a['rol_en_puesto'])): ?>
                            <span class="ds-chip"><?= $e((string) $a['rol_en_puesto']) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub">â€”</span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $estado ? $e($estado->label()) : $e((string) $a['estado']) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/asignaciones/' . $a['id']) ?>" class="ds-icon-btn" title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="ds-table-foot">
        <span>Pagina <strong><?= $page ?></strong> de <?= $totalPages ?> &middot; <?= $total ?> registro<?= $total !== 1 ? 's' : '' ?></span>
        <ul class="ds-pagination">
            <li>
                <a class="ds-page <?= $page <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/asignaciones') ?>?page=<?= max(1, $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($pp = max(1, $page - 2); $pp <= min($totalPages, $page + 2); $pp++): ?>
            <li>
                <a class="ds-page <?= $pp === $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/asignaciones') ?>?page=<?= $pp ?><?= $qsPrefix ?>"><?= $pp ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/asignaciones') ?>?page=<?= min($totalPages, $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
</div>
<?php endif ?>
<?php $view->endSection() ?>

