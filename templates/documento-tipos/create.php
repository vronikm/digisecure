<?php
/**
 * Crear un tipo de documento propio del tenant.
 *
 * Variables: $user, $csrfToken, $currentPath, $input, $error?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$old = static fn(string $key, mixed $default = ''): mixed => $input[$key] ?? $default;
$checked = static fn(string $key, bool $default = false): string => (int) ($input[$key] ?? ($default ? 1 : 0)) === 1 ? 'checked' : '';
?>

<?php $view->section('title') ?>Nuevo tipo de documento - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/configuracion/documentos') ?>">Tipos de documento</a></li>
<li class="breadcrumb-item active">Nuevo tipo</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-file-earmark-plus"></i></span>
        <div>
            <h1>Nuevo tipo de documento</h1>
            <small>Crea un requisito documental propio de tu empresa</small>
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
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">No se pudo crear el tipo</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<form method="post" action="<?= $view->url('/configuracion/documentos') ?>" class="ds-form-main">
    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="ds-card" style="margin-bottom: 0;">
                <div class="ds-card-header">
                    <span class="ds-card-mark"><i class="bi bi-card-text"></i></span>
                    <div>
                        <div class="ds-card-title">Datos del documento</div>
                        <div class="ds-card-subtitle">Identificador, nombre y descripcion visible</div>
                    </div>
                </div>
                <div class="ds-card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Codigo <span class="ds-required">*</span></label>
                                <input type="text" name="codigo" class="form-control" maxlength="50" required
                                       value="<?= $e((string) $old('codigo')) ?>"
                                       placeholder="EJ: CERT_MEDICO">
                                <span class="ds-form-help">Usa letras, numeros y guion bajo. Se normaliza en mayusculas.</span>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Nombre <span class="ds-required">*</span></label>
                                <input type="text" name="nombre" class="form-control" maxlength="200" required
                                       value="<?= $e((string) $old('nombre')) ?>"
                                       placeholder="Ej: Certificado medico ocupacional">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Descripcion</label>
                                <textarea name="descripcion" class="form-control" rows="3" maxlength="500"
                                          placeholder="Indicaciones para quien carga el documento"><?= $e((string) $old('descripcion')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="ds-card" style="margin-bottom: 0;">
                <div class="ds-card-header tone-info">
                    <span class="ds-card-mark"><i class="bi bi-sliders"></i></span>
                    <div>
                        <div class="ds-card-title">Comportamiento</div>
                        <div class="ds-card-subtitle">Bloque, estado y obligatoriedad</div>
                    </div>
                </div>
                <div class="ds-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Bloque <span class="ds-required">*</span></label>
                                <select name="bloque" class="form-select" required>
                                    <option value="A" <?= (string) $old('bloque', 'A') === 'A' ? 'selected' : '' ?>>Bloque A</option>
                                    <option value="B" <?= (string) $old('bloque') === 'B' ? 'selected' : '' ?>>Bloque B</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Orden</label>
                                <input type="number" name="orden" class="form-control" min="0" max="999"
                                       value="<?= (int) $old('orden', 0) ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label style="display:flex; align-items:center; gap:.65rem; cursor:pointer; margin-bottom:.75rem;">
                                <input type="hidden" name="activo" value="0">
                                <input class="form-check-input" type="checkbox" name="activo" value="1" <?= $checked('activo', true) ?>>
                                <span style="font-weight:600; color:var(--ds-text);">Activo</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:.65rem; cursor:pointer; margin-bottom:.75rem;">
                                <input type="hidden" name="obligatorio" value="0">
                                <input class="form-check-input" type="checkbox" name="obligatorio" value="1" <?= $checked('obligatorio', true) ?>>
                                <span style="font-weight:600; color:var(--ds-text);">Obligatorio</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:.65rem; cursor:pointer;">
                                <input type="hidden" name="requiere_validacion_externa" value="0">
                                <input class="form-check-input" type="checkbox" name="requiere_validacion_externa" value="1" <?= $checked('requiere_validacion_externa') ?>>
                                <span style="font-weight:600; color:var(--ds-text);">Requiere validacion externa</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="ds-card">
                <div class="ds-card-header tone-warning">
                    <span class="ds-card-mark"><i class="bi bi-paperclip"></i></span>
                    <div>
                        <div class="ds-card-title">Archivo y vigencia</div>
                        <div class="ds-card-subtitle">Formatos admitidos y caducidad del documento</div>
                    </div>
                </div>
                <div class="ds-card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Formatos permitidos</label>
                                <input type="text" name="formatos_permitidos" class="form-control"
                                       value="<?= $e((string) $old('formatos_permitidos', 'pdf')) ?>">
                                <span class="ds-form-help">Permitidos: pdf, jpg, jpeg, png, webp. Separar por coma.</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Tamano maximo MB</label>
                                <input type="number" name="tamano_maximo_mb" class="form-control" min="1" max="50" step="0.5"
                                       value="<?= $e((string) $old('tamano_maximo_mb', '10')) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="ds-form-field">
                                <label style="display:flex; align-items:center; gap:.65rem; cursor:pointer; margin-top:2rem;">
                                    <input type="hidden" name="tiene_vencimiento" value="0">
                                    <input class="form-check-input" type="checkbox" name="tiene_vencimiento" value="1"
                                           id="chkVenc" <?= $checked('tiene_vencimiento') ?>
                                           onchange="document.getElementById('diasVigencia').disabled = !this.checked">
                                    <span style="font-weight:600; color:var(--ds-text);">Tiene vencimiento</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="ds-form-field">
                                <label class="ds-form-label">Dias</label>
                                <input type="number" name="dias_vigencia" class="form-control" id="diasVigencia"
                                       min="1" max="9999"
                                       value="<?= $e((string) $old('dias_vigencia')) ?>"
                                       <?= (int) $old('tiene_vencimiento', 0) !== 1 ? 'disabled' : '' ?>>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-form-actions">
        <a href="<?= $view->url('/configuracion/documentos') ?>" class="ds-btn ds-btn-outline">Cancelar</a>
        <button type="submit" class="ds-btn ds-btn-primary">
            <i class="bi bi-check-lg"></i>Crear tipo
        </button>
    </div>
</form>
<?php $view->endSection() ?>
