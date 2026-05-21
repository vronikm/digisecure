<?php
/**
 * Catalogo global de planes (superadmin).
 *
 * Variables: $planes, $stats, $busqueda, $estadoFiltro, $publicoFiltro
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$planes = is_array($planes ?? null) ? $planes : [];
$stats = is_array($stats ?? null) ? $stats : [];
$csrfToken = (string) ($csrfToken ?? '');
$busqueda = (string) ($busqueda ?? '');
$estadoFiltro = (string) ($estadoFiltro ?? '');
$publicoFiltro = (string) ($publicoFiltro ?? '');
$hasFilters = $busqueda !== '' || $estadoFiltro !== '' || $publicoFiltro !== '';
?>

<?php $view->section('title') ?>Planes | Superadmin<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/admin') ?>">Plataforma</a></li>
<li class="breadcrumb-item active">Planes</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-gem"></i></span>
        <div>
            <h1>Administracion de planes</h1>
            <small>Catalogo y configuracion global de planes SaaS</small>
        </div>
    </div>
    <div style="display:flex; gap:.5rem;">
        <a href="<?= $view->url('/admin/planes/crear') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
            <i class="bi bi-plus-lg"></i>Crear plan
        </a>
        <a href="<?= $view->url('/admin') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Volver al panel
        </a>
    </div>
</div>

<div class="ds-kpi-grid ds-kpi-grid-solid">
    <div class="ds-kpi-card">
        <span class="ds-kpi-icon"><i class="bi bi-collection"></i></span>
        <div>
            <div class="ds-kpi-label">Total planes</div>
            <div class="ds-kpi-value"><?= (int) ($stats['total'] ?? 0) ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-check-circle-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Activos</div>
            <div class="ds-kpi-value"><?= (int) ($stats['activos'] ?? 0) ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-muted">
        <span class="ds-kpi-icon"><i class="bi bi-pause-circle-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Inactivos</div>
            <div class="ds-kpi-value"><?= (int) ($stats['inactivos'] ?? 0) ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-info">
        <span class="ds-kpi-icon"><i class="bi bi-globe2"></i></span>
        <div>
            <div class="ds-kpi-label">Publicos</div>
            <div class="ds-kpi-value"><?= (int) ($stats['publicos'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="ds-filter-bar">
    <form method="GET" action="<?= $view->url('/admin/planes') ?>" class="ds-filter-row ds-form-filter">
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Buscar</label>
                <input type="text" class="form-control form-control-sm" name="q"
                       placeholder="Codigo, nombre o descripcion..."
                       value="<?= $e($busqueda) ?>">
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="activos" <?= $estadoFiltro === 'activos' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivos" <?= $estadoFiltro === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Visibilidad</label>
                <select name="publico" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="publicos" <?= $publicoFiltro === 'publicos' ? 'selected' : '' ?>>Publicos</option>
                    <option value="privados" <?= $publicoFiltro === 'privados' ? 'selected' : '' ?>>Privados</option>
                </select>
            </div>
        </div>
        <div class="ds-filter-actions">
            <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                <i class="bi bi-search"></i>Buscar
            </button>
            <?php if ($hasFilters): ?>
            <a href="<?= $view->url('/admin/planes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                <i class="bi bi-x-lg"></i>Limpiar
            </a>
            <?php endif ?>
        </div>
    </form>
</div>

<?php if ($planes === []): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-gem"></i></div>
        <h3>No hay planes para mostrar</h3>
        <p>
            <?php if ($hasFilters): ?>
                Ningun plan coincide con los filtros aplicados.
            <?php else: ?>
                Crea el primer plan para habilitar su asignacion a empresas.
            <?php endif ?>
        </p>
    </div>
</div>
<?php else: ?>
<div class="ds-table-card">
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Codigo</th>
                    <th class="ds-cell-end">Nivel</th>
                    <th class="ds-cell-end">Mensual</th>
                    <th class="ds-cell-end">Anual</th>
                    <th class="ds-cell-end">Limites</th>
                    <th>Visibilidad</th>
                    <th>Estado</th>
                    <th>Actualizado</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($planes as $p): ?>
                <?php
                    $activo = (int) ($p['activo'] ?? 0) === 1;
                    $publico = (int) ($p['es_publico'] ?? 0) === 1;
                ?>
                <tr>
                    <td>
                        <div class="ds-cell-name"><?= $e((string) $p['nombre']) ?></div>
                        <div class="ds-cell-sub"><?= $e((string) ($p['descripcion'] ?? '')) ?></div>
                    </td>
                    <td><span class="ds-cell-mono"><?= $e((string) $p['codigo']) ?></span></td>
                    <td class="ds-cell-end"><span class="ds-cell-mono"><?= (int) ($p['nivel'] ?? 0) ?></span></td>
                    <td class="ds-cell-end ds-cell-mono">$<?= number_format((float) ($p['precio_mensual_usd'] ?? 0), 2) ?></td>
                    <td class="ds-cell-end ds-cell-mono">$<?= number_format((float) ($p['precio_anual_usd'] ?? 0), 2) ?></td>
                    <td class="ds-cell-end">
                        <span class="ds-cell-sub"><?= (int) ($p['max_usuarios'] ?? 0) ?> usr</span>
                        <span class="ds-cell-sub"><?= (int) ($p['max_guardias'] ?? 0) ?> gds</span>
                        <span class="ds-cell-sub"><?= (int) ($p['max_storage_mb'] ?? 0) ?> MB</span>
                    </td>
                    <td>
                        <span class="ds-badge <?= $publico ? 'tone-info' : 'tone-muted' ?>">
                            <?= $publico ? 'Publico' : 'Privado' ?>
                        </span>
                    </td>
                    <td>
                        <span class="ds-badge <?= $activo ? 'tone-success' : 'tone-muted' ?>">
                            <?= $activo ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td><span class="ds-cell-mono"><?= $e(substr((string) ($p['updated_at'] ?? ''), 0, 16)) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/admin/planes/' . urlencode((string) $p['codigo']) . '/editar') ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="Editar plan">
                                Editar
                            </a>
                            <form method="post" action="<?= $view->url('/admin/planes/' . urlencode((string) $p['codigo']) . '/estado') ?>"
                                  class="js-plan-confirm-form"
                                  data-plan="<?= $e((string) $p['nombre']) ?>"
                                  data-code="<?= $e((string) $p['codigo']) ?>"
                                  data-action="<?= $activo ? 'inactivar' : 'activar' ?>"
                                  style="display:inline;">
                                <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="estado" value="<?= $activo ? 'inactivo' : 'activo' ?>">
                                <button type="submit" class="ds-btn ds-btn-outline ds-btn-sm" title="<?= $activo ? 'Inactivar' : 'Activar' ?> plan">
                                    <?= $activo ? 'Inactivar' : 'Activar' ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif ?>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(() => {
    const forms = document.querySelectorAll('.js-plan-confirm-form');
    if (!forms.length) return;

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const action = form.dataset.action || 'actualizar';
            const planName = form.dataset.plan || 'este plan';
            const planCode = form.dataset.code || '';
            const isActivate = action === 'activar';

            Swal.fire({
                icon: isActivate ? 'question' : 'warning',
                title: `${isActivate ? 'Activar' : 'Inactivar'} plan?`,
                text: `Se va a ${action} el plan ${planName}${planCode !== '' ? ` (${planCode})` : ''}.`,
                showCancelButton: true,
                confirmButtonText: isActivate ? 'Si, activar' : 'Si, inactivar',
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
