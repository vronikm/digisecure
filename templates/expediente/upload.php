<?php
/**
 * Formulario para subir/reemplazar un documento del expediente.
 *
 * Variables: $user, $csrfToken, $currentPath, $expediente, $ficha, $tipo, $docExistente
 * Opcionales: $error (string)
 */

use DigiSecure\Expediente\Domain\Enum\EstadoDocumento;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$fichaId = $ficha['id'];
$expId = $expediente['id'];
$tipoId = $tipo['id'];
$esReemplazo = $docExistente !== null;
?>

<?php $view->section('title') ?>Subir Documento — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>">Guardias</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias/' . $fichaId) ?>"><?= $e($ficha['correlativo']) ?></a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>">Expediente</a></li>
<li class="breadcrumb-item active"><?= $esReemplazo ? 'Reemplazar' : 'Subir' ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-upload me-2"></i><?= $esReemplazo ? 'Reemplazar' : 'Subir' ?> Documento
        </h4>
        <p class="text-muted mb-0"><?= $e($tipo['nombre']) ?></p>
    </div>
    <a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver al expediente
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($error) ?></div>
<?php endif ?>

<!-- Info del tipo de documento -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <h6><?= $e($tipo['nombre']) ?></h6>
                <?php if ($tipo['descripcion']): ?>
                <p class="text-muted small mb-2"><?= $e($tipo['descripcion']) ?></p>
                <?php endif ?>
            </div>
            <div class="col-md-4">
                <ul class="list-unstyled small mb-0">
                    <li><i class="bi bi-file-earmark me-1"></i>Formatos: <strong><?= $e($tipo['formatos_permitidos']) ?></strong></li>
                    <li><i class="bi bi-hdd me-1"></i>Tamaño máx: <strong><?= $tipo['tamano_maximo_mb'] ?> MB</strong></li>
                    <?php if ($tipo['tiene_vencimiento']): ?>
                    <li><i class="bi bi-calendar-event me-1"></i>Vigencia: <strong><?= $tipo['dias_vigencia'] ?> días</strong></li>
                    <?php endif ?>
                    <?php if ($tipo['requiere_validacion_externa']): ?>
                    <li><i class="bi bi-globe me-1"></i><strong class="text-info">Requiere validación externa</strong></li>
                    <?php endif ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($esReemplazo): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    Ya existe un documento de este tipo (<strong><?= $e($docExistente['archivo_nombre_original'] ?? 'archivo') ?></strong>,
    estado: <?= $e(EstadoDocumento::from($docExistente['estado'])->label()) ?>).
    Al subir uno nuevo se reemplazará el anterior.
</div>
<?php endif ?>

<form method="post" action="<?= $view->url('/expedientes/' . $expId . '/documento/' . $tipoId) ?>" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-paperclip me-2"></i>Archivo</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Seleccionar archivo <span class="text-danger">*</span></label>
                    <input type="file" name="archivo" class="form-control" required
                           accept="<?= $e(implode(',', array_map(fn($f) => '.' . trim($f), explode(',', $tipo['formatos_permitidos'])))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha emisión del documento</label>
                    <input type="date" name="fecha_emision_documento" class="form-control"
                           value="<?= $e($docExistente['fecha_emision_documento'] ?? '') ?>">
                    <?php if ($tipo['tiene_vencimiento']): ?>
                    <div class="form-text">El vencimiento se calcula automáticamente (+<?= $tipo['dias_vigencia'] ?> días)</div>
                    <?php endif ?>
                </div>

                <?php if ($tipo['tiene_vencimiento']): ?>
                <div class="col-md-4">
                    <label class="form-label">Fecha vencimiento (manual)</label>
                    <input type="date" name="fecha_vencimiento" class="form-control"
                           value="<?= $e($docExistente['fecha_vencimiento'] ?? '') ?>">
                    <div class="form-text">Sobreescribe el cálculo automático</div>
                </div>
                <?php endif ?>

                <div class="col-md-4">
                    <label class="form-label">Fecha objetivo actualización</label>
                    <input type="date" name="fecha_objetivo_actualizacion" class="form-control"
                           value="<?= $e($docExistente['fecha_objetivo_actualizacion'] ?? '') ?>">
                    <div class="form-text">Recordatorio de renovación</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($tipo['requiere_validacion_externa']): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header"><i class="bi bi-globe me-2"></i>Validación Externa</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">URL del sitio donde se verificó</label>
                    <input type="url" name="validacion_url" class="form-control"
                           placeholder="https://..."
                           value="<?= $e($docExistente['validacion_url'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <p class="text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        Los screenshots de verificación se suben desde el detalle del documento una vez cargado.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif ?>

    <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-upload me-1"></i><?= $esReemplazo ? 'Reemplazar Documento' : 'Subir Documento' ?>
        </button>
    </div>
</form>
<?php $view->endSection() ?>
