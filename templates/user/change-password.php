<?php
/**
 * Formulario de cambio de contrasena.
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

<?php $view->section('title') ?>Cambiar contrasena — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/dashboard') ?>">Dashboard</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/perfil') ?>">Mi perfil</a></li>
<li class="breadcrumb-item active">Cambiar contrasena</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-key"></i></span>
        <div>
            <h1>Cambiar contrasena</h1>
            <small>Actualiza la contrasena de tu cuenta</small>
        </div>
    </div>
    <a href="<?= $view->url('/perfil') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al perfil
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <?php if ($error !== null): ?>
        <div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
            <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
                <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
                <div>
                    <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo cambiar la contrasena</div>
                    <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
                </div>
            </div>
        </div>
        <?php endif ?>

        <?php if ($success !== null): ?>
        <div class="ds-card" style="border-left: 4px solid #059669; margin-bottom: 1rem;">
            <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
                <span style="color: #059669; font-size: 1.25rem; line-height: 1;"><i class="bi bi-check-circle-fill"></i></span>
                <div>
                    <div style="font-weight: 700; color: #047857; margin-bottom: .15rem;">Contrasena actualizada</div>
                    <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($success) ?></div>
                </div>
            </div>
        </div>
        <?php endif ?>

        <form method="POST" action="<?= $view->url('/perfil/password') ?>" novalidate>
            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

            <div class="ds-card">
                <div class="ds-card-header">
                    <span class="ds-card-mark"><i class="bi bi-shield-lock"></i></span>
                    <div>
                        <div class="ds-card-title">Verificacion de identidad</div>
                        <div class="ds-card-subtitle">Confirma con tu contrasena actual</div>
                    </div>
                </div>
                <div class="ds-card-body">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="current_password">Contrasena actual <span class="ds-required">*</span></label>
                        <input type="password" class="form-control" id="current_password"
                               name="current_password" required autocomplete="current-password">
                    </div>
                </div>
            </div>

            <div class="ds-card">
                <div class="ds-card-header tone-warning">
                    <span class="ds-card-mark"><i class="bi bi-key-fill"></i></span>
                    <div>
                        <div class="ds-card-title">Nueva contrasena</div>
                        <div class="ds-card-subtitle">Elige una contrasena segura y unica</div>
                    </div>
                </div>
                <div class="ds-card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="ds-form-field">
                                <label class="ds-form-label" for="new_password">Nueva contrasena <span class="ds-required">*</span></label>
                                <input type="password" class="form-control" id="new_password"
                                       name="new_password" required autocomplete="new-password"
                                       minlength="8">
                                <span class="ds-form-help">Minimo 8 caracteres. Debe incluir mayusculas, minusculas y numeros.</span>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="ds-form-field">
                                <label class="ds-form-label" for="confirm_password">Confirmar nueva contrasena <span class="ds-required">*</span></label>
                                <input type="password" class="form-control" id="confirm_password"
                                       name="confirm_password" required autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ds-form-actions">
                <a href="<?= $view->url('/perfil') ?>" class="ds-btn ds-btn-outline">
                    <i class="bi bi-arrow-left"></i>Cancelar
                </a>
                <button type="submit" class="ds-btn ds-btn-primary">
                    <i class="bi bi-check-lg"></i>Actualizar contrasena
                </button>
            </div>
        </form>
    </div>
</div>
<?php $view->endSection() ?>
