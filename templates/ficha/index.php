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
$listError = (bool) ($listError ?? false);
$aspiranteMode = (bool) ($aspiranteMode ?? false);

$hasFilters = !empty($filters['busqueda'] ?? null);
$qs = http_build_query(array_filter([
    'q' => $filters['busqueda'] ?? '',
]));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
$pageTitle = $aspiranteMode ? 'Mi ficha' : 'Guardias';
$pageSubtitle = $aspiranteMode
    ? ($total > 0 ? 'Ficha registrada para actualizar datos y cargar documentos' : 'Completa tu ficha para habilitar el expediente documental')
    : ((int) $total . ' registro' . ((int) $total !== 1 ? 's' : '') . ' encontrado' . ((int) $total !== 1 ? 's' : ''));
?>

<?php $view->section('title') ?><?= $e($pageTitle) ?> - DigiSecure<?php $view->endSection() ?>

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
<li class="breadcrumb-item active"><?= $e($pageTitle) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-person-badge"></i></span>
        <div>
            <h1><?= $e($pageTitle) ?></h1>
            <small><?= $e($pageSubtitle) ?></small>
        </div>
    </div>
    <?php if (!$aspiranteMode && $user->tienePermiso('guardias.crear')): ?>
    <a href="<?= $view->url('/guardias/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nueva ficha
    </a>
    <?php elseif ($aspiranteMode && $guardias === []): ?>
    <a href="<?= $view->url('/guardias/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-pencil-square"></i>Completar ficha
    </a>
    <?php endif ?>
</div>

<?php if ($listError): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            No se pudo cargar el listado de guardias. Puedes continuar navegando mientras soporte revisa el log del servidor.
        </div>
    </div>
<?php endif; ?>

<?php if (!$aspiranteMode): ?>
<div class="ds-card ds-guardias-filter">
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
<?php endif ?>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-people"></i></span>
        <div>
            <div class="ds-card-title"><?= $aspiranteMode ? 'Ficha cargada' : 'Listado de guardias' ?></div>
            <div class="ds-card-subtitle"><?= $aspiranteMode ? 'Actualiza tus datos y continua con la documentacion requerida' : 'Un registro por persona con acceso a su historial de fichas' ?></div>
        </div>
    </div>
    <?php if ($guardias === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-person-badge"></i></div>
            <h3><?= $aspiranteMode ? 'Aun no has guardado tu ficha' : 'No hay guardias para mostrar' ?></h3>
            <p>
                <?php if ($aspiranteMode): ?>
                    Guarda tu ficha para habilitar el expediente documental y cargar los documentos digitales.
                <?php elseif ($hasFilters): ?>
                    Ningun guardia coincide con los criterios de busqueda.
                <?php else: ?>
                    Cuando registres fichas de guardias, se mostraran aqui agrupadas por persona.
                <?php endif ?>
            </p>
            <?php if ($aspiranteMode): ?>
            <a href="<?= $view->url('/guardias/crear') ?>" class="ds-btn ds-btn-primary">
                <i class="bi bi-pencil-square"></i>Completar ficha
            </a>
            <?php endif ?>
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
                    $fichaId = (string) ($g['ficha_id'] ?? '');
                    $historialUrl = $view->url('/guardias/' . $fichaId . '/fichas');
                    $editarUrl = $view->url('/guardias/' . $fichaId . '/editar');
                    $expedienteUrl = $view->url('/guardias/' . $fichaId . '/expediente');
                    $rowUrl = $aspiranteMode ? $editarUrl : $historialUrl;
                ?>
                <tr class="js-row-link" data-href="<?= $rowUrl ?>" style="cursor:pointer;">
                    <td><span class="ds-cell-mono"><?= $e((string) ($g['cedula_display'] ?? '-')) ?></span></td>
                    <td>
                        <div class="ds-cell-name"><?= $e((string) ($g['apellidos'] ?? '')) ?></div>
                        <?php if (!$aspiranteMode): ?>
                            <?php if (!empty($g['fecha_baja'])): ?>
                            <span class="ds-badge tone-muted no-dot" style="font-size:.62rem;">De baja</span>
                            <?php elseif (!empty($g['es_aspirante'])): ?>
                            <span class="ds-badge tone-info no-dot" style="font-size:.62rem;" title="Ficha registrada por el aspirante; aún no contratado">Aspirante</span>
                            <?php else: ?>
                            <span class="ds-badge tone-success no-dot" style="font-size:.62rem;">Empleado</span>
                            <?php endif ?>
                        <?php endif ?>
                    </td>
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
                            <?php if ($aspiranteMode): ?>
                            <a href="<?= $editarUrl ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm"
                               title="Actualizar ficha">
                                Actualizar ficha
                            </a>
                            <a href="<?= $expedienteUrl ?>"
                               class="ds-btn ds-btn-primary ds-btn-sm"
                               title="Cargar expediente documental">
                                Expediente documental
                            </a>
                            <?php else: ?>
                            <a href="<?= $historialUrl ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm"
                               title="Ver fichas del guardia">
                                Ver fichas
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
