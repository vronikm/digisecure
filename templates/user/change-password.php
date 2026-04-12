<?php
/**
 * Formulario de cambio de contraseña.
 *
 * Variables: $user, $csrfToken, $currentPath, $error?, $success?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Security\Auth\AuthenticatedUser $user */

$view->layout('layout/app');
$e = ViewRenderer::e(...);
$error = $error ?? null;
$success = $success ?? null;
?>

<?php $view->section('title') ?>Cambiar contraseña — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/perfil') ?>">Mi perfil</a></li>
<li class="breadcrumb-item active">Cambiar contraseña</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <h4 class="mb-4"><i class="bi bi-key me-2"></i>Cambiar contraseña</h4>

        <?php if ($error !== null): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $e($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success !== null): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= $e($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="<?= $view->url('/perfil/password') ?>" novalidate>
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña actual</label>
                        <input type="password" class="form-control" id="current_password"
                               name="current_password" required autocomplete="current-password">
                    </div>

                    <hr class="my-3">

                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva contraseña</label>
                        <input type="password" class="form-control" id="new_password"
                               name="new_password" required autocomplete="new-password"
                               minlength="8">
                        <div class="form-text">
                            Mínimo 8 caracteres. Debe incluir mayúsculas, minúsculas y números.
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" class="form-control" id="confirm_password"
                               name="confirm_password" required autocomplete="new-password">
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="<?= $view->url('/perfil') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Volver
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Cambiar contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
