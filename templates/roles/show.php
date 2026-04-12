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

// Indexar permisos asignados por código para chequeos rápidos
$asignados = [];
foreach ($permisos as $p) {
    $asignados[$p['codigo']] = true;
}

// Agrupar permisos asignados por recurso para resumen
$asignadosByRecurso = [];
foreach ($permisos as $p) {
    $asignadosByRecurso[$p['recurso']][] = $p;
}
?>

<?php $view->section('title') ?>Rol <?= $e($rol['nombre']) ?> — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/roles') ?>">Roles</a></li>
<li class="breadcrumb-item active"><?= $e($rol['nombre']) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-shield-lock me-2"></i><?= $e($rol['nombre']) ?>
        </h4>
        <div class="d-flex gap-2 align-items-center">
            <code><?= $e($rol['codigo']) ?></code>
            <?php if ((int) $rol['es_sistema'] === 1): ?>
            <span class="badge bg-primary"><i class="bi bi-gear-fill me-1"></i>Rol del sistema</span>
            <?php else: ?>
            <span class="badge bg-info text-dark">Rol de empresa</span>
            <?php endif ?>
            <span class="badge bg-secondary"><?= count($permisos) ?> permiso<?= count($permisos) !== 1 ? 's' : '' ?></span>
        </div>
    </div>
    <a href="<?= $view->url('/roles') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if ($rol['descripcion']): ?>
<div class="alert alert-light border">
    <i class="bi bi-info-circle me-2"></i><?= $e($rol['descripcion']) ?>
</div>
<?php endif ?>

<div class="card shadow-sm">
    <div class="card-header"><i class="bi bi-key me-2"></i>Permisos del catálogo</div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Marcados los permisos que están asignados a este rol. Los demás aparecen para mostrar el alcance total
            del catálogo de permisos del sistema.
        </p>
        <?php foreach ($catalogo as $recurso => $permisosRecurso): ?>
        <div class="mb-3">
            <h6 class="text-uppercase text-muted small mb-2"><?= $e($recurso) ?></h6>
            <div class="row g-2">
                <?php foreach ($permisosRecurso as $p): ?>
                <?php $tiene = isset($asignados[$p['codigo']]); ?>
                <div class="col-md-6 col-lg-4">
                    <div class="d-flex align-items-start gap-2 p-2 rounded <?= $tiene ? 'bg-success bg-opacity-10' : 'bg-light' ?>">
                        <i class="bi <?= $tiene ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' ?> mt-1"></i>
                        <div>
                            <code class="small"><?= $e($p['codigo']) ?></code>
                            <?php if ($p['descripcion']): ?>
                            <div class="small text-muted"><?= $e($p['descripcion']) ?></div>
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
