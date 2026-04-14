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
?>

<?php $view->section('title') ?>Configurar tipo — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/configuracion/documentos') ?>">Tipos de documento</a></li>
<li class="breadcrumb-item active"><?= $e((string)$tipo['codigo']) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Configurar tipo de documento</h4>
    <a href="<?= $view->url('/configuracion/documentos') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= $e($error) ?></div>
<?php endif ?>

<form method="post" action="<?= $view->url('/configuracion/documentos/' . $e((string)$tipo['codigo'])) ?>" class="card shadow-sm">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <div class="card-header">
        <div class="d-flex align-items-center gap-3">
            <span class="badge <?= $tipo['bloque'] === 'A' ? 'bg-info' : 'bg-warning text-dark' ?>">Bloque <?= $e((string)$tipo['bloque']) ?></span>
            <strong><?= $e((string)$tipo['nombre']) ?></strong>
            <code class="text-muted small"><?= $e((string)$tipo['codigo']) ?></code>
            <?php if ($tipo['tenant_id'] !== null): ?>
                <span class="badge bg-primary">Override propio</span>
            <?php endif ?>
        </div>
    </div>

    <div class="card-body">
        <?php if (!empty($tipo['descripcion'])): ?>
        <p class="text-muted small mb-4"><?= $e((string)$tipo['descripcion']) ?></p>
        <?php endif ?>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Disponibilidad</h6>

                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="activo" value="0">
                            <input class="form-check-input" type="checkbox" name="activo" value="1"
                                   id="chkActivo" <?= (int)$tipo['activo'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="chkActivo">Activo</label>
                            <div class="form-text">Si se desactiva, no aparecerá en los expedientes nuevos.</div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="obligatorio" value="0">
                            <input class="form-check-input" type="checkbox" name="obligatorio" value="1"
                                   id="chkOblig" <?= (int)$tipo['obligatorio'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="chkOblig">Obligatorio</label>
                            <div class="form-text">Los documentos obligatorios se exigen para completar el expediente.</div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Orden de presentación</label>
                            <input type="number" name="orden" class="form-control form-control-sm"
                                   value="<?= (int)$tipo['orden'] ?>" min="0" max="999">
                            <div class="form-text">Orden dentro de su bloque (menor = primero).</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Vigencia</h6>

                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="tiene_vencimiento" value="0">
                            <input class="form-check-input" type="checkbox" name="tiene_vencimiento" value="1"
                                   id="chkVenc" <?= (int)$tipo['tiene_vencimiento'] === 1 ? 'checked' : '' ?>
                                   onchange="document.getElementById('diasVigencia').disabled = !this.checked">
                            <label class="form-check-label" for="chkVenc">Tiene vencimiento</label>
                            <div class="form-text">Si se activa, los documentos de este tipo tendrán fecha de expiración.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Días de vigencia</label>
                            <input type="number" name="dias_vigencia" class="form-control form-control-sm"
                                   id="diasVigencia"
                                   value="<?= $tipo['dias_vigencia'] !== null ? (int)$tipo['dias_vigencia'] : '' ?>"
                                   min="1" max="9999" placeholder="Ej: 365"
                                   <?= (int)$tipo['tiene_vencimiento'] !== 1 ? 'disabled' : '' ?>>
                            <div class="form-text">
                                Vigencia predeterminada en días desde la fecha de emisión.
                                El revisor puede sobreescribir por documento individual.
                            </div>
                        </div>

                        <?php if ((int)$tipo['requiere_validacion_externa'] === 1): ?>
                        <div class="alert alert-warning small mb-0">
                            <i class="bi bi-globe me-1"></i>
                            Este tipo requiere <strong>validación externa</strong> (verificación en sitio web oficial + capturas).
                            Esta configuración no es modificable.
                        </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Los campos <strong>código</strong>, <strong>nombre</strong>, <strong>bloque</strong> y
            <strong>validación externa</strong> son definidos por el sistema y no son editables.
            Los cambios aquí crean una configuración propia para su empresa que prevalece sobre la del sistema.
        </div>
    </div>

    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="<?= $view->url('/configuracion/documentos') ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar configuración</button>
    </div>
</form>
<?php $view->endSection() ?>
