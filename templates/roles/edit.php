<?php
/**
 * Formulario de edicion de rol + asignacion de permisos.
 *
 * Variables: $user, $csrfToken, $currentPath, $rol, $catalogo, $asignadoIds, $error?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$esSistema = (int) ($rol['es_sistema'] ?? 0) === 1;
?>

<?php $view->section('title') ?>Editar rol - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/roles') ?>">Roles</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/roles/' . $e((string) $rol['id'])) ?>"><?= $e($rol['nombre']) ?></a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-pencil-square"></i></span>
        <div>
            <h1>Editar rol: <?= $e($rol['nombre']) ?></h1>
            <small><span class="ds-cell-mono"><?= $e($rol['codigo']) ?></span></small>
        </div>
    </div>
    <a href="<?= $view->url('/roles/' . $e((string) $rol['id'])) ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al detalle
    </a>
</div>

<?php if ($esSistema): ?>
<div class="ds-card" style="border-left: 4px solid #d97706; margin-bottom: 1.25rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #d97706; font-size: 1.25rem; line-height: 1;"><i class="bi bi-shield-lock"></i></span>
        <div>
            <div style="font-weight: 700; color: #b45309; margin-bottom: .15rem;">Rol del sistema &mdash; solo lectura</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;">Los roles del sistema no pueden editarse. Solo puedes ver sus permisos asignados.</div>
        </div>
    </div>
</div>
<?php endif ?>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudieron guardar los cambios</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<form class="ds-form-main" method="post" action="<?= $view->url('/roles/' . $e((string) $rol['id']) . '/editar') ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

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
                        <label class="ds-form-label">Codigo</label>
                        <input type="text" class="form-control" value="<?= $e((string) $rol['codigo']) ?>" disabled>
                        <span class="ds-form-help">El codigo no se puede cambiar una vez creado.</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre <span class="ds-required">*</span></label>
                        <input type="text" name="nombre" class="form-control" maxlength="120"
                               value="<?= $e((string) $rol['nombre']) ?>" required
                               <?= $esSistema ? 'disabled' : '' ?>>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Descripcion</label>
                        <input type="text" name="descripcion" class="form-control" maxlength="255"
                               value="<?= $e((string) ($rol['descripcion'] ?? '')) ?>"
                               <?= $esSistema ? 'disabled' : '' ?>>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-key"></i></span>
            <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                <div>
                    <div class="ds-card-title">Permisos asignados</div>
                    <div class="ds-card-subtitle"><?= count($asignadoIds) ?> permiso<?= count($asignadoIds) !== 1 ? 's' : '' ?> seleccionado<?= count($asignadoIds) !== 1 ? 's' : '' ?></div>
                </div>
                <?php if (!$esSistema): ?>
                <div style="display: flex; gap: .35rem;">
                    <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" onclick="toggleAll(true)">
                        <i class="bi bi-check-all"></i>Marcar todos
                    </button>
                    <button type="button" class="ds-btn ds-btn-ghost ds-btn-sm" onclick="toggleAll(false)">
                        <i class="bi bi-x-lg"></i>Desmarcar
                    </button>
                </div>
                <?php endif ?>
            </div>
        </div>
        <div class="ds-card-body">
            <?php foreach ($catalogo as $recurso => $permisosRecurso): ?>
            <div style="margin-bottom: 1.25rem;">
                <h6 style="font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--ds-text-soft); margin: 0 0 .65rem; padding-bottom: .35rem; border-bottom: 1px solid #eef1f5; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="bi bi-folder me-1"></i><?= $e($recurso) ?></span>
                    <?php if (!$esSistema): ?>
                    <button type="button"
                            style="background: transparent; border: 0; color: #2563eb; font-size: .72rem; font-weight: 600; cursor: pointer; padding: 0;"
                            onclick="toggleGroup('<?= $e($recurso) ?>')">
                        Toggle grupo
                    </button>
                    <?php endif ?>
                </h6>
                <div class="row g-2">
                    <?php foreach ($permisosRecurso as $p):
                        $asignado = in_array($p['id'], $asignadoIds, true);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <label for="perm_<?= $e($p['id']) ?>"
                               style="display: flex; gap: .55rem; align-items: flex-start; padding: .65rem .75rem; border-radius: 10px; background: <?= $asignado ? 'rgba(5,150,105,.08)' : '#f4f7fb' ?>; border: 1px solid <?= $asignado ? 'rgba(5,150,105,.18)' : '#e2e8f0' ?>; cursor: <?= $esSistema ? 'default' : 'pointer' ?>; transition: background .12s; <?= $esSistema ? 'opacity: .7;' : '' ?>">
                            <input class="perm-check" type="checkbox"
                                   name="permisos_ids[]" value="<?= $e($p['id']) ?>"
                                   id="perm_<?= $e($p['id']) ?>"
                                   data-recurso="<?= $e($recurso) ?>"
                                   <?= $asignado ? 'checked' : '' ?>
                                   <?= $esSistema ? 'disabled' : '' ?>
                                   style="margin-top: .15rem; flex-shrink: 0; accent-color: #2f80ed;">
                            <span style="flex: 1; min-width: 0;">
                                <span class="ds-cell-mono" style="font-weight: 600; color: <?= $asignado ? '#047857' : 'var(--ds-text)' ?>;"><?= $e($p['codigo']) ?></span>
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

    <?php if (!$esSistema): ?>
    <div class="ds-form-actions is-split">
        <form class="ds-form-main" method="post" action="<?= $view->url('/roles/' . $e((string) $rol['id']) . '/eliminar') ?>"
              onsubmit="return confirm('Estas seguro de eliminar el rol «<?= $e($rol['nombre']) ?>»? Esta accion no se puede deshacer.');"
              style="display: inline;">
            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
            <button type="submit" class="ds-btn ds-btn-outline" style="color: #dc2626; border-color: rgba(220,38,38,.3);">
                <i class="bi bi-trash"></i>Eliminar rol
            </button>
        </form>
        <div style="display: flex; gap: .55rem;">
            <a href="<?= $view->url('/roles/' . $e((string) $rol['id'])) ?>" class="ds-btn ds-btn-outline">Cancelar</a>
            <button type="submit" class="ds-btn ds-btn-primary">
                <i class="bi bi-check-lg"></i>Guardar cambios
            </button>
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
