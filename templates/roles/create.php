<?php
/**
 * Formulario de creación de rol.
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

<?php $view->section('title') ?>Crear rol — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/roles') ?>">Roles</a></li>
<li class="breadcrumb-item active">Crear</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Crear nuevo rol</h4>
    <a href="<?= $view->url('/roles') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $view->url('/roles/crear') ?>">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <!-- Datos básicos -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Datos del rol</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Código *</label>
                    <input type="text" name="codigo" class="form-control" maxlength="50"
                           value="<?= $e((string)($input['codigo'] ?? '')) ?>" required
                           pattern="[a-z0-9_]+" title="Solo minúsculas, números y guiones bajos"
                           placeholder="ej: supervisor_campo">
                    <div class="form-text">Identificador único. Solo minúsculas, números y _</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" maxlength="120"
                           value="<?= $e((string)($input['nombre'] ?? '')) ?>" required
                           placeholder="ej: Supervisor de Campo">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control" maxlength="255"
                           value="<?= $e((string)($input['descripcion'] ?? '')) ?>"
                           placeholder="Breve descripción del rol">
                </div>
            </div>
        </div>
    </div>

    <!-- Permisos -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-key me-2"></i>Permisos</strong>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="toggleAll(true)">Marcar todos</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Desmarcar todos</button>
            </div>
        </div>
        <div class="card-body">
            <?php foreach ($catalogo as $recurso => $permisosRecurso): ?>
            <div class="mb-4">
                <h6 class="text-uppercase text-muted small mb-2 border-bottom pb-1">
                    <i class="bi bi-folder me-1"></i><?= $e($recurso) ?>
                    <button type="button" class="btn btn-link btn-sm py-0 ms-2" onclick="toggleGroup('<?= $e($recurso) ?>')">toggle</button>
                </h6>
                <div class="row g-2">
                    <?php foreach ($permisosRecurso as $p): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-check">
                            <input class="form-check-input perm-check" type="checkbox"
                                   name="permisos_ids[]" value="<?= $e($p['id']) ?>"
                                   id="perm_<?= $e($p['id']) ?>"
                                   data-recurso="<?= $e($recurso) ?>"
                                   <?= in_array($p['id'], $selectedPermisos, true) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="perm_<?= $e($p['id']) ?>">
                                <code><?= $e($p['codigo']) ?></code>
                                <?php if ($p['descripcion']): ?>
                                <br><span class="text-muted"><?= $e($p['descripcion']) ?></span>
                                <?php endif ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
            <?php endforeach ?>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="<?= $view->url('/roles') ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Crear rol</button>
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
