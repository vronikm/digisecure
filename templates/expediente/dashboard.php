<?php
/**
 * Dashboard del expediente documental de un guardia.
 *
 * Variables: $user, $csrfToken, $currentPath, $ficha, $expediente,
 *            $tiposA, $tiposB, $docsPorTipo, $porVencer
 */

use DigiSecure\Expediente\Domain\Enum\EstadoDocumento;
use DigiSecure\Expediente\Domain\Enum\EstadoExpediente;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estado = EstadoExpediente::from($expediente['estado']);
$fichaId = $ficha['id'];
$expId = $expediente['id'];
$isAspirante = $user->esAspiranteGuardia();
$fichaUrl = $isAspirante ? '/guardias/' . $fichaId . '/editar' : '/guardias/' . $fichaId;
$guardiasLabel = $isAspirante ? 'Mi ficha' : 'Guardias';

$totalA = (int) $expediente['bloque_a_total'];
$aceptA = (int) $expediente['bloque_a_aceptados'];
$totalB = (int) $expediente['bloque_b_total'];
$aceptB = (int) $expediente['bloque_b_aceptados'];
$pctA = $totalA > 0 ? round(($aceptA / $totalA) * 100) : 0;
$pctB = $totalB > 0 ? round(($aceptB / $totalB) * 100) : 0;
$bloqueado = !$expediente['bloque_b_desbloqueado'];

$expEstadoTone = match ($expediente['estado']) {
    'INCOMPLETO'        => 'tone-warning',
    'BLOQUE_A_COMPLETO' => 'tone-info',
    'COMPLETO'          => 'tone-success',
    default             => 'tone-muted',
};

$docTone = static fn(EstadoDocumento $d): string => match ($d) {
    EstadoDocumento::PENDIENTE   => 'tone-muted',
    EstadoDocumento::EN_REVISION => 'tone-warning',
    EstadoDocumento::ACEPTADO    => 'tone-success',
    EstadoDocumento::RECHAZADO   => 'tone-danger',
    EstadoDocumento::VENCIDO     => 'tone-danger',
};

$toneA = $pctA === 100 ? 'tone-success' : ($pctA >= 50 ? '' : 'tone-warning');
$toneB = $pctB === 100 ? 'tone-success' : 'tone-info';
$puedeCargarDocumentos = $isAspirante || $user->tienePermiso('expedientes.cargar') || $user->tienePermiso('expedientes.subir');
$puedeCargarBloqueB = !$isAspirante && $puedeCargarDocumentos;
?>

<?php $view->section('title') ?>Expediente - <?= $e($ficha['correlativo']) ?><?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/guardias') ?>"><?= $e($guardiasLabel) ?></a></li>
<li class="breadcrumb-item"><a href="<?= $view->url($fichaUrl) ?>"><?= $e($ficha['correlativo']) ?></a></li>
<li class="breadcrumb-item active">Expediente</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-folder2-open"></i></span>
        <div>
            <h1>Expediente documental</h1>
            <small style="display: flex; align-items: center; gap: .55rem; flex-wrap: wrap;">
                <strong style="color: var(--ds-text);"><?= $e($ficha['apellidos']) ?> <?= $e($ficha['nombres']) ?></strong>
                <span class="ds-cell-mono" style="font-weight: 700;"><?= $e($ficha['correlativo']) ?></span>
                <span class="ds-badge <?= $expEstadoTone ?>"><?= $e($estado->label()) ?></span>
            </small>
        </div>
    </div>
    <a href="<?= $view->url($fichaUrl) ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver a ficha
    </a>
</div>

<?php if ($isAspirante): ?>
<div class="login-info" style="margin-bottom: 1.25rem;">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Carga tus documentos digitales</strong>
        Puedes subir, ver y reemplazar archivos del Bloque A en los formatos permitidos por cada requisito. El Bloque B lo gestiona administracion cuando el Bloque A este aceptado.
    </div>
</div>
<?php endif ?>

<?php if ($porVencer !== []): ?>
<div class="ds-card" style="border-left: 4px solid #d97706; margin-bottom: 1.25rem;">
    <div class="ds-card-body" style="display: flex; gap: .85rem; align-items: flex-start;">
        <span style="color: #d97706; font-size: 1.4rem; line-height: 1;"><i class="bi bi-exclamation-triangle-fill"></i></span>
        <div style="flex: 1;">
            <div style="font-weight: 700; color: #b45309; margin-bottom: .35rem;">Documentos proximos a vencer</div>
            <ul style="margin: 0; padding-left: 1.1rem; color: var(--ds-text-soft); font-size: .85rem; line-height: 1.6;">
                <?php foreach ($porVencer as $pv): ?>
                <li>
                    <strong style="color: var(--ds-text);"><?= $e($pv['tipo_nombre']) ?></strong>
                    &mdash; vence el <span class="ds-cell-mono"><?= date('d/m/Y', strtotime($pv['fecha_vencimiento'])) ?></span>
                    <span style="color: #b45309; font-weight: 600;">(<?= (int) $pv['dias_restantes'] ?> dia<?= (int) $pv['dias_restantes'] !== 1 ? 's' : '' ?>)</span>
                </li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
