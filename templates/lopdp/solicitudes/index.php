<?php
/**
 * Listado de solicitudes ARCO-P.
 *
 * Variables: $user, $csrfToken, $currentPath, $solicitudes, $total, $page, $totalPages, $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoTone = static fn(string $estado) => match ($estado) {
    'RECIBIDA'   => 'tone-info',
    'EN_PROCESO' => 'tone-warning',
    'RESPONDIDA' => 'tone-success',
    'DENEGADA'   => 'tone-muted',
    default      => 'tone-muted',
};

$today = date('Y-m-d');

$hasFilters = !empty($filters['estado'] ?? null)
    || !empty($filters['tipo'] ?? null)
    || !empty($filters['busqueda'] ?? null);

$qs = http_build_query(array_filter([
    'estado' => $filters['estado'] ?? '',
    'tipo' => $filters['tipo'] ?? '',
    'q' => $filters['busqueda'] ?? '',
]));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
?>

<?php $view->section('title') ?>Solicitudes ARCO-P - LOPDP<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-op-filter .ds-filter-bar {
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
    }

    .ds-op-filter .ds-filter-row {
        padding: 0;
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp') ?>">LOPDP</a></li>
<li class="breadcrumb-item active">Solicitudes ARCO-P</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-list-check"></i></span>
        <div>
            <h1>Solicitudes ARCO-P</h1>
            <small><?= (int) $total ?> solicitud<?= $total !== 1 ? 'es' : '' ?> registrada<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <a href="<?= $view->url('/lopdp/solicitudes/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nueva solicitud
    </a>
</div>

<div class="ds-card ds-op-filter" data-collapsible-mobile>
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros de solicitudes</div>
            <div class="ds-card-subtitle">Consulta por estado, tipo de derecho y titular</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form method="get" action="<?= $view->url('/lopdp/solicitudes') ?>" class="ds-filter-row ds-form-filter">
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (['RECIBIDA','EN_PROCESO','RESPONDIDA','DENEGADA'] as $st): ?>
                            <option value="<?= $st ?>" <?= ($filters['estado'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo</label>
                        <select name="tipo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (['acceso','rectificacion','cancelacion','oposicion','portabilidad'] as $tp): ?>
                            <option value="<?= $tp ?>" <?= ($filters['tipo'] ?? '') === $tp ? 'selected' : '' ?>><?= ucfirst($tp) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Busqueda</label>
                        <input type="text" name="q" class="form-control form-control-sm"
                               value="<?= $e($filters['busqueda'] ?? '') ?>" placeholder="Nombre, cedula o email">
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-search"></i>Buscar
                    </button>
                    <?php if ($hasFilters): ?>
                    <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                    <?php endif ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-file-earmark-lock"></i></span>
        <div>
            <div class="ds-card-title">Listado de solicitudes</div>
            <div class="ds-card-subtitle">Seguimiento de requerimientos ARCO-P por fecha limite</div>
        </div>
    </div>

    <?php if ($solicitudes === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-file-earmark-lock"></i></div>
            <h3>No hay solicitudes registradas</h3>
            <p>
                <?php if ($hasFilters): ?>
                    Ninguna solicitud coincide con los filtros aplicados.
                <?php else: ?>
                    Cuando un titular ejerza sus derechos ARCO-P, podras registrar la solicitud aqui.
                <?php endif ?>
            </p>
            <?php if (!$hasFilters): ?>
            <a href="<?= $view->url('/lopdp/solicitudes/crear') ?>" class="ds-btn ds-btn-primary">
                <i class="bi bi-plus-lg"></i>Registrar primera solicitud
            </a>
            <?php endif ?>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Titular</th>
                    <th>Recepcion</th>
                    <th>Vence</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes as $s):
                    $vencida = $s['estado'] !== 'RESPONDIDA' && $s['estado'] !== 'DENEGADA' && $s['fecha_limite'] < $today;
                    $tone = $estadoTone((string) $s['estado']);
                ?>
                <tr <?= $vencida ? 'style="background:rgba(220,38,38,.04);"' : '' ?>>
                    <td><span class="ds-chip"><i class="bi bi-tag"></i><?= $e(ucfirst((string) $s['tipo'])) ?></span></td>
                    <td>
                        <div class="ds-cell-name"><?= $e((string) $s['titular_nombre']) ?></div>
                        <div class="ds-cell-sub"><?= $e((string) ($s['titular_cedula'] ?? '-')) ?> &middot; <?= $e((string) $s['titular_tipo']) ?></div>
                    </td>
                    <td><span class="ds-cell-mono"><?= $e((string) $s['fecha_recepcion']) ?></span></td>
                    <td>
                        <?php if ($vencida): ?>
                            <span class="ds-badge tone-danger no-dot"><i class="bi bi-exclamation-triangle"></i><?= $e((string) $s['fecha_limite']) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-mono"><?= $e((string) $s['fecha_limite']) ?></span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e((string) $s['estado']) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/lopdp/solicitudes/' . $s['id']) ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="Ver detalle">
                                Ver
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
        <span>Pagina <strong><?= $page ?></strong> de <?= $totalPages ?> &middot; <?= $total ?> solicitud<?= $total !== 1 ? 'es' : '' ?></span>
        <ul class="ds-pagination">
            <li>
                <a class="ds-page <?= $page <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/lopdp/solicitudes') ?>?page=<?= max(1, $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li>
                <a class="ds-page <?= $p === $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/lopdp/solicitudes') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/lopdp/solicitudes') ?>?page=<?= min($totalPages, $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
