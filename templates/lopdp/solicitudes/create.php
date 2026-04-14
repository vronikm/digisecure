<?php
/**
 * Formulario de registro de solicitud ARCO-P.
 *
 * Variables: $user, $csrfToken, $currentPath, $input, $error?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Nueva solicitud ARCO-P — LOPDP<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp') ?>">LOPDP</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp/solicitudes') ?>">Solicitudes</a></li>
<li class="breadcrumb-item active">Nueva</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Nueva solicitud ARCO-P</h4>
    <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= $e($error) ?></div>
<?php endif ?>

<div class="alert alert-info">
    <strong><i class="bi bi-info-circle me-1"></i>Plazo legal:</strong> el plazo de respuesta es de
    <strong>15 días calendario</strong> desde la fecha de recepción (Art. 32 LOPDP).
</div>

<form method="post" action="<?= $view->url('/lopdp/solicitudes/crear') ?>" class="card shadow-sm">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
    <div class="card-body">
        <h6 class="text-muted text-uppercase small mb-3">Tipo y canal</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Tipo de derecho ARCO-P *</label>
                <select name="tipo" class="form-select" required>
                    <option value="">— Seleccione —</option>
                    <?php foreach (['acceso','rectificacion','cancelacion','oposicion','portabilidad'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($input['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Canal de recepción *</label>
                <select name="canal_recepcion" class="form-select" required>
                    <?php foreach (['email','telefono','presencial','formulario_web'] as $c): ?>
                    <option value="<?= $c ?>" <?= ($input['canal_recepcion'] ?? 'email') === $c ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $c)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha de recepción *</label>
                <input type="date" name="fecha_recepcion" class="form-control"
                       value="<?= $e($input['fecha_recepcion'] ?? date('Y-m-d')) ?>" required>
            </div>
        </div>

        <h6 class="text-muted text-uppercase small mb-3">Datos del titular</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Tipo de titular *</label>
                <select name="titular_tipo" class="form-select" required>
                    <option value="">— Seleccione —</option>
                    <?php foreach (['guardia','usuario','externo'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($input['titular_tipo'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-9">
                <label class="form-label">Nombre completo *</label>
                <input type="text" name="titular_nombre" class="form-control" maxlength="200"
                       value="<?= $e($input['titular_nombre'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cédula</label>
                <input type="text" name="titular_cedula" class="form-control" maxlength="20"
                       value="<?= $e($input['titular_cedula'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" name="titular_email" class="form-control" maxlength="190"
                       value="<?= $e($input['titular_email'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <input type="text" name="titular_telefono" class="form-control" maxlength="30"
                       value="<?= $e($input['titular_telefono'] ?? '') ?>">
            </div>
        </div>

        <h6 class="text-muted text-uppercase small mb-3">Descripción</h6>
        <div class="mb-3">
            <label class="form-label">Detalle de la solicitud *</label>
            <textarea name="descripcion" class="form-control" rows="5" required minlength="10"
                      placeholder="Describa el derecho que el titular ejerce y los datos a los que se refiere…"><?= $e($input['descripcion'] ?? '') ?></textarea>
            <small class="text-muted">Mínimo 10 caracteres.</small>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Registrar solicitud</button>
    </div>
</form>
<?php $view->endSection() ?>
