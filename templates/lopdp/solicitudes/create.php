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
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-plus-circle"></i></span>
        <div>
            <h1>Nueva solicitud ARCO-P</h1>
            <small>Registra el ejercicio de derechos del titular sobre sus datos personales</small>
        </div>
    </div>
    <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo registrar la solicitud</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Plazo legal de respuesta</strong>
        El plazo de respuesta es de <strong>15 dias calendario</strong> desde la fecha de recepcion (Art. 32 LOPDP Ecuador).
    </div>
</div>

<form method="post" action="<?= $view->url('/lopdp/solicitudes/crear') ?>">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-tag"></i></span>
            <div>
                <div class="ds-card-title">Tipo y canal</div>
                <div class="ds-card-subtitle">Como se recibio la solicitud</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo de derecho ARCO-P <span class="ds-required">*</span></label>
                        <select name="tipo" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach (['acceso','rectificacion','cancelacion','oposicion','portabilidad'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($input['tipo'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Canal de recepcion <span class="ds-required">*</span></label>
                        <select name="canal_recepcion" class="form-select" required>
                            <?php foreach (['email','telefono','presencial','formulario_web'] as $c): ?>
                            <option value="<?= $c ?>" <?= ($input['canal_recepcion'] ?? 'email') === $c ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $c)) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha de recepcion <span class="ds-required">*</span></label>
                        <input type="date" name="fecha_recepcion" class="form-control"
                               value="<?= $e($input['fecha_recepcion'] ?? date('Y-m-d')) ?>" required>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-success">
            <span class="ds-card-mark"><i class="bi bi-person-vcard"></i></span>
            <div>
                <div class="ds-card-title">Datos del titular</div>
                <div class="ds-card-subtitle">Persona que ejerce el derecho</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo de titular <span class="ds-required">*</span></label>
                        <select name="titular_tipo" class="form-select" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach (['guardia','usuario','externo'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($input['titular_tipo'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre completo <span class="ds-required">*</span></label>
                        <input type="text" name="titular_nombre" class="form-control" maxlength="200"
                               value="<?= $e($input['titular_nombre'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Cedula</label>
                        <input type="text" name="titular_cedula" class="form-control" maxlength="20"
                               value="<?= $e($input['titular_cedula'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Email</label>
                        <input type="email" name="titular_email" class="form-control" maxlength="190"
                               value="<?= $e($input['titular_email'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono</label>
                        <input type="text" name="titular_telefono" class="form-control" maxlength="30"
                               value="<?= $e($input['titular_telefono'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-warning">
            <span class="ds-card-mark"><i class="bi bi-card-text"></i></span>
            <div>
                <div class="ds-card-title">Descripcion</div>
                <div class="ds-card-subtitle">Detalla el derecho ejercido y los datos a los que se refiere</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="ds-form-field">
                <label class="ds-form-label">Detalle de la solicitud <span class="ds-required">*</span></label>
                <textarea name="descripcion" class="form-control" rows="6" required minlength="10"
                          placeholder="Describe el derecho que el titular ejerce y los datos a los que se refiere..."><?= $e($input['descripcion'] ?? '') ?></textarea>
                <span class="ds-form-help">Minimo 10 caracteres. Se conservara como evidencia ante una eventual auditoria de la SPDP.</span>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Registrar solicitud
        </button>
    </div>
</form>
<?php $view->endSection() ?>
