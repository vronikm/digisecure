<?php
/**
 * Listado de fichas de guardia con paginacion y filtros.
 *
 * Variables: $user, $csrfToken, $currentPath, $fichas, $total, $page, $totalPages, $filters
 */

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\TipoFicha;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoTone = [
    'BORRADOR'    => 'tone-muted',
    'EN_REVISION' => 'tone-warning',
    'COMPLETA'    => 'tone-success',
    'ANULADA'     => 'tone-danger',
];

$hasFilters = !empty($filters['estado'] ?? null)
    || !empty($filters['tipo_ficha'] ?? null)
    || !empty($filters['busqueda'] ?? null);

$qs = http_build_query(array_filter([
    'estado' => $filters['estado'] ?? '',
    'tipo_ficha' => $filters['tipo_ficha'] ?? '',
    'q' => $filters['busqueda'] ?? '',
]));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
?>

<?php $view->section('title') ?>Guardias — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-person-badge me-1"></i>Guardias</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-person-badge"></i></span>
        <div>
            <h1>Fichas de guardia</h1>
            <small><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <?php if ($user->tienePermiso('guardias.crear')): ?>
    <a href="<?= $view->url('/guardias/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nueva ficha
    </a>
    <?php endif ?>
</div>

<!-- Filtros -->
<div class="ds-filter-bar">
    <form method="get" action="<?= $view->url('/guardias') ?>" class="ds-filter-row ds-form-filter">
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Nombre, apellido o correlativo..."
                       value="<?= $e($filters['busqueda'] ?? '') ?>">
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos los estados</option>
                    <?php foreach (EstadoFicha::cases() as $est): ?>
                    <option value="<?= $est->value ?>" <?= ($filters['estado'] ?? '') === $est->value ? 'selected' : '' ?>>
                        <?= $e($est->label()) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Tipo</label>
                <select name="tipo_ficha" class="form-select form-select-sm">
                    <option value="">Todos los tipos</option>
                    <?php foreach (TipoFicha::cases() as $tipo): ?>
                    <option value="<?= $tipo->value ?>" <?= ($filters['tipo_ficha'] ?? '') === $tipo->value ? 'selected' : '' ?>>
                        <?= $e($tipo->label()) ?>
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
            <a href="<?= $view->url('/guardias') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                <i class="bi bi-x-lg"></i>Limpiar
            </a>
            <?php endif ?>
        </div>
    </form>
</div>

<?php if ($fichas === []): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-person-badge"></i></div>
        <h3>No hay fichas para mostrar</h3>
        <p>
            <?php if ($hasFilters): ?>
                Ninguna ficha coincide con los criterios seleccionados. Ajusta los filtros o limpia los criterios.
            <?php else: ?>
                Crea la primera ficha para empezar a registrar a los guardias de tu empresa.
            <?php endif ?>
        </p>
        <?php if ($user->tienePermiso('guardias.crear') && !$hasFilters): ?>
        <a href="<?= $view->url('/guardias/crear') ?>" class="ds-btn ds-btn-primary">
            <i class="bi bi-plus-lg"></i>Crear la primera ficha
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
                    <th>Correlativo</th>
                    <th>Guardia</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Fecha emision</th>
                    <th>Celular</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fichas as $f):
                    $estado = EstadoFicha::from($f['estado']);
                    $tipoF = TipoFicha::from($f['tipo_ficha']);
                    $tone = $estadoTone[$f['estado']] ?? 'tone-muted';
                ?>
                <tr>
                    <td><span class="ds-cell-mono" style="font-weight:700;color:var(--ds-text);"><?= $e($f['correlativo']) ?></span></td>
                    <td>
                        <div class="ds-cell-name"><?= $e($f['apellidos']) ?> <?= $e($f['nombres']) ?></div>
                        <?php if ($f['email']): ?>
                        <div class="ds-cell-sub"><?= $e($f['email']) ?></div>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-chip"><i class="bi bi-tag"></i><?= $e($tipoF->label()) ?></span></td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e($estado->label()) ?></span></td>
                    <td>
                        <?php if ($f['fecha_emision']): ?>
                        <span class="ds-cell-mono"><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></span>
                        <?php else: ?>
                        <span class="ds-cell-sub">—</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (!empty($f['celular'])): ?>
                        <span class="ds-cell-mono"><?= $e($f['celular']) ?></span>
                        <?php else: ?>
                        <span class="ds-cell-sub">—</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/guardias/' . $f['id']) ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="Ver detalle">
                                Ver
                            </a>
                            <?php if ($user->tienePermiso('guardias.editar') && in_array($f['estado'], ['BORRADOR', 'EN_REVISION'], true)): ?>
                                <a href="<?= $view->url('/guardias/' . $f['id'] . '/editar') ?>"
                                   class="ds-btn ds-btn-outline ds-btn-sm" title="Editar">
                                    Editar
                                </a>
                            <?php endif ?>
                            <?php if ($user->tienePermiso('guardias.editar') && $f['estado'] === 'BORRADOR'): ?>
                                <form method="post" action="<?= $view->url('/guardias/' . $f['id'] . '/revision') ?>"
                                      class="d-inline ds-form-inline"
                                      onsubmit="return confirm('Enviar esta ficha a revisión?');">
                                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                    <button type="submit" class="ds-btn ds-btn-outline ds-btn-sm" title="Enviar a revisión">
                                        Revisar
                                    </button>
                                </form>
                            <?php endif ?>
                            <?php if ($user->tienePermiso('guardias.eliminar')): ?>
                                <?php if ($f['estado'] === 'ANULADA'): ?>
                                    <form method="post" action="<?= $view->url('/guardias/' . $f['id'] . '/recuperar') ?>" class="d-inline js-ficha-confirm-form ds-form-inline"
                                          data-action="recover"
                                          data-guardia="<?= $e(trim(($f['apellidos'] ?? '') . ' ' . ($f['nombres'] ?? ''))) ?>">
                                        <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                        <button type="submit" class="ds-btn ds-btn-outline ds-btn-sm" title="Recuperar">
                                            Recuperar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= $view->url('/guardias/' . $f['id'] . '/eliminar') ?>" class="d-inline js-ficha-confirm-form ds-form-inline"
                                          data-action="delete"
                                          data-guardia="<?= $e(trim(($f['apellidos'] ?? '') . ' ' . ($f['nombres'] ?? ''))) ?>">
                                        <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                        <button type="submit" class="ds-btn ds-btn-danger ds-btn-sm" title="Eliminar">
                                            Eliminar
                                        </button>
                                    </form>
                                <?php endif ?>
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
                   href="<?= $view->url('/guardias') ?>?page=<?= max(1, $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li>
                <a class="ds-page <?= $p === $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/guardias') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/guardias') ?>?page=<?= min($totalPages, $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
</div>
<?php endif ?>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(() => {
    const forms = document.querySelectorAll('.js-ficha-confirm-form');
    if (!forms.length) return;

    forms.forEach((form) => {
        form.removeAttribute('onsubmit');
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const guardia = form.dataset.guardia || 'este guardia';
            const isRecover = form.dataset.action === 'recover';

            if (isRecover) {
                Swal.fire({
                    icon: 'question',
                    title: 'Recuperar ficha de guardia?',
                    text: `La ficha de ${guardia} volvera al estado Borrador.`,
                    showCancelButton: true,
                    confirmButtonText: 'Si, recuperar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                    focusCancel: true,
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: '¿Eliminar ficha de guardia?',
                text: `La ficha de ${guardia} se marcará como Eliminado y podrás recuperarla después si es necesario.`,
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                focusCancel: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
})();
</script>
<?php $view->endSection() ?>
