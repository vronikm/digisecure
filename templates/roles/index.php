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
$total = count($roles);
?>

<?php $view->section('title') ?>Roles — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-shield-lock me-1"></i>Roles</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-shield-lock"></i></span>
        <div>
            <h1>Roles y permisos</h1>
            <small><?= $total ?> rol<?= $total !== 1 ? 'es' : '' ?> disponible<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <?php if ($user->tienePermiso('roles.crear')): ?>
    <a href="<?= $view->url('/roles/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Crear rol
    </a>
    <?php endif ?>
</div>

<?php if ($roles === []): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-shield-lock"></i></div>
        <h3>No hay roles disponibles</h3>
        <p>Crea tu primer rol para definir los permisos que se asignaran a los usuarios.</p>
        <?php if ($user->tienePermiso('roles.crear')): ?>
        <a href="<?= $view->url('/roles/crear') ?>" class="ds-btn ds-btn-primary">
            <i class="bi bi-plus-lg"></i>Crear el primer rol
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
                    <th>Rol</th>
                    <th>Codigo</th>
                    <th>Tipo</th>
                    <th class="ds-cell-end">Permisos</th>
                    <th class="ds-cell-end">Usuarios</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $rol): ?>
                <tr>
                    <td>
                        <div class="ds-cell-name"><?= $e($rol['nombre']) ?></div>
                        <?php if (!empty($rol['descripcion'])): ?>
                        <div class="ds-cell-sub"><?= $e($rol['descripcion']) ?></div>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-cell-mono"><?= $e($rol['codigo']) ?></span></td>
                    <td>
                        <?php if ((int) $rol['es_sistema'] === 1): ?>
                            <span class="ds-badge tone-purple"><i class="bi bi-gear-fill"></i>Sistema</span>
                        <?php else: ?>
                            <span class="ds-badge tone-info">Empresa</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <span class="ds-chip"><i class="bi bi-key"></i><?= (int) $rol['permisos_count'] ?></span>
                    </td>
                    <td class="ds-cell-end">
                        <span class="ds-chip"><i class="bi bi-people"></i><?= (int) $rol['usuarios_count'] ?></span>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/roles/' . $rol['id']) ?>" class="ds-icon-btn" title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ((int) $rol['es_sistema'] === 0 && $user->tienePermiso('roles.editar')): ?>
                            <a href="<?= $view->url('/roles/' . $rol['id'] . '/editar') ?>" class="ds-icon-btn" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif ?>
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
