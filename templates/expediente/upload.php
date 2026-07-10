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
$bloqueTone = $tipo['bloque'] === 'A' ? 'tone-info' : 'tone-warning';
$isAspirante = $user->esAspiranteGuardia();
$fichaUrl = $isAspirante ? '/guardias/' . $fichaId . '/editar' : '/guardias/' . $fichaId;
$guardiasLabel = $isAspirante ? 'Mi ficha' : 'Guardias';
$tieneVencimiento = (int) $tipo['tiene_vencimiento'] === 1;
$diasVigencia = (int) ($tipo['dias_vigencia'] ?? 0);
$fechaObjetivoActual = !empty($docExistente['fecha_objetivo_actualizacion'] ?? null)
    ? date('d/m/Y', strtotime((string) $docExistente['fecha_objetivo_actualizacion']))
    : null;
$puedeValidarDocumentos = (bool) ($puedeValidarDocumentos
    ?? ($user->tienePermiso('expedientes.validar') || $user->tienePermiso('expedientes.revisar')));
?>

<?php $view->section('title') ?><?= $esReemplazo ? 'Reemplazar' : 'Subir' ?> documento - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>"><?= $e($guardiasLabel) ?></a></li>
<li class="breadcrumb-item"><a href="<?= $view->url($fichaUrl) ?>"><?= $e($ficha['correlativo']) ?></a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>">Expediente</a></li>
<li class="breadcrumb-item active"><?= $esReemplazo ? 'Reemplazar' : 'Subir' ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-cloud-upload"></i></span>
        <div>
            <h1><?= $esReemplazo ? 'Reemplazar' : 'Subir' ?> documento</h1>
            <small style="display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;">
                <span class="ds-badge <?= $bloqueTone ?> no-dot">Bloque <?= $e((string) $tipo['bloque']) ?></span>
                <strong style="color: var(--ds-text);"><?= $e($tipo['nombre']) ?></strong>
                <span>&middot;</span>
                <span><?= $e($ficha['apellidos']) ?> <?= $e($ficha['nombres']) ?></span>
            </small>
        </div>
    </div>
    <a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al expediente
    </a>
</div>

