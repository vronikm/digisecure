<?php
/**
 * Edicion de datos de la empresa (tenant).
 *
 * Variables: $user, $csrfToken, $currentPath, $tenant, $error?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Editar empresa — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/empresa') ?>">Empresa</a></li>
<li class="breadcrumb-item active">Editar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-pencil-square"></i></span>
        <div>
            <h1>Editar datos de la empresa</h1>
            <small><?= $e((string) ($tenant['razon_social'] ?? '')) ?> &middot; <span class="ds-cell-mono">RUC <?= $e((string) ($tenant['ruc'] ?? '')) ?></span></small>
        </div>
    </div>
    <a href="<?= $view->url('/empresa') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver
    </a>
</div>

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

<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Campos protegidos</strong>
        El RUC, estado, plan y limites solo pueden ser modificados por el administrador de la plataforma.
    </div>
</div>

<form method="post" action="<?= $view->url('/empresa/editar') ?>" class="ds-form-main">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-building"></i></span>
            <div>
                <div class="ds-card-title">Identidad legal</div>
                <div class="ds-card-subtitle">Razon social y datos del SRI</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Razon social <span class="ds-required">*</span></label>
                        <input type="text" name="razon_social" class="form-control" maxlength="255"
                               value="<?= $e((string) ($tenant['razon_social'] ?? '')) ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre comercial</label>
                        <input type="text" name="nombre_comercial" class="form-control" maxlength="255"
                               value="<?= $e((string) ($tenant['nombre_comercial'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">RUC</label>
                        <input type="text" class="form-control" value="<?= $e((string) ($tenant['ruc'] ?? '')) ?>" disabled>
                        <span class="ds-form-help">Solo modificable por el administrador del sistema.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-success">
            <span class="ds-card-mark"><i class="bi bi-envelope-at"></i></span>
            <div>
                <div class="ds-card-title">Contacto</div>
                <div class="ds-card-subtitle">Por donde te localizamos</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Email de contacto <span class="ds-required">*</span></label>
                        <input type="email" name="email_contacto" class="form-control" maxlength="190"
                               value="<?= $e((string) ($tenant['email_contacto'] ?? '')) ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="30"
                               value="<?= $e((string) ($tenant['telefono'] ?? '')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-geo-alt"></i></span>
            <div>
                <div class="ds-card-title">Domicilio</div>
                <div class="ds-card-subtitle">Direccion fiscal de la empresa</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Direccion</label>
                        <input type="text" name="direccion" class="form-control" maxlength="255"
                               value="<?= $e((string) ($tenant['direccion'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control" maxlength="100"
                               value="<?= $e((string) ($tenant['ciudad'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Provincia</label>
                        <input type="text" name="provincia" class="form-control" maxlength="100"
                               value="<?= $e((string) ($tenant['provincia'] ?? '')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/empresa') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Guardar cambios
        </button>
    </div>
</form>
<?php $view->endSection() ?>