</div>
<?php endif ?>

<!-- Resumen de progreso -->
<div class="row g-3" style="margin-bottom: 1.25rem;">
    <div class="col-md-6">
        <div class="ds-card" style="margin-bottom: 0; height: 100%;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-shield-check"></i></span>
                <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <div class="ds-card-title">Bloque A &middot; Admisibilidad</div>
                        <div class="ds-card-subtitle">26 documentos pre-empleo</div>
                    </div>
                    <span class="ds-badge <?= $pctA === 100 ? 'tone-success' : 'tone-info' ?> no-dot"><?= $aceptA ?>/<?= $totalA ?></span>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-progress-row">
                    <div class="ds-progress" style="height: 10px;">
                        <div class="ds-progress-bar <?= $toneA ?>" style="width: <?= $pctA ?>%"></div>
                    </div>
                    <small><strong><?= $pctA ?>%</strong></small>
                </div>
                <div class="ds-cell-sub" style="margin-top: .5rem;"><?= $pctA ?>% completado del bloque pre-empleo</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="ds-card <?= $bloqueado ? 'is-locked' : '' ?>" style="margin-bottom: 0; height: 100%; <?= $bloqueado ? 'opacity: .85;' : '' ?>">
            <div class="ds-card-header <?= $bloqueado ? '' : 'tone-success' ?>">
                <span class="ds-card-mark">
                    <i class="bi <?= $bloqueado ? 'bi-lock' : 'bi-unlock-fill' ?>"></i>
                </span>
                <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <div class="ds-card-title">Bloque B &middot; Vinculacion laboral</div>
                        <div class="ds-card-subtitle"><?= $bloqueado ? 'Bloqueado &mdash; pendiente Bloque A' : 'Carga administrativa, aceptacion automatica' ?></div>
                    </div>
                    <?php if (!$bloqueado): ?>
                        <span class="ds-badge <?= $pctB === 100 ? 'tone-success' : 'tone-info' ?> no-dot"><?= $aceptB ?>/<?= $totalB ?></span>
                    <?php endif ?>
                </div>
            </div>
            <div class="ds-card-body">
                <?php if ($bloqueado): ?>
                <div class="ds-cell-sub" style="display: flex; gap: .45rem; align-items: flex-start;">
                    <i class="bi bi-info-circle" style="color: #2563eb; font-size: 1rem;"></i>
                    <span>Se desbloquea cuando el Bloque A este 100% aceptado.</span>
                </div>
                <?php else: ?>
                <div class="ds-progress-row">
                    <div class="ds-progress" style="height: 10px;">
                        <div class="ds-progress-bar <?= $toneB ?>" style="width: <?= $pctB ?>%"></div>
                    </div>
                    <small><strong><?= $pctB ?>%</strong></small>
                </div>
                <div class="ds-cell-sub" style="margin-top: .5rem;"><?= $pctB ?>% completado &mdash; documentos post-contratacion cargados por administracion</div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<!-- Bloque A: Documentos -->