<?php if (!empty($error)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo subir el documento</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<?php if ($esReemplazo): ?>
<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Reemplazo de documento</strong>
        Ya existe un documento de este tipo (<span class="ds-cell-mono"><?= $e($docExistente['archivo_nombre_original'] ?? 'archivo') ?></span>, estado: <?= $e(EstadoDocumento::from($docExistente['estado'])->label()) ?>). Al subir uno nuevo se reemplazara el anterior.
    </div>
</div>
<?php endif ?>

<!-- Info del tipo -->
<div class="ds-card">
    <div class="ds-card-header">
        <span class="ds-card-mark"><i class="bi bi-file-earmark-text"></i></span>
        <div>
            <div class="ds-card-title">Especificaciones del documento</div>
            <div class="ds-card-subtitle">Lo que el sistema acepta y procesa</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="row g-3">
            <div class="col-md-7">
                <?php if (!empty($tipo['descripcion'])): ?>
                <p style="color: var(--ds-text); font-size: .92rem; line-height: 1.55; margin: 0;"><?= $e($tipo['descripcion']) ?></p>
                <?php else: ?>
                <p class="ds-cell-sub" style="margin: 0;">Sin descripcion adicional. Sigue las especificaciones tecnicas del lado derecho.</p>
                <?php endif ?>
            </div>
            <div class="col-md-5">
                <ul style="list-style: none; padding: 0; margin: 0; font-size: .82rem;">
                    <li style="padding: .35rem 0; color: var(--ds-text-soft); display: flex; gap: .45rem;">
                        <i class="bi bi-file-earmark" style="width: 1rem;"></i>
                        Formatos: <strong style="color: var(--ds-text);"><?= $e($tipo['formatos_permitidos']) ?></strong>
                    </li>
                    <li style="padding: .35rem 0; color: var(--ds-text-soft); display: flex; gap: .45rem;">
                        <i class="bi bi-hdd" style="width: 1rem;"></i>
                        Tamano max: <strong style="color: var(--ds-text);"><?= $tipo['tamano_maximo_mb'] ?> MB</strong>
                    </li>
                    <?php if ($tieneVencimiento): ?>
                    <li style="padding: .35rem 0; color: var(--ds-text-soft); display: flex; gap: .45rem;">
                        <i class="bi bi-calendar-event" style="width: 1rem;"></i>
                        Vigencia: <strong style="color: var(--ds-text);"><?= $diasVigencia ?> dias</strong>
                    </li>
                    <li style="padding: .35rem 0; color: var(--ds-text-soft); display: flex; gap: .45rem;">
                        <i class="bi bi-arrow-repeat" style="width: 1rem;"></i>
                        Renovacion: <strong style="color: var(--ds-text);">30 dias antes del vencimiento</strong>
                    </li>
                    <?php endif ?>
                    <?php if ($tipo['requiere_validacion_externa']): ?>
                    <li style="padding: .35rem 0; color: #1d4d8f; display: flex; gap: .45rem;">
                        <i class="bi bi-globe" style="width: 1rem;"></i>
                        <strong>Requiere validacion externa</strong>
                    </li>
                    <?php endif ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<form method="post" action="<?= $view->url('/expedientes/' . $expId . '/documento/' . $tipoId) ?>" enctype="multipart/form-data" class="ds-form-main">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="ds-card">
        <div class="ds-card-header tone-success">
            <span class="ds-card-mark"><i class="bi bi-paperclip"></i></span>
            <div>
                <div class="ds-card-title">Archivo</div>
                <div class="ds-card-subtitle">Selecciona el documento desde tu equipo</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Seleccionar archivo <span class="ds-required">*</span></label>
                        <input type="file" name="archivo" class="form-control" required
                               accept="<?= $e(implode(',', array_map(fn($f) => '.' . trim($f), explode(',', $tipo['formatos_permitidos'])))) ?>">
                        <span class="ds-form-help">Solo se aceptan los formatos indicados arriba.</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha emision del documento</label>
                        <input type="date" name="fecha_emision_documento" class="form-control"
                               value="<?= $e($docExistente['fecha_emision_documento'] ?? '') ?>">
                        <?php if ($tieneVencimiento): ?>
                        <span class="ds-form-help">Si no ingresas vencimiento, se calcula automaticamente (+<?= $diasVigencia ?> dias).</span>
                        <?php endif ?>
                    </div>
                </div>

                <?php if ($tieneVencimiento): ?>
                <div class="col-md-4">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha vencimiento del documento</label>
                        <input type="date" name="fecha_vencimiento" class="form-control"
                               value="<?= $e($docExistente['fecha_vencimiento'] ?? '') ?>">
                        <span class="ds-form-help">Opcional. Usala si el documento ya trae una fecha impresa.</span>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="login-info" style="margin: 0; height: 100%; align-items: flex-start;">
                        <i class="bi bi-calendar-check"></i>
                        <div>
                            <strong>Renovacion programada automaticamente</strong>
                            El sistema calcula la fecha objetivo 30 dias antes del vencimiento. Si el documento esta muy cerca de vencer, la agenda desde hoy o desde la fecha de emision.
                            <?php if ($fechaObjetivoActual !== null): ?>
                                <div class="ds-cell-sub" style="margin-top: .35rem;">Fecha objetivo actual: <span class="ds-cell-mono"><?= $e($fechaObjetivoActual) ?></span></div>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <?php if ($tipo['requiere_validacion_externa'] && $puedeValidarDocumentos): ?>
    <div class="ds-card">
        <div class="ds-card-header tone-purple">
            <span class="ds-card-mark"><i class="bi bi-globe"></i></span>
            <div>
                <div class="ds-card-title">Validacion externa</div>
                <div class="ds-card-subtitle">Verificacion del documento en sitio web oficial</div>
            </div>
        </div>
        <div class="ds-card-body">
            <div class="ds-form-field" style="margin-bottom: 1rem;">
                <label class="ds-form-label">URL del sitio donde se verifico</label>
                <input type="url" name="validacion_url" class="form-control"
                       placeholder="https://..."
                       value="<?= $e($docExistente['validacion_url'] ?? '') ?>">
            </div>
            <div class="login-info" style="margin: 0;">
                <i class="bi bi-info-circle"></i>
                <div>
                    <strong>Capturas de verificacion</strong>
                    Las capturas de pantalla del proceso de verificacion se suben desde el detalle del documento una vez cargado.
                </div>
            </div>
        </div>
    </div>
    <?php endif ?>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-cloud-upload"></i><?= $esReemplazo ? 'Reemplazar documento' : 'Subir documento' ?>
        </button>
    </div>
</form>
<?php $view->endSection() ?>
