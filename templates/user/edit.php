<?php
/**
 * Formulario de edicion de usuario.
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

<?php $view->section('title') ?>Editar usuario - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/usuarios') ?>">Usuarios</a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-pencil-square"></i></span>
        <div>
            <h1>Editar usuario</h1>
            <small><?= $e($usuario['apellidos']) ?> <?= $e($usuario['nombres']) ?> &middot; <span class="ds-cell-mono"><?= $e($usuario['email']) ?></span></small>
        </div>
    </div>
    <a href="<?= $view->url('/usuarios') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo guardar el usuario</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="ds-form-wrap ds-user-form-wrap">
<form method="post" action="<?= $view->url('/usuarios/' . $usuario['id'] . '/editar') ?>" class="ds-form-main">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-person"></i></span>
            <div>
                <div class="ds-card-title">Datos del usuario</div>
                <div class="ds-card-subtitle">Informacion personal y de contacto</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="ds-user-grid">
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombres <span class="ds-required">*</span></label>
                        <input type="text" name="nombres" class="form-control" required maxlength="120"
                               value="<?= $e($valor('nombres')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Apellidos <span class="ds-required">*</span></label>
                        <input type="text" name="apellidos" class="form-control" required maxlength="120"
                               value="<?= $e($valor('apellidos')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Cargo</label>
                        <input type="text" name="cargo" class="form-control" maxlength="120"
                               value="<?= $e($valor('cargo')) ?>">
                    </div>
                </div>

                <div class="ds-col-12 ds-col-md-6 ds-col-lg-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Email <span class="ds-required">*</span></label>
                        <input type="email" name="email" class="form-control" required maxlength="190"
                               value="<?= $e($valor('email')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="30"
                               value="<?= $e($valor('telefono')) ?>">
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Rol asignado</label>
                        <select name="role_id" class="form-select">
                            <option value="">- Mantener actual -</option>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $e($r['id']) ?>" <?= ($input['role_id'] ?? $rolActualId) === $r['id'] ? 'selected' : '' ?>>
                                <?= $e($r['nombre']) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                        <span class="ds-form-help">Reemplaza todos los roles del usuario por el seleccionado.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/usuarios') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Guardar cambios
        </button>
    </div>
</form>

<?php if ($user->tienePermiso('usuarios.editar')): ?>
<div class="ds-card" style="border-left: 4px solid #f59e0b;">
    <div class="ds-card-header tone-warning">
        <span class="ds-card-mark"><i class="bi bi-shield-lock"></i></span>
        <div>
            <div class="ds-card-title">Resetear contrasena</div>
            <div class="ds-card-subtitle">Establece una contrasena temporal; el usuario debera cambiarla al iniciar sesion</div>
        </div>
    </div>
    <div class="ds-card-body">
        <form class="ds-form-main" method="post" action="<?= $view->url('/usuarios/' . $usuario['id'] . '/password') ?>"
              onsubmit="return confirm('Resetear la contrasena de este usuario?');">
            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
            <div class="ds-user-grid">
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-8">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nueva contrasena temporal</label>
                        <input type="text" name="new_password" class="form-control"
                               placeholder="Minimo 8 caracteres" required minlength="8">
                        <span class="ds-form-help">Anota la contrasena para entregarla al usuario por un canal seguro.</span>
                    </div>
                </div>
                <div class="ds-col-12 ds-col-md-6 ds-col-lg-4" style="align-self: end;">
                    <button type="submit" class="ds-btn ds-btn-danger" style="width: 100%; justify-content: center;">
                        <i class="bi bi-key"></i>Resetear
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif ?>
</div>
<?php $view->endSection() ?>
