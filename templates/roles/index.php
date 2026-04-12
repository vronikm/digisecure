<?php
/**
 * Listado de roles accesibles para el tenant.
 *
 * Variables: $user, $csrfToken, $currentPath, $roles
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Roles — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-shield-lock me-1"></i>Roles</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-lock me-2"></i>Roles y Permisos</h4>
        <p class="text-muted mb-0"><?= count($roles) ?> rol<?= count($roles) !== 1 ? 'es' : '' ?> disponible<?= count($roles) !== 1 ? 's' : '' ?></p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Rol</th>
                    <th>Código</th>
                    <th>Tipo</th>
                    <th class="text-center">Permisos</th>
                    <th class="text-center">Usuarios</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($roles === []): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No hay roles disponibles.
                    </td>
                </tr>
                <?php endif ?>
                <?php foreach ($roles as $rol): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= $e($rol['nombre']) ?></div>
                        <?php if ($rol['descripcion']): ?>
                        <small class="text-muted"><?= $e($rol['descripcion']) ?></small>
                        <?php endif ?>
                    </td>
                    <td><code><?= $e($rol['codigo']) ?></code></td>
                    <td>
                        <?php if ((int) $rol['es_sistema'] === 1): ?>
                        <span class="badge bg-primary"><i class="bi bi-gear-fill me-1"></i>Sistema</span>
                        <?php else: ?>
                        <span class="badge bg-info text-dark">Empresa</span>
                        <?php endif ?>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?= (int) $rol['permisos_count'] ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-light text-dark border"><?= (int) $rol['usuarios_count'] ?></span>
                    </td>
                    <td class="text-end">
                        <a href="<?= $view->url('/roles/' . $rol['id']) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info mt-4">
    <i class="bi bi-info-circle me-2"></i>
    Los roles del sistema son comunes a toda la plataforma y no pueden modificarse.
    En esta versión los roles son de solo lectura — la creación y edición de roles
    propios del tenant llegará en una próxima fase.
</div>
<?php $view->endSection() ?>
