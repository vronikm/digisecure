<?php
/**
 * Alta manual de tenant (superadmin).
 *
 * Variables: $csrfToken, $planes, $input, $formAction, $volverUrl, $volverLabel
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$in = is_array($input ?? null) ? $input : [];
$formAction = (string) ($formAction ?? '/admin/tenants/crear');
$volverUrl = (string) ($volverUrl ?? '/admin/tenants');
$volverLabel = (string) ($volverLabel ?? 'Volver al listado');
?>

<?php $view->section('title') ?>Crear tenant | Superadmin<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url($volverUrl) ?>">Tenants</a></li>
<li class="breadcrumb-item active">Crear</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-building-add"></i></span>
        <div>
            <h1>Crear tenant</h1>
            <small>Alta manual de empresa en la plataforma</small>
        </div>
    </div>
    <a href="<?= $view->url($volverUrl) ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i><?= $e($volverLabel) ?>
    </a>
</div>

<form class="ds-form-main" method="post" action="<?= $view->url($formAction) ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-building"></i></span>
            <div>
                <div class="ds-card-title">Identidad y contacto</div>
                <div class="ds-card-subtitle">Datos base del tenant</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Razon social <span class="ds-required">*</span></label>
                        <input type="text" name="razon_social" class="form-control" required maxlength="255" value="<?= $e((string) ($in['razon_social'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">RUC <span class="ds-required">*</span></label>
                        <input type="text" name="ruc" class="form-control" required maxlength="13" value="<?= $e((string) ($in['ruc'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Estado</label>
                        <?php $estado = (string) ($in['estado'] ?? 'activo'); ?>
                        <select name="estado" class="form-select">
                            <?php foreach (['activo','pendiente','suspendido','cancelado'] as $est): ?>
                            <option value="<?= $est ?>" <?= $estado === $est ? 'selected' : '' ?>><?= ucfirst($est) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre comercial</label>
                        <input type="text" name="nombre_comercial" class="form-control" maxlength="255" value="<?= $e((string) ($in['nombre_comercial'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Email contacto <span class="ds-required">*</span></label>
                        <input type="email" name="email_contacto" class="form-control" required maxlength="190" value="<?= $e((string) ($in['email_contacto'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Telefono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="30" value="<?= $e((string) ($in['telefono'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control" maxlength="100" value="<?= $e((string) ($in['ciudad'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Provincia</label>
                        <input type="text" name="provincia" class="form-control" maxlength="100" value="<?= $e((string) ($in['provincia'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Direccion</label>
                        <input type="text" name="direccion" class="form-control" maxlength="255" value="<?= $e((string) ($in['direccion'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Pais</label>
                        <input type="text" name="pais" class="form-control" maxlength="2" value="<?= $e((string) ($in['pais'] ?? 'EC')) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-gem"></i></span>
            <div>
                <div class="ds-card-title">Plan y limites</div>
                <div class="ds-card-subtitle">Configuracion inicial del tenant</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Plan <span class="ds-required">*</span></label>
                        <?php $planSel = (string) ($in['plan_codigo'] ?? 'trial'); ?>
                        <select name="plan_codigo" class="form-select" required>
                            <?php foreach ($planes as $p): ?>
                            <option value="<?= $e($p->codigo) ?>" <?= $planSel === $p->codigo ? 'selected' : '' ?>>
                                <?= $e($p->nombre) ?> (<?= $e($p->codigo) ?>)
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Inicio plan</label>
                        <input type="date" name="plan_inicio" class="form-control" value="<?= $e((string) ($in['plan_inicio'] ?? date('Y-m-d'))) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Vencimiento plan</label>
                        <input type="date" name="plan_vencimiento" class="form-control" value="<?= $e((string) ($in['plan_vencimiento'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Max usuarios</label>
                        <input type="number" name="max_usuarios" min="1" class="form-control" value="<?= $e((string) ($in['max_usuarios'] ?? '5')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Max guardias</label>
                        <input type="number" name="max_guardias" min="1" class="form-control" value="<?= $e((string) ($in['max_guardias'] ?? '50')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Max storage (MB)</label>
                        <input type="number" name="max_storage_mb" min="128" class="form-control" value="<?= $e((string) ($in['max_storage_mb'] ?? '1024')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Timezone</label>
                        <input type="text" name="timezone" class="form-control" value="<?= $e((string) ($in['timezone'] ?? 'America/Guayaquil')) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Locale</label>
                        <input type="text" name="locale" class="form-control" value="<?= $e((string) ($in['locale'] ?? 'es_EC')) ?>">
                    </div>
                </div>
                <div class="col-12">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Notas</label>
                        <textarea name="notas" class="form-control" rows="3"><?= $e((string) ($in['notas'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url($volverUrl) ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Crear tenant
        </button>
    </div>
</form>
<?php $view->endSection() ?>
