<?php
/**
 * Detalle de un rol con sus permisos asignados.
 *
 * Variables: $user, $csrfToken, $currentPath, $rol, $permisos, $catalogo
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$asignados = [];
foreach ($permisos as $p) {
    $asignados[$p['codigo']] = true;
}
?>

<?php $view->section('title') ?>Rol <?= $e($rol['nombre']) ?> — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/roles') ?>">Roles</a></li>
<li class="breadcrumb-item active"><?= $e($rol['nombre']) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-shield-lock"></i></span>
        <div>
            <h1><?= $e($rol['nombre']) ?></h1>
            <small style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <span class="ds-cell-mono"><?= $e($rol['codigo']) ?></span>
                <?php if ((int) $rol['es_sistema'] === 1): ?>
                    <span class="ds-badge tone-purple"><i class="bi bi-gear-fill"></i>Sistema</span>
                <?php else: ?>
                    <span class="ds-badge tone-info">Empresa</span>
                <?php endif ?>
                <span class="ds-chip"><i class="bi bi-key"></i><?= count($permisos) ?> permiso<?= count($permisos) !== 1 ? 's' : '' ?></span>
            </small>
        </div>
    </div>
    <div style="display: flex; gap: .5rem;">
        <?php if ((int) $rol['es_sistema'] === 0 && $user->tienePermiso('roles.editar')): ?>
        <a href="<?= $view->url('/roles/' . $e((string) $rol['id']) . '/editar') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
            <i class="bi bi-pencil"></i>Editar
        </a>
        <?php endif ?>
        <a href="<?= $view->url('/roles') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Volver
        </a>
    </div>
</div>

<?php if (!empty($rol['descripcion'])): ?>
<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Descripcion del rol</strong>
        <?= $e($rol['descripcion']) ?>
    </div>
</div>
<?php endif ?>

<div class="ds-card">
    <div class="ds-card-header">
        <span class="ds-card-mark"><i class="bi bi-key"></i></span>
        <div>
            <div class="ds-card-title">Permisos del catalogo</div>
            <div class="ds-card-subtitle">Los marcados estan asignados a este rol. Los demas muestran el alcance total del sistema.</div>
        </div>
    </div>
    <div class="ds-card-body">
        <?php foreach ($catalogo as $recurso => $permisosRecurso): ?>
        <div style="margin-bottom: 1.25rem;">
            <h6 style="font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--ds-text-soft); margin: 0 0 .65rem; padding-bottom: .35rem; border-bottom: 1px solid #eef1f5;">
                <i class="bi bi-folder me-1"></i><?= $e($recurso) ?>
            </h6>
            <div class="row g-2">
                <?php foreach ($permisosRecurso as $p):
                    $tiene = isset($asignados[$p['codigo']]);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div style="display: flex; gap: .55rem; align-items: flex-start; padding: .65rem .75rem; border-radius: 10px; background: <?= $tiene ? 'rgba(5,150,105,.08)' : '#f4f7fb' ?>; border: 1px solid <?= $tiene ? 'rgba(5,150,105,.18)' : '#e2e8f0' ?>;">
                        <i class="bi <?= $tiene ? 'bi-check-circle-fill' : 'bi-circle' ?>" style="color: <?= $tiene ? '#059669' : '#94a3b8' ?>; font-size: 1rem; margin-top: .15rem; flex-shrink: 0;"></i>
                        <div style="flex: 1; min-width: 0;">
                            <span class="ds-cell-mono" style="font-weight: 600; color: <?= $tiene ? '#047857' : 'var(--ds-text)' ?>;"><?= $e($p['codigo']) ?></span>
                            <?php if (!empty($p['descripcion'])): ?>
                            <div class="ds-cell-sub" style="margin-top: .15rem;"><?= $e($p['descripcion']) ?></div>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
                <?php endforeach ?>
            </div>
        </div>
        <?php endforeach ?>
    </div>
</div>
<?php $view->endSection() ?>