<div class="ds-card" style="margin-bottom: 1.25rem;">
    <div class="ds-card-header">
        <span class="ds-card-mark"><i class="bi bi-shield-check"></i></span>
        <div>
            <div class="ds-card-title">Bloque A &middot; Documentos de admisibilidad</div>
            <div class="ds-card-subtitle">Pre-empleo &mdash; carga obligatoria antes de la vinculacion</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Documento</th>
                    <th>Estado</th>
                    <th>Vencimiento</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tiposA as $tipo):
                    $doc = $docsPorTipo[(string) ($tipo['codigo'] ?? '')] ?? $docsPorTipo[$tipo['id']] ?? null;
                    $docEstado = $doc ? EstadoDocumento::from($doc['estado']) : null;
                ?>
                <tr>
                    <td><span class="ds-cell-sub"><?= (int) $tipo['orden'] ?></span></td>
                    <td>
                        <div class="ds-cell-name"><?= $e($tipo['nombre']) ?></div>
                        <div class="ds-cell-sub" style="display: flex; flex-wrap: wrap; gap: .45rem;">
                            <?php if ($tipo['obligatorio']): ?>
                                <span style="color: #dc2626; font-weight: 600;">Obligatorio</span>
                            <?php else: ?>
                                <span>Opcional</span>
                            <?php endif ?>
                            <?php if ($tipo['requiere_validacion_externa']): ?>
                                <span><i class="bi bi-globe"></i> Validacion externa</span>
                            <?php endif ?>
                            <?php if ($tipo['tiene_vencimiento']): ?>
                                <span><i class="bi bi-calendar-event"></i> <?= $tipo['dias_vigencia'] ?>d vigencia</span>
                            <?php endif ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($doc): ?>
                            <span class="ds-badge <?= $docTone($docEstado) ?>"><i class="bi <?= $docEstado->iconClass() ?>"></i><?= $e($docEstado->label()) ?></span>
                        <?php else: ?>
                            <span class="ds-badge tone-muted no-dot"><i class="bi bi-dash-circle"></i>Sin subir</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($doc && $doc['fecha_vencimiento']):
                            $vence = new DateTimeImmutable($doc['fecha_vencimiento']);
                            $hoy = new DateTimeImmutable('today');
                            $diasR = (int) $hoy->diff($vence)->format('%r%a');
                        ?>
                            <?php if ($diasR < 0): ?>
                                <span class="ds-badge tone-danger no-dot"><i class="bi bi-exclamation-triangle"></i><?= $vence->format('d/m/Y') ?> &middot; vencido</span>
                            <?php elseif ($diasR <= 30): ?>
                                <span class="ds-badge tone-warning no-dot"><?= $vence->format('d/m/Y') ?> &middot; <?= $diasR ?>d</span>
                            <?php else: ?>
                                <span class="ds-cell-mono"><?= $vence->format('d/m/Y') ?></span>
                            <?php endif ?>
                        <?php else: ?>
                                    <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <?php if ($doc): ?>
                            <a href="<?= $view->url('/expedientes/documento/' . $doc['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm" title="Ver detalle">
                                Ver
                            </a>
                            <?php endif ?>
                            <?php if ($puedeCargarDocumentos): ?>
                            <a href="<?= $view->url('/expedientes/' . $expId . '/documento/' . $tipo['id']) ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="<?= $doc ? 'Reemplazar' : 'Subir' ?>">
                                <?= $doc ? 'Reemplazar' : 'Subir' ?>
                            </a>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bloque B: Documentos -->
<div class="ds-card" style="<?= $bloqueado ? 'opacity: .55;' : '' ?>">
    <div class="ds-card-header <?= $bloqueado ? '' : 'tone-success' ?>">
        <span class="ds-card-mark">
            <i class="bi <?= $bloqueado ? 'bi-lock' : 'bi-unlock-fill' ?>"></i>
        </span>
        <div>
            <div class="ds-card-title">Bloque B &middot; Documentos de vinculacion laboral</div>
            <div class="ds-card-subtitle">Post-contratacion &mdash; <?= $bloqueado ? 'pendiente desbloqueo' : 'carga administrativa con aceptacion automatica' ?></div>
        </div>
    </div>
    <?php if ($bloqueado): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state" style="padding: 1.75rem 1rem;">
            <div class="ds-empty-icon" style="background: rgba(100,116,139,.1); color: #64748b;"><i class="bi bi-lock-fill"></i></div>
            <h3>Bloque bloqueado</h3>
            <p>Completa el 100% del Bloque A para desbloquear los documentos post-contratacion.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Documento</th>
                    <th>Estado</th>
                    <th>Vencimiento</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tiposB as $tipo):
                    $doc = $docsPorTipo[(string) ($tipo['codigo'] ?? '')] ?? $docsPorTipo[$tipo['id']] ?? null;
                    $docEstado = $doc ? EstadoDocumento::from($doc['estado']) : null;
                ?>
                <tr>
                    <td><span class="ds-cell-sub"><?= (int) $tipo['orden'] ?></span></td>
                    <td>
                        <div class="ds-cell-name"><?= $e($tipo['nombre']) ?></div>
                        <div class="ds-cell-sub">
                            <?php if ($tipo['obligatorio']): ?>
                                <span style="color: #dc2626; font-weight: 600;">Obligatorio</span>
                            <?php else: ?>
                                Opcional
                            <?php endif ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($doc): ?>
                            <span class="ds-badge <?= $docTone($docEstado) ?>"><i class="bi <?= $docEstado->iconClass() ?>"></i><?= $e($docEstado->label()) ?></span>
                        <?php else: ?>
                            <span class="ds-badge tone-muted no-dot"><i class="bi bi-dash-circle"></i>Sin subir</span>
                        <?php endif ?>
                    </td>
                            <td><span class="ds-cell-sub">-</span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <?php if ($doc): ?>
                            <a href="<?= $view->url('/expedientes/documento/' . $doc['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm" title="Ver">
                                Ver
                            </a>
                            <?php endif ?>
                            <?php if ($puedeCargarBloqueB): ?>
                            <a href="<?= $view->url('/expedientes/' . $expId . '/documento/' . $tipo['id']) ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="<?= $doc ? 'Reemplazar' : 'Subir' ?>">
                                <?= $doc ? 'Reemplazar' : 'Subir' ?>
                            </a>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
