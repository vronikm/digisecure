<?php
/**
 * Edición de datos de la empresa (tenant).
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
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-pencil me-2"></i>Editar datos de la empresa</h4>
    <a href="<?= $view->url('/empresa') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $view->url('/empresa/editar') ?>" class="card shadow-sm">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
    <div class="card-body">
        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            El <strong>RUC</strong>, <strong>estado</strong>, <strong>plan</strong> y <strong>límites</strong> solo pueden ser modificados por el administrador del sistema.
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Razón social *</label>
                <input type="text" name="razon_social" class="form-control" maxlength="255"
                       value="<?= $e((string)($tenant['razon_social'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nombre comercial</label>
                <input type="text" name="nombre_comercial" class="form-control" maxlength="255"
                       value="<?= $e((string)($tenant['nombre_comercial'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">RUC</label>
                <input type="text" class="form-control" value="<?= $e((string)($tenant['ruc'] ?? '')) ?>" disabled>
            </div>
            <div class="col-md-4">
                <label class="form-label">Email de contacto *</label>
                <input type="email" name="email_contacto" class="form-control" maxlength="190"
                       value="<?= $e((string)($tenant['email_contacto'] ?? '')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" class="form-control" maxlength="30"
                       value="<?= $e((string)($tenant['telefono'] ?? '')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Dirección</label>
                <input type="text" name="direccion" class="form-control" maxlength="255"
                       value="<?= $e((string)($tenant['direccion'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Ciudad</label>
                <input type="text" name="ciudad" class="form-control" maxlength="100"
                       value="<?= $e((string)($tenant['ciudad'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Provincia</label>
                <input type="text" name="provincia" class="form-control" maxlength="100"
                       value="<?= $e((string)($tenant['provincia'] ?? '')) ?>">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="<?= $view->url('/empresa') ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar cambios</button>
    </div>
</form>
<?php $view->endSection() ?>
