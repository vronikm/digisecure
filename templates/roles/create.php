<?php
/**
 * Formulario de creacion de rol.
 *
 * Variables: $user, $csrfToken, $currentPath, $catalogo, $input?, $error?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$input = $input ?? [];
$selectedPermisos = $input['permisos_ids'] ?? [];
?>

<?php $view->section('title') ?>Crear rol - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/roles') ?>">Roles</a></li>
<li class="breadcrumb-item active">Crear</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-plus-circle"></i></span>
        <div>
            <h1>Crear nuevo rol</h1>
            <small>Define el codigo, el nombre y los permisos que tendran los usuarios asignados</small>
        </div>
    </div>
    <a href="<?= $view->url('/roles') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo crear el rol</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<form class="ds-form-main" method="post" action="<?= $view->url('/roles/crear') ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <!-- Datos basicos -->
    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-shield-lock"></i></span>
            <div>
                <div class="ds-card-title">Datos del rol</div>
                <div class="ds-card-subtitle">Identificador y nombre visible</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Codigo <span class="ds-required">*</span></label>
                        <input type="text" name="codigo" class="form-control" maxlength="50"
                               value="<?= $e((string) ($input['codigo'] ?? '')) ?>" required
                               pattern="[a-z0-9_]+" title="Solo minusculas, numeros y guiones bajos"
                               placeholder="ej: supervisor_campo">
                        <span class="ds-form-help">Identificador unico. Solo minusculas, numeros y guion bajo.</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre <span class="ds-required">*</span></label>
                        <input type="text" name="nombre" class="form-control" maxlength="120"
                               value="<?= $e((string) ($input['nombre'] ?? '')) ?>" required
                               placeholder="ej: Supervisor de Campo">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Descripcion</label>
                        <input type="text" name="descripcion" class="form-control" maxlength="255"
                               value="<?= $e((string) ($input['descripcion'] ?? '')) ?>"
                               placeholder="Breve descripcion del rol">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Permisos -->
    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-key"></i></span>
            <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <div class="ds-card-title">Permisos asignados</div>
                    <div class="ds-card-subtitle">Marca los permisos que este rol tendra en el sistema</div>
                </div>
                <div style="display: flex; gap: .35rem;">
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" onclick="toggleAll(true)">
                        <i class="bi bi-check-all"></i>Marcar todos
                    </button>
                    <button type="button" class="ds-btn ds-btn-ghost ds-btn-sm" onclick="toggleAll(false)">
                        <i class="bi bi-x-lg"></i>Desmarcar
                    </button>
                </div>
            </div>
        </div>
        <div class="ds-card-body">
            <?php foreach ($catalogo as $recurso => $permisosRecurso): ?>
            <div style="margin-bottom: 1.25rem;">
                <h6 style="font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--ds-text-soft); margin: 0 0 .65rem; padding-bottom: .35rem; border-bottom: 1px solid #eef1f5; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="bi bi-folder me-1"></i><?= $e($recurso) ?></span>
                    <button type="button"
                            style="background: transparent; border: 0; color: #2563eb; font-size: .72rem; font-weight: 600; cursor: pointer; padding: 0;"
                            onclick="toggleGroup('<?= $e($recurso) ?>')">
                        Toggle grupo
                    </button>
                </h6>
                <div class="row g-2">
                    <?php foreach ($permisosRecurso as $p): ?>
                    <div class="col-md-6 col-lg-4">
                        <label for="perm_<?= $e($p['id']) ?>"
                               style="display: flex; gap: .55rem; align-items: flex-start; padding: .65rem .75rem; border-radius: 10px; background: #f4f7fb; border: 1px solid #e2e8f0; cursor: pointer; transition: background .12s;"
                               onmouseover="this.style.background='#eaeff7'" onmouseout="this.style.background='#f4f7fb'">
                            <input class="perm-check" type="checkbox"
                                   name="permisos_ids[]" value="<?= $e($p['id']) ?>"
                                   id="perm_<?= $e($p['id']) ?>"
                                   data-recurso="<?= $e($recurso) ?>"
                                   <?= in_array($p['id'], $selectedPermisos, true) ? 'checked' : '' ?>
                                   style="margin-top: .15rem; flex-shrink: 0; accent-color: #2f80ed;">
                            <span style="flex: 1; min-width: 0;">
                                <span class="ds-cell-mono" style="font-weight: 600; color: var(--ds-text);"><?= $e($p['codigo']) ?></span>
                                <?php if (!empty($p['descripcion'])): ?>
                                <span class="ds-cell-sub" style="display: block; margin-top: .15rem;"><?= $e($p['descripcion']) ?></span>
                                <?php endif ?>
                            </span>
                        </label>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
            <?php endforeach ?>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/roles') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Crear rol
        </button>
    </div>
</form>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script>
function toggleAll(state) {
    document.querySelectorAll('.perm-check').forEach(cb => cb.checked = state);
}
function toggleGroup(recurso) {
    const cbs = document.querySelectorAll('.perm-check[data-recurso="' + recurso + '"]');
    const allChecked = Array.from(cbs).every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
}
</script>
<?php $view->endSection() ?>
