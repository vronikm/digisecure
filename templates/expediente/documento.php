<?php
/**
 * Detalle de un documento del expediente + formulario de revision.
 *
 * Variables: $user, $csrfToken, $currentPath, $doc, $expediente, $ficha, $nombresUsuarios
 * Opcionales: $error (string)
 */

use DigiSecure\Expediente\Application\DescargarDocumentoService;
use DigiSecure\Expediente\Domain\Enum\EstadoDocumento;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$nombresUsuarios = $nombresUsuarios ?? [];
$nombreUsuario = static fn(?string $id): string => $id === null || $id === '' ? '-' : ($nombresUsuarios[$id] ?? 'Usuario no disponible');

$fichaId = $ficha['id'];
$docEstado = EstadoDocumento::from($doc['estado']);
$esSegundaRevisionAceptado = $docEstado === EstadoDocumento::ACEPTADO;
$puedeRevisar = in_array($docEstado, [EstadoDocumento::EN_REVISION, EstadoDocumento::RECHAZADO, EstadoDocumento::VENCIDO, EstadoDocumento::ACEPTADO], true);
$puedeValidarDocumentos = $user->tienePermiso('expedientes.validar') || $user->tienePermiso('expedientes.revisar');
$isAspirante = $user->esAspiranteGuardia();
$fichaUrl = $isAspirante ? '/guardias/' . $fichaId . '/editar' : '/guardias/' . $fichaId;
$guardiasLabel = $isAspirante ? 'Mi ficha' : 'Guardias';

$docTone = match ($docEstado) {
    EstadoDocumento::PENDIENTE   => 'tone-muted',
    EstadoDocumento::EN_REVISION => 'tone-warning',
    EstadoDocumento::ACEPTADO    => 'tone-success',
    EstadoDocumento::RECHAZADO   => 'tone-danger',
    EstadoDocumento::VENCIDO     => 'tone-danger',
};

$bloqueTone = $doc['bloque'] === 'A' ? 'tone-info' : 'tone-warning';

$ds_dt = 'font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ds-text-soft); margin-bottom: .15rem;';
$ds_dd = 'font-size: .9rem; color: var(--ds-text); font-weight: 600;';

// Archivo y vista previa
$tieneArchivo = !empty($doc['archivo_nombre_storage']);
$mime = (string) ($doc['archivo_mime'] ?? '');
$previsualizable = $tieneArchivo && DescargarDocumentoService::admiteVistaPrevia($mime);
$esPdf = $mime === 'application/pdf';
$urlVer = $view->url('/expedientes/documento/' . $doc['id'] . '/ver');
$urlDescargar = $view->url('/expedientes/documento/' . $doc['id'] . '/descargar');
$nombreArchivo = (string) ($doc['archivo_nombre_original'] ?? '') !== '' ? (string) $doc['archivo_nombre_original'] : (string) $doc['tipo_nombre'];
$bytes = (int) ($doc['archivo_tamano_bytes'] ?? 0);
$tamanoTxt = $bytes <= 0 ? '' : ($bytes >= 1048576
    ? number_format($bytes / 1048576, 1, ',', '.') . ' MB'
    : number_format(max(1, $bytes / 1024), 0, ',', '.') . ' KB');
$formatoTxt = match ($mime) {
    'application/pdf' => 'PDF',
    'image/jpeg'      => 'Imagen JPG',
    'image/png'       => 'Imagen PNG',
    'image/webp'      => 'Imagen WEBP',
    ''                => '-',
    default           => strtoupper((string) pathinfo($nombreArchivo, PATHINFO_EXTENSION)) ?: $mime,
};
?>

<?php $view->section('title') ?>Documento - <?= $e($doc['tipo_nombre']) ?><?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>"><?= $e($guardiasLabel) ?></a></li>
<li class="breadcrumb-item"><a href="<?= $view->url($fichaUrl) ?>"><?= $e($ficha['correlativo']) ?></a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>">Expediente</a></li>
<li class="breadcrumb-item active"><?= $e($doc['tipo_nombre']) ?></li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>

