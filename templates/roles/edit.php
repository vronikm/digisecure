<?php
/**
 * Formulario de edición de rol + asignación de permisos.
 *
 * Variables: $user, $csrfToken, $currentPath, $rol, $catalogo, $asignadoIds, $error?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$esSistema = (int)($rol['es_sistema'] ?? 0) === 1;
?>

<?php $view->section('title') ?>Editar rol — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/roles') ?>">Roles</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/roles/' . $e((string)$rol['id'])) ?>"><?= $e($rol['nombre']) ?></a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-pencil me-2"></i>Editar rol: <?= $e($rol['nombre']) ?></h4>
    <div class="d-flex gap-2">
        <a href="<?= $view->url('/roles/' . $e((string)$rol['id'])) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?php if ($esSistema): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Los roles del sistema no pueden editarse. Solo puede ver sus permisos.
</div>
<?php endif ?>

<?php if (!empty($error ?? null)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $view->url('/roles/' . $e((string)$rol['id']) . '/editar') ?>">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <!-- Datos básicos -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Datos del rol</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Código</label>
                    <input type="text" class="form-control" value="<?= $e((string)$rol['codigo']) ?>" disabled>
                    <div class="form-text">El código no se puede cambiar una vez creado.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" maxlength="120"
                           value="<?= $e((string)$rol['nombre']) ?>" required
                           <?= $esSistema ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control" maxlength="255"
                           value="<?= $e((string)($rol['descripcion'] ?? '')) ?>"
                           <?= $esSistema ? 'disabled' : '' ?>>
                </div>
            </div>
        </div>
    </div>

    <!-- Permisos -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-key me-2"></i>Permisos asignados</strong>
            <?php if (!$esSistema): ?>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="toggleAll(true)">Marcar todos</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Desmarcar todos</button>
            </div>
            <?php endif ?>
        </div>
        <div class="card-body">
            <?php foreach ($catalogo as $recurso => $permisosRecurso): ?>
            <div class="mb-4">
                <h6 class="text-uppercase text-muted small mb-2 border-bottom pb-1">
                    <i class="bi bi-folder me-1"></i><?= $e($recurso) ?>
                    <?php if (!$esSistema): ?>
                    <button type="button" class="btn btn-link btn-sm py-0 ms-2" onclick="toggleGroup('<?= $e($recurso) ?>')">toggle</button>
                    <?php endif ?>
                </h6>
                <div class="row g-2">
                    <?php foreach ($permisosRecurso as $p): ?>
                    <?php $asignado = in_array($p['id'], $asignadoIds, true); ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-check">
                            <input class="form-check-input perm-check" type="checkbox"
                                   name="permisos_ids[]" value="<?= $e($p['id']) ?>"
                                   id="perm_<?= $e($p['id']) ?>"
                                   data-recurso="<?= $e($recurso) ?>"
                                   <?= $asignado ? 'checked' : '' ?>
                                   <?= $esSistema ? 'disabled' : '' ?>>
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

    <?php if (!$esSistema): ?>
    <div class="d-flex justify-content-between">
        <form method="post" action="<?= $view->url('/roles/' . $e((string)$rol['id']) . '/eliminar') ?>" class="d-inline"
              onsubmit="return confirm('¿Está seguro de eliminar el rol «<?= $e($rol['nombre']) ?>»? Esta acción no se puede deshacer.')">
            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i>Eliminar rol
            </button>
        </form>
        <div class="d-flex gap-2">
            <a href="<?= $view->url('/roles/' . $e((string)$rol['id'])) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar cambios</button>
        </div>
    </div>
    <?php endif ?>
</form>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script>
function toggleAll(state) {
    document.querySelectorAll('.perm-check:not(:disabled)').forEach(cb => cb.checked = state);
}
function toggleGroup(recurso) {
    const cbs = document.querySelectorAll('.perm-check:not(:disabled)[data-recurso="' + recurso + '"]');
    const allChecked = Array.from(cbs).every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
}
</script>
<?php $view->endSection() ?>
