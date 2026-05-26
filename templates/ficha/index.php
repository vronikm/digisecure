<?php
/**
 * Listado de guardias (un registro por persona) con paginacion y busqueda.
 *
 * Variables: $user, $csrfToken, $currentPath, $guardias, $total, $page, $totalPages, $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$hasFilters = !empty($filters['busqueda'] ?? null);
$qs = http_build_query(array_filter([
    'q' => $filters['busqueda'] ?? '',
]));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
?>

<?php $view->section('title') ?>Guardias - DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-guardias-filter .ds-filter-bar {
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
    }
    .ds-guardias-filter .ds-filter-row {
        padding: 0;
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active">Guardias</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-person-badge"></i></span>
        <div>
            <h1>Guardias</h1>
            <small><?= (int) $total ?> registro<?= (int) $total !== 1 ? 's' : '' ?> encontrado<?= (int) $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <?php if ($user->tienePermiso('guardias.crear')): ?>
    <a href="<?= $view->url('/guardias/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nueva ficha
    </a>
    <?php endif ?>
</div>

<div class="ds-card ds-guardias-filter">
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros de guardias</div>
            <div class="ds-card-subtitle">Busqueda por identidad y datos de contacto</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form method="get" action="<?= $view->url('/guardias') ?>" class="ds-filter-row ds-form-filter">
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Buscar</label>
                        <input type="text" name="q" class="form-control form-control-sm"
                               placeholder="Cedula, apellido, nombre, celular o correo"
                               value="<?= $e((string) ($filters['busqueda'] ?? '')) ?>">
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-search"></i>Buscar
                    </button>
                    <?php if ($hasFilters): ?>
                    <a href="<?= $view->url('/guardias') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
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
        <span class="ds-card-mark"><i class="bi bi-people"></i></span>
        <div>
            <div class="ds-card-title">Listado de guardias</div>
            <div class="ds-card-subtitle">Un registro por persona con acceso a su historial de fichas</div>
        </div>
    </div>
    <?php if ($guardias === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-person-badge"></i></div>
            <h3>No hay guardias para mostrar</h3>
            <p>
                <?php if ($hasFilters): ?>
                    Ningun guardia coincide con los criterios de busqueda.
                <?php else: ?>
                    Cuando registres fichas de guardias, se mostraran aqui agrupadas por persona.
                <?php endif ?>
            </p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Cedula</th>
                    <th>Apellidos</th>
                    <th>Nombres</th>
                    <th>Fecha de nacimiento</th>
                    <th>Celular</th>
                    <th>Correo</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($guardias as $g):
                    $historialUrl = $view->url('/guardias/' . $g['ficha_id'] . '/fichas');
                ?>
                <tr class="js-row-link" data-href="<?= $historialUrl ?>" style="cursor:pointer;">
                    <td><span class="ds-cell-mono"><?= $e((string) ($g['cedula_display'] ?? '-')) ?></span></td>
                    <td><div class="ds-cell-name"><?= $e((string) ($g['apellidos'] ?? '')) ?></div></td>
                    <td><div class="ds-cell-name"><?= $e((string) ($g['nombres'] ?? '')) ?></div></td>
                    <td>
                        <?php if (!empty($g['fecha_nacimiento'])): ?>
                        <span class="ds-cell-mono"><?= date('d/m/Y', strtotime((string) $g['fecha_nacimiento'])) ?></span>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (!empty($g['celular'])): ?>
                        <span class="ds-cell-mono"><?= $e((string) $g['celular']) ?></span>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (!empty($g['email'])): ?>
                        <span class="ds-cell-mono"><?= $e((string) $g['email']) ?></span>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $historialUrl ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm"
                               title="Ver fichas del guardia">
                                Ver fichas
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
        <span>Pagina <strong><?= (int) $page ?></strong> de <?= (int) $totalPages ?> &middot; <?= (int) $total ?> guardia<?= (int) $total !== 1 ? 's' : '' ?></span>
        <ul class="ds-pagination">
            <li>
                <a class="ds-page <?= (int) $page <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/guardias') ?>?page=<?= max(1, (int) $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, (int) $page - 2); $p <= min((int) $totalPages, (int) $page + 2); $p++): ?>
            <li>
                <a class="ds-page <?= $p === (int) $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/guardias') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= (int) $page >= (int) $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/guardias') ?>?page=<?= min((int) $totalPages, (int) $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
    <?php endif ?>
</div>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script>
(() => {
    const rows = document.querySelectorAll('.js-row-link');
    if (!rows.length) return;

    rows.forEach((row) => {
        row.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.closest('a,button,form,input,select,textarea,label')) {
                return;
            }
            const href = row.getAttribute('data-href');
            if (href) {
                window.location.href = href;
            }
        });
    });
})();
</script>
<?php $view->endSection() ?>
