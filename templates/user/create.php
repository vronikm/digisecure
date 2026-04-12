<?php
/**
 * Formulario de creación de usuario.
 *
 * Variables: $user, $csrfToken, $currentPath, $roles, $input
 * Opcionales: $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Nuevo usuario — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/usuarios') ?>">Usuarios</a></li>
<li class="breadcrumb-item active">Nuevo</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Nuevo usuario</h4>
    <a href="<?= $view->url('/usuarios') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $view->url('/usuarios/crear') ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-person me-2"></i>Datos del usuario</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nombres <span class="text-danger">*</span></label>
                    <input type="text" name="nombres" class="form-control" required maxlength="120"
                           value="<?= $e($input['nombres'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                    <input type="text" name="apellidos" class="form-control" required maxlength="120"
                           value="<?= $e($input['apellidos'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required maxlength="190"
                           value="<?= $e($input['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cargo</label>
                    <input type="text" name="cargo" class="form-control" maxlength="120"
                           value="<?= $e($input['cargo'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" maxlength="30"
                           value="<?= $e($input['telefono'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Estado inicial</label>
                    <select name="estado" class="form-select">
                        <?php foreach (['activo','pendiente_verificacion','inactivo'] as $est): ?>
                        <option value="<?= $est ?>" <?= ($input['estado'] ?? 'activo') === $est ? 'selected' : '' ?>>
                            <?= $e(ucfirst(str_replace('_', ' ', $est))) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-key me-2"></i>Credenciales y rol</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Contraseña inicial <span class="text-danger">*</span></label>
                    <input type="text" name="password" class="form-control" required minlength="8"
                           value="<?= $e($input['password'] ?? '') ?>">
                    <div class="form-text">Mínimo 8 caracteres. El usuario deberá cambiarla en su próximo login.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rol asignado <span class="text-danger">*</span></label>
                    <select name="role_id" class="form-select" required>
                        <option value="">— Seleccione —</option>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= $e($r['id']) ?>" <?= ($input['role_id'] ?? '') === $r['id'] ? 'selected' : '' ?>>
                            <?= $e($r['nombre']) ?>
                            <?php if ($r['descripcion']): ?> — <?= $e($r['descripcion']) ?><?php endif ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="<?= $view->url('/usuarios') ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Crear usuario
        </button>
    </div>
</form>
<?php $view->endSection() ?>
