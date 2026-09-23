<?php
/**
 * Formulario para subir/reemplazar un documento del expediente.
 *
 * Variables: $user, $csrfToken, $currentPath, $expediente, $ficha, $tipo, $docExistente, $docsExistentes
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
$docExistente = $docExistente ?? null;
$docsExistentes = $docsExistentes ?? ($docExistente !== null ? [$docExistente] : []);
$docsPorIndice = [];
foreach ($docsExistentes as $docSlot) {
    $indice = max(1, (int) ($docSlot['documento_indice'] ?? 1));
    $docsPorIndice[$indice] ??= $docSlot;
}
$numeroDocumentos = max(1, (int) ($tipo['numero_documentos'] ?? 1));
$documentosSubidos = count($docsPorIndice);
$esReemplazo = $documentosSubidos > 0;
$ranurasEditables = 0;
for ($i = 1; $i <= $numeroDocumentos; $i++) {
    $docSlot = $docsPorIndice[$i] ?? null;
    if ($docSlot === null || (string) ($docSlot['estado'] ?? '') !== EstadoDocumento::ACEPTADO->value) {
        $ranurasEditables++;
    }
}
$todasLasRanurasAceptadas = $ranurasEditables === 0;
$esBloqueB = (string) ($tipo['bloque'] ?? '') === 'B';
$bloqueTone = $tipo['bloque'] === 'A' ? 'tone-info' : 'tone-warning';
$isAspirante = $user->esAspiranteGuardia();
$fichaUrl = $isAspirante ? '/guardias/' . $fichaId . '/editar' : '/guardias/' . $fichaId;
$guardiasLabel = $isAspirante ? 'Mi ficha' : 'Guardias';
$tieneVencimiento = (int) $tipo['tiene_vencimiento'] === 1;
$diasVigencia = (int) ($tipo['dias_vigencia'] ?? 0);
$primerDocumento = array_values($docsPorIndice)[0] ?? null;
$fechaObjetivoActual = !empty($primerDocumento['fecha_objetivo_actualizacion'] ?? null)
    ? date('d/m/Y', strtotime((string) $primerDocumento['fecha_objetivo_actualizacion']))
    : null;
$accept = $e(implode(',', array_map(
    static fn($f) => '.' . ltrim(trim((string) $f), '.'),
    explode(',', (string) $tipo['formatos_permitidos']),
)));
$puedeValidarDocumentos = (bool) ($puedeValidarDocumentos
    ?? ($user->tienePermiso('expedientes.validar') || $user->tienePermiso('expedientes.revisar')));
$autoAceptaBloqueB = $esBloqueB && !$isAspirante;
?>

<?php $view->section('title') ?><?= $todasLasRanurasAceptadas ? 'Ver' : ($esReemplazo ? 'Reemplazar' : 'Subir') ?> documento - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>"><?= $e($guardiasLabel) ?></a></li>
<li class="breadcrumb-item"><a href="<?= $view->url($fichaUrl) ?>"><?= $e($ficha['correlativo']) ?></a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>">Expediente</a></li>
<li class="breadcrumb-item active"><?= $todasLasRanurasAceptadas ? 'Ver' : ($esReemplazo ? 'Reemplazar' : 'Subir') ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-cloud-upload"></i></span>
        <div>
            <h1><?= $todasLasRanurasAceptadas ? 'Documento aceptado' : ($esReemplazo ? 'Reemplazar' : 'Subir') ?> documento</h1>
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

<?php if ($todasLasRanurasAceptadas): ?>
<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-lock"></i>
    <div>
        <strong>Documento aceptado en solo lectura</strong>
        Este requisito ya fue aceptado. Puedes ver los archivos cargados, pero no reemplazarlos.
    </div>
</div>
<?php elseif ($esReemplazo): ?>
<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Documentos cargados</strong>
        Este requisito tiene <?= (int) $documentosSubidos ?> de <?= (int) $numeroDocumentos ?> archivo(s) cargado(s). Solo las ranuras no aceptadas pueden corregirse o reemplazarse.
    </div>
</div>
<?php endif ?>

<?php if ($autoAceptaBloqueB): ?>
<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-check-circle"></i>
    <div>
        <strong>Carga administrativa de Bloque B</strong>
        Este documento quedara en estado <strong>Aceptado</strong> al guardarse, sin pasar por doble revision.
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
                        <i class="bi bi-files" style="width: 1rem;"></i>
                        Número de archivos: <strong style="color: var(--ds-text);"><?= (int) $numeroDocumentos ?></strong>
                    </li>
                    <li style="padding: .35rem 0; color: var(--ds-text-soft); display: flex; gap: .45rem;">
                        <i class="bi bi-hdd" style="width: 1rem;"></i>
                        Tamaño máx.: <strong style="color: var(--ds-text);"><?= $tipo['tamano_maximo_mb'] ?> MB</strong>
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

<form method="post" action="<?= $view->url('/expedientes/' . $expId . '/documento/' . $tipoId) ?>" enctype="multipart/form-data" class="ds-form-main" style="max-width: none;">
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
                <div class="col-md-6">
                    <div class="row g-3">
                        <?php for ($i = 1; $i <= $numeroDocumentos; $i++):
                            $docSlot = $docsPorIndice[$i] ?? null;
                            $slotEstado = $docSlot ? EstadoDocumento::from((string) $docSlot['estado']) : null;
                            $slotAceptado = $slotEstado === EstadoDocumento::ACEPTADO;
                            $slotInput = $numeroDocumentos === 1 ? 'archivo' : 'archivo_' . $i;
                        ?>
                        <div class="col-12">
                            <div class="ds-form-field">
                                <label class="ds-form-label">
                                    <?= $numeroDocumentos === 1 ? 'Seleccionar archivo' : 'Archivo ' . $i . ' de ' . (int) $numeroDocumentos ?>
                                    <?php if (!$slotAceptado && $numeroDocumentos === 1 && !$docSlot): ?><span class="ds-required">*</span><?php endif ?>
                                </label>
                                <?php if ($docSlot): ?>
                                    <div class="login-info" style="margin: 0 0 .5rem; padding: .65rem .75rem;">
                                        <i class="bi <?= $slotEstado?->iconClass() ?? 'bi-file-earmark' ?>"></i>
                                        <div>
                                            <strong><?= $e((string) ($docSlot['archivo_nombre_original'] ?? 'Archivo cargado')) ?></strong>
                                            <span class="ds-cell-sub">Estado: <?= $e($slotEstado?->label() ?? 'Cargado') ?></span>
                                        </div>
                                        <a href="<?= $view->url('/expedientes/documento/' . $docSlot['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm" style="margin-left:auto;">
                                            Ver
                                        </a>
                                    </div>
                                <?php endif ?>
                                <?php if ($slotAceptado): ?>
                                <div class="ds-form-help" style="display:flex; align-items:center; gap:.35rem; color:#047857;">
                                    <i class="bi bi-lock"></i>
                                    Archivo aceptado. No se permite reemplazar esta posicion.
                                </div>
                                <?php else: ?>
                                <input type="file" name="<?= $slotInput ?>" class="form-control"
                                       <?= $numeroDocumentos === 1 && !$docSlot ? 'required' : '' ?>
                                       accept="<?= $accept ?>">
                                <span class="ds-form-help">
                                    <?php if ($numeroDocumentos === 1): ?>
                                        <?= $docSlot ? 'Selecciona un archivo solo si deseas reemplazar el documento.' : 'Solo se aceptan los formatos indicados arriba.' ?>
                                    <?php else: ?>
                                        <?= $docSlot ? 'Selecciona un archivo solo si deseas reemplazar esta posicion.' : 'Carga el archivo correspondiente a esta posicion.' ?>
                                    <?php endif ?>
                                </span>
                                <?php endif ?>
                            </div>
                        </div>
                        <?php endfor ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha emisión del documento</label>
                        <input type="date" name="fecha_emision_documento" class="form-control"
                               value="<?= $e($primerDocumento['fecha_emision_documento'] ?? '') ?>"
                               <?= $todasLasRanurasAceptadas ? 'disabled' : '' ?>>
                    </div>
                </div>

                <?php if ($tieneVencimiento): ?>
                <div class="col-md-3">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Fecha vencimiento del documento</label>
                        <input type="date" name="fecha_vencimiento" class="form-control"
                               value="<?= $e($primerDocumento['fecha_vencimiento'] ?? '') ?>"
                               <?= $todasLasRanurasAceptadas ? 'disabled' : '' ?>>
                        <span class="ds-form-help">Opcional. Usala si el documento ya trae una fecha impresa.</span>
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
                       value="<?= $e($primerDocumento['validacion_url'] ?? '') ?>"
                       <?= $todasLasRanurasAceptadas ? 'disabled' : '' ?>>
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
        <?php if ($ranurasEditables > 0): ?>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-cloud-upload"></i><?= $autoAceptaBloqueB ? ($esReemplazo ? 'Cargar y aceptar cambios' : 'Subir y aceptar') : ($esReemplazo ? 'Guardar cambios' : ($numeroDocumentos > 1 ? 'Subir documentos' : 'Subir documento')) ?>
        </button>
        <?php else: ?>
        <button type="button" class="ds-btn ds-btn-primary" disabled style="opacity:.65;">
            <i class="bi bi-lock"></i>Solo lectura
        </button>
        <?php endif ?>
    </div>
</form>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script>
// El vencimiento debe ser posterior a la emisión (el servidor también lo valida).
(() => {
    const emision = document.querySelector('input[name="fecha_emision_documento"]');
    const vence = document.querySelector('input[name="fecha_vencimiento"]');
    if (!emision || !vence) return;
    const sync = () => {
        if (!emision.value) { vence.removeAttribute('min'); vence.setCustomValidity(''); return; }
        const d = new Date(emision.value + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        vence.min = d.toISOString().slice(0, 10);
        vence.setCustomValidity(vence.value && vence.value <= emision.value
            ? 'La fecha de vencimiento debe ser posterior a la fecha de emisión.' : '');
    };
    emision.addEventListener('change', sync);
    vence.addEventListener('change', sync);
    sync();
})();
</script>
<?php $view->endSection() ?>
