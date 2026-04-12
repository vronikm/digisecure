<?php
/**
 * Formulario de edición de usuario.
 *
 * Variables: $user, $csrfToken, $currentPath, $usuario, $roles, $rolActualId, $input
 * Opcionales: $error
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$valor = static fn(string $key, mixed $default = '') => $input[$key] ?? $usuario[$key] ?? $default;
?>

<?php $view->section('title') ?>Editar usuario — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/usuarios') ?>">Usuarios</a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-pencil me-2"></i>Editar usuario</h4>
        <p class="text-muted mb-0"><?= $e($usuario['apellidos']) ?> <?= $e($usuario['nombres']) ?></p>
    </div>
    <a href="<?= $view->url('/usuarios') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($error) ?></div>
<?php endif ?>
<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($_GET['error']) ?></div>
<?php endif ?>

<form method="post" action="<?= $view->url('/usuarios/' . $usuario['id'] . '/editar') ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-person me-2"></i>Datos del usuario</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nombres <span class="text-danger">*</span></label>
                    <input type="text" name="nombres" class="form-control" required maxlength="120"
                           value="<?= $e($valor('nombres')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                    <input type="text" name="apellidos" class="form-control" required maxlength="120"
                           value="<?= $e($valor('apellidos')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required maxlength="190"
                           value="<?= $e($valor('email')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cargo</label>
                    <input type="text" name="cargo" class="form-control" maxlength="120"
                           value="<?= $e($valor('cargo')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" maxlength="30"
                           value="<?= $e($valor('telefono')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rol asignado</label>
                    <select name="role_id" class="form-select">
                        <option value="">— Mantener actual —</option>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= $e($r['id']) ?>" <?= ($input['role_id'] ?? $rolActualId) === $r['id'] ? 'selected' : '' ?>>
                            <?= $e($r['nombre']) ?>
                        </option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-text">Reemplaza todos los roles del usuario por el seleccionado.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= $view->url('/usuarios') ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Guardar cambios
        </button>
    </div>
</form>

<?php if ($user->tienePermiso('usuarios.editar')): ?>
<div class="card shadow-sm border-warning mb-5">
    <div class="card-header bg-warning bg-opacity-10">
        <i class="bi bi-shield-lock me-2"></i>Resetear contraseña
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Establece una nueva contraseña para este usuario. Será obligado a cambiarla en su próximo login.
        </p>
        <form method="post" action="<?= $view->url('/usuarios/' . $usuario['id'] . '/password') ?>" class="row g-2"
              onsubmit="return confirm('¿Resetear la contraseña de este usuario?');">
            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
            <div class="col-md-6">
                <input type="text" name="new_password" class="form-control" placeholder="Nueva contraseña" required minlength="8">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-warning w-100">
                    <i class="bi bi-key me-1"></i>Resetear
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif ?>
<?php $view->endSection() ?>
