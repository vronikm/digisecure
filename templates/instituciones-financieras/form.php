<?php
/**
 * Form de alta/edición de una institución financiera.
 *
 * Variables: $user, $csrfToken, $currentPath, $modo ('crear'|'editar'), $item
 * Opcionales: $error
 */

use DigiSecure\Catalogo\Domain\TipoInstitucion;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$esEditar = ($modo ?? 'crear') === 'editar';
$id = (string) ($item['id'] ?? '');
$action = $esEditar
    ? '/configuracion/instituciones-financieras/' . $id
    : '/configuracion/instituciones-financieras';
$tipoActual = (string) ($item['tipo'] ?? 'BANCO');
$activo = (int) ($item['activo'] ?? 1) === 1;
?>

<?php $view->section('title') ?><?= $esEditar ? 'Editar' : 'Nueva' ?> institución financiera - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/configuracion/instituciones-financieras') ?>">Instituciones financieras</a></li>
<li class="breadcrumb-item active"><?= $esEditar ? 'Editar' : 'Nueva' ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-bank2"></i></span>
        <div>
            <h1><?= $esEditar ? 'Editar institución' : 'Nueva institución' ?></h1>
            <small>Catálogo usado en el campo Banco de la ficha del guardia</small>
        </div>
    </div>
    <a href="<?= $view->url('/configuracion/instituciones-financieras') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al catálogo
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo guardar</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<form class="ds-form-main" method="post" action="<?= $view->url($action) ?>">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header">
            <span class="ds-card-mark"><i class="bi bi-info-circle"></i></span>
            <div>
                <div class="ds-card-title">Datos de la institución</div>
                <div class="ds-card-subtitle">Nombre, tipo y orden de aparición</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nombre <span class="ds-required">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="150"
                               placeholder="Ej: Banco Pichincha" value="<?= $e((string) ($item['nombre'] ?? '')) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Tipo <span class="ds-required">*</span></label>
                        <select name="tipo" class="form-select" required>
                            <?php foreach (TipoInstitucion::cases() as $t): ?>
                            <option value="<?= $t->value ?>" <?= $tipoActual === $t->value ? 'selected' : '' ?>><?= $e($t->label()) ?></option>
                            <?php endforeach ?>
                        </select>
                        <span class="ds-form-help">Los bancos se listan antes que las cooperativas.</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Orden</label>
                        <input type="number" name="orden" class="form-control" min="0" max="999"
                               value="<?= (int) ($item['orden'] ?? 0) ?>">
                        <span class="ds-form-help">Menor número aparece primero dentro de su tipo.</span>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Estado</label>
                        <div class="form-check form-switch" style="padding-top: .4rem;">
                            <input class="form-check-input" type="checkbox" role="switch" id="if-activo"
                                   name="activo" value="1" <?= $activo ? 'checked' : '' ?>>
                            <label class="form-check-label" for="if-activo">Activa (visible en la ficha del guardia)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/configuracion/instituciones-financieras') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i><?= $esEditar ? 'Guardar cambios' : 'Crear institución' ?>
        </button>
    </div>
</form>
<?php $view->endSection() ?>