<?php if (!empty($error)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">Error en la operación</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-file-earmark-text"></i></span>
        <div>
            <h1><?= $e($doc['tipo_nombre']) ?></h1>
            <small style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <span class="ds-cell-mono"><?= $e($doc['tipo_codigo']) ?></span>
                <span class="ds-badge <?= $docTone ?>"><i class="bi <?= $docEstado->iconClass() ?>"></i><?= $e($docEstado->label()) ?></span>
                <span class="ds-badge <?= $bloqueTone ?> no-dot">Bloque <?= $e((string) $doc['bloque']) ?></span>
            </small>
        </div>
    </div>
    <div class="ds-page-actions">
        <a href="<?= $view->url('/guardias/' . $fichaId . '/expediente') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Volver al expediente
        </a>
    </div>
</div>

<div class="row g-4 ds-doc-layout">
    <!-- Vista previa del archivo (sin descargarlo) -->
    <div class="col-xl-7">
        <?php if ($previsualizable): ?>
        <section class="ds-card ds-visor" aria-label="Vista previa del documento"
                 data-visor
                 data-src="<?= $e($urlVer) ?>"
                 data-tipo="<?= $esPdf ? 'pdf' : 'imagen' ?>"
                 data-mime="<?= $e($mime) ?>"
                 data-nombre="<?= $e($nombreArchivo) ?>"
                 data-pdfjs="<?= $e($view->url('/assets/lib/pdfjs-4.10.38/')) ?>">
            <div class="ds-card-header ds-visor-cabecera">
                <span class="ds-card-mark"><i class="bi <?= $esPdf ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image' ?>"></i></span>
                <div class="ds-visor-titulo">
                    <div class="ds-card-title">Vista previa</div>
                    <div class="ds-card-subtitle" title="<?= $e($nombreArchivo) ?>"><?= $e($nombreArchivo) ?><?= $tamanoTxt !== '' ? ' · ' . $e($tamanoTxt) : '' ?></div>
                </div>
                <div class="ds-visor-salidas">
                    <a href="<?= $e($urlVer) ?>" target="_blank" rel="noopener" class="ds-visor-btn"
                       title="Abrir en otra pestaña" aria-label="Abrir en otra pestaña">
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                    </a>
                    <a href="<?= $e($urlDescargar) ?>" class="ds-visor-btn ds-visor-btn-texto"
                       title="Descargar archivo" aria-label="Descargar archivo">
                        <i class="bi bi-download" aria-hidden="true"></i><span>Descargar</span>
                    </a>
                    <button type="button" class="ds-visor-btn ds-visor-btn-principal" data-visor-accion="completo"
                            title="Pantalla completa" aria-label="Pantalla completa" aria-pressed="false">
                        <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="ds-visor-barra" role="toolbar" aria-label="Controles del visor">
                <div class="ds-visor-grupo ds-visor-paginacion">
                    <button type="button" class="ds-visor-btn" data-visor-accion="anterior" disabled
                            title="Página anterior" aria-label="Página anterior">
                        <i class="bi bi-chevron-up" aria-hidden="true"></i>
                    </button>
                    <span class="ds-visor-pagina" data-visor-pagina aria-live="polite" title="Página actual">– / –</span>
                    <button type="button" class="ds-visor-btn" data-visor-accion="siguiente" disabled
                            title="Página siguiente" aria-label="Página siguiente">
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="ds-visor-grupo">
                    <button type="button" class="ds-visor-btn" data-visor-accion="alejar" disabled
                            title="Alejar (−)" aria-label="Alejar">
                        <i class="bi bi-zoom-out" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="ds-visor-btn ds-visor-zoom" data-visor-accion="ajustar" disabled
                            title="Ajustar al ancho (0)" aria-label="Ajustar al ancho" aria-pressed="true">
                        <span data-visor-zoom>100%</span>
                    </button>
                    <button type="button" class="ds-visor-btn" data-visor-accion="acercar" disabled
                            title="Acercar (+)" aria-label="Acercar">
                        <i class="bi bi-zoom-in" aria-hidden="true"></i>
                    </button>
                </div>
                <button type="button" class="ds-visor-btn" data-visor-accion="girar" disabled
                        title="Girar 90° (R)" aria-label="Girar 90 grados">
                    <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                </button>
            </div>

            <div class="ds-visor-lienzo" data-visor-lienzo tabindex="0"
                 aria-label="Documento: usa + y − para el zoom">
                <div class="ds-visor-paginas" data-visor-paginas></div>
                <div class="ds-visor-estado" data-visor-estado data-estado="cargando" role="status">
                    <span class="ds-visor-estado-icono" aria-hidden="true">
                        <i class="bi bi-hourglass-split"></i>
                        <i class="bi bi-exclamation-triangle"></i>
                    </span>
                    <p data-visor-mensaje>Cargando el documento…</p>
                    <div class="ds-visor-progreso"><span data-visor-progreso></span></div>
                    <div class="ds-visor-estado-acciones" data-visor-error hidden>
                        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" data-visor-accion="reintentar">
                            <i class="bi bi-arrow-repeat"></i>Reintentar
                        </button>
                        <a href="<?= $e($urlDescargar) ?>" class="ds-btn ds-btn-primary ds-btn-sm">
                            <i class="bi bi-download"></i>Descargar
                        </a>
                    </div>
                </div>
                <noscript>
                    <div class="ds-visor-estado" data-estado="error">
                        <p>La vista previa necesita JavaScript.</p>
                        <a href="<?= $e($urlVer) ?>" target="_blank" rel="noopener" class="ds-btn ds-btn-primary ds-btn-sm">Abrir el archivo</a>
                    </div>
                </noscript>
            </div>
        </section>
        <?php else: ?>
        <section class="ds-card ds-visor is-vacio" aria-label="Vista previa del documento">
            <div class="ds-card-header ds-visor-cabecera">
                <span class="ds-card-mark"><i class="bi bi-eye-slash"></i></span>
                <div class="ds-visor-titulo">
                    <div class="ds-card-title">Vista previa</div>
                    <div class="ds-card-subtitle"><?= $tieneArchivo ? $e($nombreArchivo) : 'Sin archivo' ?></div>
                </div>
            </div>
            <div class="ds-visor-vacio">
                <?php if ($tieneArchivo): ?>
                <span class="ds-visor-vacio-icono"><i class="bi bi-file-earmark-arrow-down"></i></span>
                <strong>Este formato no se puede ver aquí</strong>
                <p>Los archivos <?= $e($formatoTxt) ?> se abren con su propio programa. Descárgalo para revisarlo.</p>
                <a href="<?= $e($urlDescargar) ?>" class="ds-btn ds-btn-primary ds-btn-sm">
                    <i class="bi bi-download"></i>Descargar<?= $tamanoTxt !== '' ? ' (' . $e($tamanoTxt) . ')' : '' ?>
                </a>
                <?php else: ?>
                <span class="ds-visor-vacio-icono"><i class="bi bi-cloud-arrow-up"></i></span>
                <strong>Todavía no se ha subido el archivo</strong>
                <p>Cuando se cargue el documento podrás verlo aquí sin descargarlo.</p>
                <?php endif ?>
            </div>
        </section>
        <?php endif ?>
    </div>

    <div class="col-xl-5">
    <div class="ds-doc-lateral">
    <!-- Info del archivo -->
        <div class="ds-card" style="margin-bottom: 0;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-paperclip"></i></span>
                <div>
                    <div class="ds-card-title">Archivo</div>
                    <div class="ds-card-subtitle">Metadatos del documento subido</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div style="<?= $ds_dt ?>">Nombre original</div>
                    <div style="<?= $ds_dd ?> overflow-wrap: anywhere;"><?= $e($doc['archivo_nombre_original'] ?? '-') ?></div>
                    </div>
                    <div class="col-6">
                        <div style="<?= $ds_dt ?>">Formato</div>
                        <div style="<?= $ds_dd ?>" title="<?= $e($mime) ?>"><?= $e($formatoTxt) ?></div>
                    </div>
                    <div class="col-6">
                        <div style="<?= $ds_dt ?>">Tamaño</div>
                        <div class="ds-cell-mono"><?= $tamanoTxt !== '' ? $e($tamanoTxt) : '-' ?></div>
                    </div>
                    <div class="col-6">
                        <div style="<?= $ds_dt ?>">Subido por</div>
                    <div style="<?= $ds_dd ?>"><?= $e($nombreUsuario($doc['subido_por'] ?? null)) ?></div>
                    </div>
                    <div class="col-6">
                        <div style="<?= $ds_dt ?>">Fecha subida</div>
                    <div class="ds-cell-mono"><?= $doc['subido_at'] ? date('d/m/Y H:i', strtotime($doc['subido_at'])) : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Vigencia y fechas -->
        <div class="ds-card" style="margin-bottom: 0;">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-calendar-event"></i></span>
                <div>
                    <div class="ds-card-title">Vigencia y fechas</div>
                    <div class="ds-card-subtitle">Caducidad y recordatorios</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Fecha emisión doc.</div>
                    <div class="ds-cell-mono"><?= $doc['fecha_emision_documento'] ? date('d/m/Y', strtotime($doc['fecha_emision_documento'])) : '-' ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Fecha vencimiento</div>
                        <div>
                            <?php if (!empty($doc['fecha_vencimiento'])):
                                $vence = new DateTimeImmutable($doc['fecha_vencimiento']);
                                $hoy = new DateTimeImmutable('today');
                                $diasR = (int) $hoy->diff($vence)->format('%r%a');
                            ?>
                                <?php if ($diasR < 0): ?>
                                    <span class="ds-badge tone-danger no-dot"><i class="bi bi-exclamation-triangle"></i><?= $vence->format('d/m/Y') ?> &middot; vencido <?= abs($diasR) ?>d</span>
                                <?php elseif ($diasR <= 30): ?>
                                    <span class="ds-badge tone-warning no-dot"><?= $vence->format('d/m/Y') ?> &middot; <?= $diasR ?>d restantes</span>
                                <?php else: ?>
                                    <span class="ds-cell-mono" style="font-weight: 700;"><?= $vence->format('d/m/Y') ?></span>
                                <?php endif ?>
                            <?php else: ?>
                                    <span class="ds-cell-sub">-</span>
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Fecha obj. actualización</div>
                    <div class="ds-cell-mono"><?= $doc['fecha_objetivo_actualizacion'] ? date('d/m/Y', strtotime($doc['fecha_objetivo_actualizacion'])) : '-' ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Vigencia del tipo</div>
                        <div style="<?= $ds_dd ?>"><?= $doc['tiene_vencimiento'] ? $doc['dias_vigencia'] . ' días' : 'Sin vencimiento' ?></div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Validacion externa -->
    <?php if ($doc['requiere_validacion_externa'] && $puedeValidarDocumentos): ?>
        <div class="ds-card" style="margin-bottom: 0;">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-globe"></i></span>
                <div>
                    <div class="ds-card-title">Validación externa</div>
                    <div class="ds-card-subtitle">Verificación en sitio web oficial + capturas</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-form-field" style="margin-bottom: 1.25rem;">
                    <label class="ds-form-label">URL del sitio</label>
                    <?php if (!empty($doc['validacion_url'])): ?>
                    <a href="<?= $e($doc['validacion_url']) ?>" target="_blank" rel="noopener"
                       class="ds-cell-mono" style="color: #2563eb; text-decoration: none; word-break: break-all;">
                        <i class="bi bi-box-arrow-up-right"></i> <?= $e($doc['validacion_url']) ?>
                    </a>
                    <?php else: ?>
                    <span class="ds-cell-sub">No registrada</span>
                    <?php endif ?>
                </div>
                <div class="row g-3">
                    <?php foreach ([1, 2] as $i):
                        $key = 'screenshot_' . $i . '_storage';
                        $tieneShot = !empty($doc[$key]);
                    ?>
                    <div class="col-sm-6">
                        <div style="<?= $ds_dt ?>; margin-bottom: .35rem;">Screenshot <?= $i ?></div>
                        <?php if ($tieneShot): ?>
                        <div style="display: flex; align-items: center; gap: .65rem; padding: .85rem 1rem; background: rgba(5,150,105,.08); border: 1px solid rgba(5,150,105,.18); border-radius: 10px;">
                            <span style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: rgba(5,150,105,.15); color: #059669; font-size: 1.2rem;">
                                <i class="bi bi-image"></i>
                            </span>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 600; color: #047857; font-size: .85rem;">Capturado</div>
                                <div class="ds-cell-mono" style="font-size: .72rem; word-break: break-all;"><?= $e($doc[$key]) ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="display: flex; align-items: center; gap: .65rem; padding: .85rem 1rem; background: #f4f7fb; border: 1px dashed #cbd5e1; border-radius: 10px;">
                            <span style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #e2e8f0; color: #94a3b8; font-size: 1.2rem;">
                                <i class="bi bi-image"></i>
                            </span>
                            <div class="ds-cell-sub">Pendiente de subir</div>
                        </div>
                        <?php endif ?>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    <?php endif ?>

    <!-- Revision (se omite si no hay nada que mostrar, p. ej. al aspirante con el documento en revision) -->
    <?php if (!empty($doc['revisado_por']) || !$puedeRevisar || $puedeValidarDocumentos): ?>
        <div class="ds-card" style="margin-bottom: 0;">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-clipboard-check"></i></span>
                <div>
                    <div class="ds-card-title">Revisión</div>
                    <div class="ds-card-subtitle">Aceptación o rechazo del revisor</div>
                </div>
            </div>
            <div class="ds-card-body">
                <?php if (!empty($doc['revisado_por'])): ?>
                <div style="background: #f4f7fb; border-radius: 12px; padding: 1rem 1.1rem; margin-bottom: 1.25rem; border-left: 3px solid #2f80ed;">
                    <div style="font-weight: 700; color: var(--ds-text); font-size: .85rem; margin-bottom: .25rem;">
                        Última revisión
                    </div>
                    <div class="ds-cell-sub" style="margin-bottom: .35rem;">
                        <span class="ds-cell-mono"><?= $doc['revisado_at'] ? date('d/m/Y H:i', strtotime($doc['revisado_at'])) : '' ?></span>
                        por <strong style="color: var(--ds-text);"><?= $e($nombreUsuario($doc['revisado_por'])) ?></strong>
                    </div>
                    <?php if (!empty($doc['observacion_revisor'])): ?>
                    <div style="font-style: italic; color: var(--ds-text); font-size: .9rem; padding: .55rem .75rem; background: #fff; border-radius: 8px; margin-top: .5rem;">
                        &ldquo;<?= $e($doc['observacion_revisor']) ?>&rdquo;
                    </div>
                    <?php endif ?>
                </div>
                <?php endif ?>

                <?php if ($puedeRevisar && $puedeValidarDocumentos): ?>
                <?php if ($esSegundaRevisionAceptado): ?>
                <div class="login-info" style="margin-bottom: 1rem;">
                    <i class="bi bi-arrow-repeat"></i>
                    <div>
                        <strong>Segunda revisión</strong>
                        Este documento ya fue aceptado. Si encuentras una novedad posterior, rechazalo con el motivo para que el usuario pueda subir una correccion.
                    </div>
                </div>
                <?php endif ?>
                <form method="post" action="<?= $view->url('/expedientes/documento/' . $doc['id'] . '/revisar') ?>" class="ds-form-main" data-review-document-form>
                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                    <div class="ds-form-field" style="margin-bottom: 1rem;">
                        <label class="ds-form-label">Observación del revisor</label>
                        <textarea name="observacion" class="form-control" rows="3"
                                  placeholder="<?= $esSegundaRevisionAceptado ? 'Indica la novedad encontrada para que el usuario pueda corregir...' : 'Obligatoria si vas a rechazar; opcional si vas a aceptar...' ?>"
                                  <?= $esSegundaRevisionAceptado ? 'required' : '' ?>><?= $e($doc['observacion_revisor'] ?? '') ?></textarea>
                        <span class="ds-form-help"><?= $esSegundaRevisionAceptado ? 'Este motivo quedará visible para que el usuario suba una nueva versión.' : 'Al rechazar, el usuario podrá corregir y cargar nuevamente el archivo.' ?></span>
                    </div>
                    <div style="display: flex; gap: .55rem; flex-wrap: wrap;">
                        <?php if (!$esSegundaRevisionAceptado): ?>
                        <button type="submit" name="accion" value="aceptar" class="ds-btn ds-btn-success"
                                data-confirm
                                data-confirm-title="Aceptar documento"
                                data-confirm-text="Confirmas que el documento cumple los requisitos?"
                                data-confirm-type="success"
                                data-confirm-button="Si, aceptar">
                            <i class="bi bi-check-circle"></i>Aceptar documento
                        </button>
                        <?php endif ?>
                        <button type="submit" name="accion" value="rechazar" class="ds-btn ds-btn-danger"
                                data-reject-document
                                data-confirm
                                data-confirm-title="<?= $esSegundaRevisionAceptado ? 'Rechazar documento aceptado' : 'Rechazar documento' ?>"
                                data-confirm-text="<?= $esSegundaRevisionAceptado ? 'El documento volvera a estado rechazado y el usuario podra cargar una correccion. Confirmas?' : 'El documento quedara rechazado y el usuario podra cargar una correccion. Confirmas?' ?>"
                                data-confirm-type="danger"
                                data-confirm-button="Si, rechazar">
                            <i class="bi bi-x-circle"></i><?= $esSegundaRevisionAceptado ? 'Rechazar en segunda revision' : 'Rechazar documento' ?>
                        </button>
                    </div>
                </form>
                <?php elseif (!$puedeRevisar): ?>
                <div class="login-info" style="margin: 0;">
                    <i class="bi bi-info-circle"></i>
                    <div>
                        <strong>Documento no revisable</strong>
                        Este documento está en estado <strong><?= $e($docEstado->label()) ?></strong> y no puede revisarse en este momento.
                    </div>
                </div>
                <?php endif ?>
            </div>
        </div>
    <?php endif ?>
    </div><!-- /.ds-doc-lateral -->
    </div>
</div>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<?php if ($previsualizable): ?>
<script src="<?= $view->url('/assets/js/ds-visor-documento.js') ?>?v=1.0" defer></script>
<?php endif ?>
<script>
(() => {
    const form = document.querySelector('[data-review-document-form]');
    if (!form) {
        return;
    }

    const observacion = form.querySelector('textarea[name="observacion"]');
    const rejectButtons = form.querySelectorAll('[data-reject-document]');
    const acceptButtons = form.querySelectorAll('button[name="accion"][value="aceptar"]');

    rejectButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (observacion) {
                observacion.required = true;
            }
        });
    });

    acceptButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (observacion) {
                observacion.required = false;
            }
        });
    });
})();
</script>
<?php $view->endSection() ?>
