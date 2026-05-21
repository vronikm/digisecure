<?php
/**
 * Listado de puestos (sitios donde el tenant presta servicios).
 *
 * Variables: $user, $csrfToken, $currentPath, $puestos,
 *            $total, $page, $totalPages, $filters, $kpis, $clientes, $tiposPuesto
 */

use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Puesto\Domain\Enum\TipoPuesto;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoTone = [
    'activo'     => 'tone-success',
    'inactivo'   => 'tone-warning',
    'archivado'  => 'tone-muted',
];

$hasFilters = !empty($filters['q']) || !empty($filters['estado']) || !empty($filters['tipo']) || !empty($filters['cliente_id']);

$qs = http_build_query(array_filter([
    'q' => $filters['q'] ?? '',
    'estado' => $filters['estado'] ?? '',
    'tipo' => $filters['tipo'] ?? '',
    'cliente_id' => $filters['cliente_id'] ?? '',
]));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
?>

<?php $view->section('title') ?>Puestos — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-geo-alt me-1"></i>Puestos</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-geo-alt-fill"></i></span>
        <div>
            <h1>Puestos / Sitios</h1>
            <small><?= (int) $kpis['total'] ?> registrado<?= (int) $kpis['total'] !== 1 ? 's' : '' ?> &middot; <?= (int) $kpis['plazas'] ?> plaza<?= (int) $kpis['plazas'] !== 1 ? 's' : '' ?> activa<?= (int) $kpis['plazas'] !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <?php if ($user->tienePermiso('puestos.crear')): ?>
    <a href="<?= $view->url('/puestos/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nuevo puesto
    </a>
    <?php endif ?>
</div>

<!-- KPIs -->
<div class="ds-kpi-grid">
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
    <div class="ds-kpi-card tone-purple">
        <span class="ds-kpi-icon"><i class="bi bi-people-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Plazas activas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['plazas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-pause-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Inactivos</div>
            <div class="ds-kpi-value"><?= (int) $kpis['inactivos'] ?></div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="ds-filter-bar">
    <form method="get" action="<?= $view->url('/puestos') ?>" class="ds-filter-row ds-form-filter">
        <div class="ds-filter-summary">
            <i class="bi bi-funnel"></i>
            <strong>Filtrar:</strong>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Nombre, codigo, cliente o direccion..."
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
                <label class="ds-form-label">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($tiposPuesto as $tp): ?>
                    <option value="<?= $tp->value ?>" <?= ($filters['tipo'] ?? '') === $tp->value ? 'selected' : '' ?>>
                        <?= $e($tp->label()) ?>
                    </option>
                    <?php endforeach ?>
                </select>
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
                <i class="bi bi-search"></i>Aplicar
            </button>
            <?php if ($hasFilters): ?>
            <a href="<?= $view->url('/puestos') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                <i class="bi bi-x-lg"></i>Limpiar
            </a>
            <?php endif ?>
        </div>
    </form>
</div>

<?php if ($puestos === []): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-geo-alt"></i></div>
        <h3>No hay puestos para mostrar</h3>
        <p>
            <?php if ($hasFilters): ?>
                Ningun puesto coincide con los criterios. Ajusta los filtros o limpialos.
            <?php else: ?>
                Crea el primer puesto para asociarlo a un cliente y poder asignar guardias.
            <?php endif ?>
        </p>
        <?php if ($user->tienePermiso('puestos.crear') && !$hasFilters): ?>
        <a href="<?= $view->url('/puestos/crear') ?>" class="ds-btn ds-btn-primary">
            <i class="bi bi-plus-lg"></i>Crear el primer puesto
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
                    <th>Puesto</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Modalidad</th>
                    <th class="ds-cell-end">Plazas</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($puestos as $p):
                    $tipo = TipoPuesto::tryFrom((string) $p['tipo']);
                    $tone = $estadoTone[$p['estado']] ?? 'tone-muted';
                ?>
                <tr>
                    <td>
                        <a href="<?= $view->url('/puestos/' . $p['id']) ?>"
                           class="ds-cell-name" style="text-decoration: none;">
                            <?= $e((string) $p['nombre']) ?>
                        </a>
                        <div class="ds-cell-sub">
                            <?php if (!empty($p['codigo'])): ?>
                                <span class="ds-cell-mono"><?= $e((string) $p['codigo']) ?></span> &middot;
                            <?php endif ?>
                            <?= $e((string) ($p['ciudad'] ?? '—')) ?>
                        </div>
                    </td>
                    <td><div class="ds-cell-name" style="font-weight: 500;"><?= $e((string) $p['cliente_razon_social']) ?></div></td>
                    <td>
                        <?php if ($tipo): ?>
                            <span class="ds-chip"><i class="bi <?= $tipo->icon() ?>"></i><?= $e($tipo->label()) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub"><?= $e((string) $p['tipo']) ?></span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-cell-sub"><?= $e(ucfirst((string) $p['modalidad'])) ?></span></td>
                    <td class="ds-cell-end"><span class="ds-chip"><i class="bi bi-people"></i><?= (int) $p['num_guardias_requeridos'] ?></span></td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e(ucfirst((string) $p['estado'])) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/puestos/' . $p['id']) ?>" class="ds-icon-btn" title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($user->tienePermiso('puestos.editar') && $p['estado'] !== 'archivado'): ?>
                            <a href="<?= $view->url('/puestos/' . $p['id'] . '/editar') ?>" class="ds-icon-btn" title="Editar">
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
                   href="<?= $view->url('/puestos') ?>?page=<?= max(1, $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($pp = max(1, $page - 2); $pp <= min($totalPages, $page + 2); $pp++): ?>
            <li>
                <a class="ds-page <?= $pp === $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/puestos') ?>?page=<?= $pp ?><?= $qsPrefix ?>"><?= $pp ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/puestos') ?>?page=<?= min($totalPages, $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
</div>
<?php endif ?>
<?php $view->endSection() ?>
