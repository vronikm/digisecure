<?php
/**
 * Detalle de un documento del expediente + formulario de revisión.
 *
 * Variables: $user, $csrfToken, $currentPath, $doc, $expediente, $ficha
 * Opcionales: $error (string)
 */

use DigiSecure\Expediente\Domain\Enum\EstadoDocumento;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$fichaId = $ficha['id'];
$docEstado = EstadoDocumento::from($doc['estado']);
$puedeRevisar = in_array($docEstado, [EstadoDocumento::EN_REVISION, EstadoDocumento::RECHAZADO, EstadoDocumento::VENCIDO]);
?>

<?php $view->section('title') ?>Documento — <?= $e($doc['tipo_nombre']) ?><?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>">Guardias</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias/' . $fichaId) ?>"><?= $e($ficha['correlativo']) ?></a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>">Expediente</a></li>
<li class="breadcrumb-item active"><?= $e($doc['tipo_nombre']) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($error) ?></div>
<?php endif ?>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-file-earmark-text me-2"></i><?= $e($doc['tipo_nombre']) ?>
        </h4>
        <div class="d-flex gap-2 align-items-center">
            <code><?= $e($doc['tipo_codigo']) ?></code>
            <span class="badge <?= $docEstado->badgeClass() ?>">
                <i class="bi <?= $docEstado->iconClass() ?> me-1"></i><?= $e($docEstado->label()) ?>
            </span>
            <span class="badge bg-light text-dark border"><?= $e($doc['bloque'] === 'A' ? 'Bloque A' : 'Bloque B') ?></span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($doc['archivo_nombre_storage'])): ?>
        <?php $inline = in_array($doc['archivo_mime'] ?? '', ['application/pdf','image/jpeg','image/png','image/webp'], true); ?>
        <a href="<?= $view->url('/expedientes/documento/' . $doc['id'] . '/descargar') ?>"
           class="btn btn-primary btn-sm" <?= $inline ? 'target="_blank" rel="noopener"' : '' ?>>
            <i class="bi <?= $inline ? 'bi-eye' : 'bi-download' ?> me-1"></i>
            <?= $inline ? 'Ver archivo' : 'Descargar' ?>
        </a>
        <?php endif ?>
        <a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver al expediente
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Info del archivo -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-paperclip me-2"></i>Archivo</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="text-muted" style="width:40%">Nombre original</th><td><?= $e($doc['archivo_nombre_original'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Tipo MIME</th><td><code><?= $e($doc['archivo_mime'] ?? '—') ?></code></td></tr>
                    <tr>
                        <th class="text-muted">Tamaño</th>
                        <td>
                            <?php if ($doc['archivo_tamano_bytes']): ?>
                            <?= number_format($doc['archivo_tamano_bytes'] / 1024, 1) ?> KB
                            <?php else: ?>—<?php endif ?>
                        </td>
                    </tr>
                    <tr><th class="text-muted">Subido por</th><td><?= $e($doc['subido_por'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Fecha subida</th><td><?= $doc['subido_at'] ? date('d/m/Y H:i', strtotime($doc['subido_at'])) : '—' ?></td></tr>
                </table>
                <?php if (!empty($doc['archivo_nombre_storage'])): ?>
                <div class="mt-3 pt-3 border-top">
                    <a href="<?= $view->url('/expedientes/documento/' . $doc['id'] . '/descargar') ?>"
                       class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-cloud-download me-1"></i>Descargar archivo original
                    </a>
                </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <!-- Vigencia y fechas -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header"><i class="bi bi-calendar-event me-2"></i>Vigencia y Fechas</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="text-muted" style="width:40%">Fecha emisión doc.</th><td><?= $doc['fecha_emision_documento'] ? date('d/m/Y', strtotime($doc['fecha_emision_documento'])) : '—' ?></td></tr>
                    <tr>
                        <th class="text-muted">Fecha vencimiento</th>
                        <td>
                            <?php if ($doc['fecha_vencimiento']): ?>
                                <?php
                                $vence = new DateTimeImmutable($doc['fecha_vencimiento']);
                                $hoy = new DateTimeImmutable('today');
                                $diasR = (int) $hoy->diff($vence)->format('%r%a');
                                ?>
                                <span class="<?= $diasR < 0 ? 'text-danger fw-bold' : ($diasR <= 30 ? 'text-warning' : '') ?>">
                                    <?= $vence->format('d/m/Y') ?>
                                    <?php if ($diasR < 0): ?> (vencido hace <?= abs($diasR) ?>d)
                                    <?php elseif ($diasR <= 30): ?> (<?= $diasR ?>d restantes)
                                    <?php endif ?>
                                </span>
                            <?php else: ?>—<?php endif ?>
                        </td>
                    </tr>
                    <tr><th class="text-muted">Fecha obj. actualización</th><td><?= $doc['fecha_objetivo_actualizacion'] ? date('d/m/Y', strtotime($doc['fecha_objetivo_actualizacion'])) : '—' ?></td></tr>
                    <tr><th class="text-muted">Vigencia del tipo</th><td><?= $doc['tiene_vencimiento'] ? $doc['dias_vigencia'] . ' días' : 'Sin vencimiento' ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Validación externa -->
    <?php if ($doc['requiere_validacion_externa']): ?>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header"><i class="bi bi-globe me-2"></i>Validación Externa</div>
            <div class="card-body">
                <p>
                    <strong>URL:</strong>
                    <?php if ($doc['validacion_url']): ?>
                    <a href="<?= $e($doc['validacion_url']) ?>" target="_blank" rel="noopener"><?= $e($doc['validacion_url']) ?></a>
                    <?php else: ?><span class="text-muted">No registrada</span><?php endif ?>
                </p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Screenshot 1</label>
                        <?php if ($doc['screenshot_1_storage']): ?>
                        <div class="border rounded p-2 text-center bg-light">
                            <i class="bi bi-image fs-3 text-success"></i>
                            <div class="small"><?= $e($doc['screenshot_1_storage']) ?></div>
                        </div>
                        <?php else: ?>
                        <div class="border rounded p-3 text-center text-muted bg-light">
                            <i class="bi bi-image fs-3 d-block mb-1"></i>No subido
                        </div>
                        <?php endif ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Screenshot 2</label>
                        <?php if ($doc['screenshot_2_storage']): ?>
                        <div class="border rounded p-2 text-center bg-light">
                            <i class="bi bi-image fs-3 text-success"></i>
                            <div class="small"><?= $e($doc['screenshot_2_storage']) ?></div>
                        </div>
                        <?php else: ?>
                        <div class="border rounded p-3 text-center text-muted bg-light">
                            <i class="bi bi-image fs-3 d-block mb-1"></i>No subido
                        </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif ?>

    <!-- Revisión -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header"><i class="bi bi-clipboard-check me-2"></i>Revisión</div>
            <div class="card-body">
                <?php if ($doc['revisado_por']): ?>
                <div class="mb-3 p-3 bg-light rounded">
                    <strong>Última revisión:</strong>
                    <?= $doc['revisado_at'] ? date('d/m/Y H:i', strtotime($doc['revisado_at'])) : '' ?>
                    por <?= $e($doc['revisado_por']) ?>
                    <?php if ($doc['observacion_revisor']): ?>
                    <div class="mt-2 fst-italic">"<?= $e($doc['observacion_revisor']) ?>"</div>
                    <?php endif ?>
                </div>
                <?php endif ?>

                <?php if ($puedeRevisar && $user->tienePermiso('expedientes.revisar')): ?>
                <form method="post" action="<?= $view->url('/expedientes/documento/' . $doc['id'] . '/revisar') ?>">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label">Observación del revisor</label>
                        <textarea name="observacion" class="form-control" rows="3" placeholder="Opcional: razón del rechazo o comentario..."><?= $e($doc['observacion_revisor'] ?? '') ?></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="accion" value="aceptar" class="btn btn-success"
                                onclick="return confirm('¿Aceptar este documento?')">
                            <i class="bi bi-check-circle me-1"></i>Aceptar
                        </button>
                        <button type="submit" name="accion" value="rechazar" class="btn btn-danger"
                                onclick="return confirm('¿Rechazar este documento?')">
                            <i class="bi bi-x-circle me-1"></i>Rechazar
                        </button>
                    </div>
                </form>
                <?php elseif (!$puedeRevisar): ?>
                <p class="text-muted mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Este documento está en estado <strong><?= $e($docEstado->label()) ?></strong> y no puede revisarse en este momento.
                </p>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
