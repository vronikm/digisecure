<?php
/**
 * Listado de clientes del tenant.
 *
 * Variables: $user, $csrfToken, $currentPath, $clientes,
 *            $total, $page, $totalPages, $filters, $kpis
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoTone = [
    'activo'     => 'tone-success',
    'inactivo'   => 'tone-warning',
    'archivado'  => 'tone-muted',
];

$hasFilters = !empty($filters['q']) || !empty($filters['estado']);

$qs = http_build_query(array_filter([
    'q' => $filters['q'] ?? '',
    'estado' => $filters['estado'] ?? '',
]));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
?>

<?php $view->section('title') ?>Clientes â€” DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-briefcase me-1"></i>Clientes</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-briefcase-fill"></i></span>
        <div>
            <h1>Clientes</h1>
            <small><?= (int) $kpis['total'] ?> registrado<?= (int) $kpis['total'] !== 1 ? 's' : '' ?> en el sistema</small>
        </div>
    </div>
    <?php if ($user->tienePermiso('clientes.crear')): ?>
    <a href="<?= $view->url('/clientes/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nuevo cliente
    </a>
    <?php endif ?>
</div>

<!-- KPIs -->
<div class="ds-kpi-grid ds-kpi-grid-4 ds-kpi-grid-solid">
    <div class="ds-kpi-card">
        <span class="ds-kpi-icon"><i class="bi bi-collection"></i></span>
        <div>
            <div class="ds-kpi-label">Total</div>
            <div class="ds-kpi-value"><?= (int) $kpis['total'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-check2-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Activos</div>
            <div class="ds-kpi-value"><?= (int) $kpis['activos'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-pause-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Inactivos</div>
            <div class="ds-kpi-value"><?= (int) $kpis['inactivos'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-muted">
        <span class="ds-kpi-icon"><i class="bi bi-archive"></i></span>
        <div>
            <div class="ds-kpi-label">Archivados</div>
            <div class="ds-kpi-value"><?= (int) $kpis['archivados'] ?></div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="ds-filter-bar">
    <form method="get" action="<?= $view->url('/clientes') ?>" class="ds-filter-row ds-form-filter">
<div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Razon social, RUC, contacto..."
                       value="<?= $e((string) ($filters['q'] ?? '')) ?>">
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Activos e inactivos</option>
                    <?php foreach (['activo', 'inactivo', 'archivado'] as $est): ?>
                    <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>>
                        <?= ucfirst($est) ?>
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
            <a href="<?= $view->url('/clientes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                <i class="bi bi-x-lg"></i>Limpiar
            </a>
            <?php endif ?>
        </div>
    </form>
</div>

<?php if ($clientes === []): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-briefcase"></i></div>
        <h3>No hay clientes para mostrar</h3>
        <p>
            <?php if ($hasFilters): ?>
                Ningun cliente coincide con los criterios. Ajusta los filtros o limpia los criterios.
            <?php else: ?>
                Registra el primer cliente para empezar a asociar puestos, turnos y guardias.
            <?php endif ?>
        </p>
        <?php if ($user->tienePermiso('clientes.crear') && !$hasFilters): ?>
        <a href="<?= $view->url('/clientes/crear') ?>" class="ds-btn ds-btn-primary">
            <i class="bi bi-plus-lg"></i>Crear el primer cliente
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
                    <th>Cliente</th>
                    <th>Documento</th>
                    <th>Contacto</th>
                    <th>Sector</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c):
                    $tone = $estadoTone[$c['estado']] ?? 'tone-muted';
                ?>
                <tr>
                    <td>
                        <a href="<?= $view->url('/clientes/' . $c['id']) ?>"
                           class="ds-cell-name" style="text-decoration: none;">
                            <?= $e((string) $c['razon_social']) ?>
                        </a>
                        <?php if (!empty($c['nombre_comercial'])): ?>
                        <div class="ds-cell-sub"><?= $e((string) $c['nombre_comercial']) ?></div>
                        <?php endif ?>
                    </td>
                    <td>
                        <span class="ds-chip"><i class="bi bi-tag"></i><?= $e((string) $c['tipo_documento']) ?></span>
                        <div class="ds-cell-mono" style="margin-top: .15rem;"><?= $e((string) $c['documento']) ?></div>
                    </td>
                    <td>
                        <?php if (!empty($c['contacto_nombre'])): ?>
                        <div class="ds-cell-name" style="font-weight: 500;"><?= $e((string) $c['contacto_nombre']) ?></div>
                        <?php endif ?>
                        <?php if (!empty($c['contacto_email'])): ?>
                        <div class="ds-cell-mono"><?= $e((string) $c['contacto_email']) ?></div>
                        <?php elseif (!empty($c['contacto_telefono'])): ?>
                        <div class="ds-cell-mono"><?= $e((string) $c['contacto_telefono']) ?></div>
                        <?php else: ?>
                        <span class="ds-cell-sub">â€”</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (!empty($c['sector'])): ?>
                            <span class="ds-chip"><?= $e((string) $c['sector']) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub">â€”</span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e(ucfirst((string) $c['estado'])) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/clientes/' . $c['id']) ?>" class="ds-icon-btn" title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($user->tienePermiso('clientes.editar') && $c['estado'] !== 'archivado'): ?>
                            <a href="<?= $view->url('/clientes/' . $c['id'] . '/editar') ?>" class="ds-icon-btn" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif ?>
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
                   href="<?= $view->url('/clientes') ?>?page=<?= max(1, $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li>
                <a class="ds-page <?= $p === $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/clientes') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/clientes') ?>?page=<?= min($totalPages, $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
</div>
<?php endif ?>
<?php $view->endSection() ?>

