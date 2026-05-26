<?php
/**
 * Configurar un tipo de documento para el tenant.
 *
 * Variables: $user, $csrfToken, $currentPath, $tipo, $error?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$bloqueTone = $tipo['bloque'] === 'A' ? 'tone-info' : 'tone-warning';
?>

<?php $view->section('title') ?>Configurar tipo - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/configuracion/documentos') ?>">Tipos de documento</a></li>
<li class="breadcrumb-item active"><?= $e((string) $tipo['codigo']) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-gear"></i></span>
        <div>
            <h1>Configurar tipo de documento</h1>
            <small style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <span class="ds-badge <?= $bloqueTone ?> no-dot">Bloque <?= $e((string) $tipo['bloque']) ?></span>
                <strong style="color: var(--ds-text);"><?= $e((string) $tipo['nombre']) ?></strong>
                <span class="ds-cell-mono"><?= $e((string) $tipo['codigo']) ?></span>
                <?php if ($tipo['tenant_id'] !== null): ?>
                    <span class="ds-badge tone-info no-dot">Override propio</span>
                <?php endif ?>
            </small>
        </div>
    </div>
    <a href="<?= $view->url('/configuracion/documentos') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al catalogo
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo guardar la configuracion</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<form method="post" action="<?= $view->url('/configuracion/documentos/' . $e((string) $tipo['codigo'])) ?>" class="ds-form-main">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <?php if (!empty($tipo['descripcion'])): ?>
    <div class="login-info" style="margin-bottom: 1.25rem;">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>Descripcion del tipo</strong>
            <?= $e((string) $tipo['descripcion']) ?>
        </div>
    </div>
    <?php endif ?>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="ds-card" style="margin-bottom: 0; height: 100%;">
                <div class="ds-card-header">
                    <span class="ds-card-mark"><i class="bi bi-toggle-on"></i></span>
                    <div>
                        <div class="ds-card-title">Disponibilidad</div>
                        <div class="ds-card-subtitle">Activacion, obligatoriedad y orden</div>
                    </div>
                </div>
                <div class="ds-card-body">
                    <div class="ds-form-field" style="margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; gap: .65rem; cursor: pointer;">
                            <input type="hidden" name="activo" value="0">
                            <input class="form-check-input" type="checkbox" name="activo" value="1"
                                   id="chkActivo" <?= (int) $tipo['activo'] === 1 ? 'checked' : '' ?>
                                   style="width: 2.5rem; height: 1.4rem; flex-shrink: 0;">
                            <span style="font-weight: 600; color: var(--ds-text);">Tipo activo</span>
                        </label>
                        <span class="ds-form-help" style="margin-top: .25rem;">Si se desactiva, no aparecera en los expedientes nuevos.</span>
                    </div>

                    <div class="ds-form-field" style="margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; gap: .65rem; cursor: pointer;">
                            <input type="hidden" name="obligatorio" value="0">
                            <input class="form-check-input" type="checkbox" name="obligatorio" value="1"
                                   id="chkOblig" <?= (int) $tipo['obligatorio'] === 1 ? 'checked' : '' ?>
                                   style="width: 2.5rem; height: 1.4rem; flex-shrink: 0;">
                            <span style="font-weight: 600; color: var(--ds-text);">Obligatorio</span>
                        </label>
                        <span class="ds-form-help" style="margin-top: .25rem;">Los documentos obligatorios se exigen para completar el expediente.</span>
                    </div>

                    <div class="ds-form-field">
                        <label class="ds-form-label">Orden de presentacion</label>
                        <input type="number" name="orden" class="form-control"
                               value="<?= (int) $tipo['orden'] ?>" min="0" max="999">
                        <span class="ds-form-help">Orden dentro de su bloque (menor = primero).</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="ds-card" style="margin-bottom: 0; height: 100%;">
                <div class="ds-card-header tone-warning">
                    <span class="ds-card-mark"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <div class="ds-card-title">Vigencia</div>
                        <div class="ds-card-subtitle">Caducidad y validacion externa</div>
                    </div>
                </div>
                <div class="ds-card-body">
                    <div class="ds-form-field" style="margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; gap: .65rem; cursor: pointer;">
                            <input type="hidden" name="tiene_vencimiento" value="0">
                            <input class="form-check-input" type="checkbox" name="tiene_vencimiento" value="1"
                                   id="chkVenc" <?= (int) $tipo['tiene_vencimiento'] === 1 ? 'checked' : '' ?>
                                   onchange="document.getElementById('diasVigencia').disabled = !this.checked"
                                   style="width: 2.5rem; height: 1.4rem; flex-shrink: 0;">
                            <span style="font-weight: 600; color: var(--ds-text);">Tiene vencimiento</span>
                        </label>
                        <span class="ds-form-help" style="margin-top: .25rem;">Si se activa, los documentos de este tipo tendran fecha de expiracion.</span>
                    </div>

                    <div class="ds-form-field" style="margin-bottom: 1rem;">
                        <label class="ds-form-label">Dias de vigencia</label>
                        <input type="number" name="dias_vigencia" class="form-control"
                               id="diasVigencia"
                               value="<?= $tipo['dias_vigencia'] !== null ? (int) $tipo['dias_vigencia'] : '' ?>"
                               min="1" max="9999" placeholder="Ej: 365"
                               <?= (int) $tipo['tiene_vencimiento'] !== 1 ? 'disabled' : '' ?>>
                        <span class="ds-form-help">Vigencia predeterminada en dias desde la fecha de emision. El revisor puede sobreescribir por documento.</span>
                    </div>

                    <?php if ((int) $tipo['requiere_validacion_externa'] === 1): ?>
                    <div class="login-info" style="margin: 0;">
                        <i class="bi bi-globe"></i>
                        <div>
                            <strong>Validacion externa</strong>
                            Este tipo requiere verificacion en sitio web oficial + capturas. La configuracion no es modificable.
                        </div>
                    </div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>

    <div class="login-info" style="margin-top: 1.25rem;">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>Campos no editables</strong>
            El codigo, nombre, bloque y validacion externa los define el sistema. Los cambios aqui crean una configuracion propia de tu empresa que prevalece sobre la del sistema base.
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/configuracion/documentos') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Guardar configuracion
        </button>
    </div>
</form>
<?php $view->endSection() ?>
