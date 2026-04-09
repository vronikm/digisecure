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

$totalA = (int) $expediente['bloque_a_total'];
$aceptA = (int) $expediente['bloque_a_aceptados'];
$totalB = (int) $expediente['bloque_b_total'];
$aceptB = (int) $expediente['bloque_b_aceptados'];
$pctA = $totalA > 0 ? round(($aceptA / $totalA) * 100) : 0;
$pctB = $totalB > 0 ? round(($aceptB / $totalB) * 100) : 0;
$bloqueado = !$expediente['bloque_b_desbloqueado'];
?>

<?php $view->section('title') ?>Expediente — <?= $e($ficha['correlativo']) ?><?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="/guardias">Guardias</a></li>
<li class="breadcrumb-item"><a href="/guardias/<?= $e($fichaId) ?>"><?= $e($ficha['correlativo']) ?></a></li>
<li class="breadcrumb-item active">Expediente</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>

<!-- Cabecera -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-folder2-open me-2"></i>Expediente Documental
        </h4>
        <div class="d-flex gap-2 align-items-center">
            <span class="fw-semibold"><?= $e($ficha['apellidos']) ?> <?= $e($ficha['nombres']) ?></span>
            <code><?= $e($ficha['correlativo']) ?></code>
            <span class="badge <?= $estado->badgeClass() ?>"><?= $e($estado->label()) ?></span>
        </div>
    </div>
    <a href="/guardias/<?= $e($fichaId) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver a ficha
    </a>
</div>

<!-- Alertas de documentos por vencer -->
<?php if ($porVencer !== []): ?>
<div class="alert alert-warning shadow-sm">
    <h6 class="alert-heading mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Documentos próximos a vencer</h6>
    <ul class="mb-0">
        <?php foreach ($porVencer as $pv): ?>
        <li>
            <strong><?= $e($pv['tipo_nombre']) ?></strong> — vence el
            <?= date('d/m/Y', strtotime($pv['fecha_vencimiento'])) ?>
            (<?= (int) $pv['dias_restantes'] ?> día<?= (int) $pv['dias_restantes'] !== 1 ? 's' : '' ?>)
        </li>
        <?php endforeach ?>
    </ul>
</div>
<?php endif ?>

<!-- Resumen de progreso -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>Bloque A — Admisibilidad</h6>
                    <span class="badge <?= $pctA === 100 ? 'bg-success' : 'bg-primary' ?>"><?= $aceptA ?>/<?= $totalA ?></span>
                </div>
                <div class="progress mb-1" style="height:10px">
                    <div class="progress-bar <?= $pctA === 100 ? 'bg-success' : 'bg-primary' ?>" style="width:<?= $pctA ?>%"></div>
                </div>
                <small class="text-muted"><?= $pctA ?>% completado — 26 documentos pre-empleo</small>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100 <?= $bloqueado ? 'border-secondary' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">
                        <?php if ($bloqueado): ?>
                        <i class="bi bi-lock me-2 text-muted"></i>
                        <?php else: ?>
                        <i class="bi bi-unlock me-2 text-success"></i>
                        <?php endif ?>
                        Bloque B — Vinculación Laboral
                    </h6>
                    <?php if (!$bloqueado): ?>
                    <span class="badge <?= $pctB === 100 ? 'bg-success' : 'bg-info' ?>"><?= $aceptB ?>/<?= $totalB ?></span>
                    <?php endif ?>
                </div>
                <?php if ($bloqueado): ?>
                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>Se desbloquea cuando el Bloque A esté 100% aceptado.
                </p>
                <?php else: ?>
                <div class="progress mb-1" style="height:10px">
                    <div class="progress-bar bg-info" style="width:<?= $pctB ?>%"></div>
                </div>
                <small class="text-muted"><?= $pctB ?>% completado — 4 documentos post-contratación</small>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<!-- Bloque A: Lista de documentos -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <i class="bi bi-shield-check me-2"></i>Bloque A — Documentos de Admisibilidad (Pre-empleo)
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:30px">#</th>
                    <th>Documento</th>
                    <th>Estado</th>
                    <th>Vencimiento</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tiposA as $tipo):
                    $doc = $docsPorTipo[$tipo['id']] ?? null;
                    $docEstado = $doc ? EstadoDocumento::from($doc['estado']) : null;
                ?>
                <tr>
                    <td class="text-muted"><?= (int) $tipo['orden'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= $e($tipo['nombre']) ?></div>
                        <small class="text-muted">
                            <?php if ($tipo['obligatorio']): ?><span class="text-danger">Obligatorio</span><?php else: ?>Opcional<?php endif ?>
                            <?php if ($tipo['requiere_validacion_externa']): ?> | <i class="bi bi-globe me-1"></i>Validación externa<?php endif ?>
                            <?php if ($tipo['tiene_vencimiento']): ?> | <i class="bi bi-calendar-event me-1"></i><?= $tipo['dias_vigencia'] ?> días<?php endif ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($doc): ?>
                        <span class="badge <?= $docEstado->badgeClass() ?>">
                            <i class="bi <?= $docEstado->iconClass() ?> me-1"></i><?= $e($docEstado->label()) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-dash-circle me-1"></i>Sin subir
                        </span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($doc && $doc['fecha_vencimiento']): ?>
                            <?php
                            $vence = new DateTimeImmutable($doc['fecha_vencimiento']);
                            $hoy = new DateTimeImmutable('today');
                            $diasR = (int) $hoy->diff($vence)->format('%r%a');
                            $claseVenc = $diasR < 0 ? 'text-danger fw-bold' : ($diasR <= 30 ? 'text-warning' : 'text-muted');
                            ?>
                            <span class="<?= $claseVenc ?>">
                                <?= $vence->format('d/m/Y') ?>
                                <?php if ($diasR < 0): ?> (vencido)<?php elseif ($diasR <= 30): ?> (<?= $diasR ?>d)<?php endif ?>
                            </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif ?>
                    </td>
                    <td class="text-end">
                        <?php if ($doc): ?>
                        <a href="/expedientes/documento/<?= $e($doc['id']) ?>" class="btn btn-sm btn-outline-primary" title="Ver">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php endif ?>
                        <?php if ($user->tienePermiso('expedientes.subir')): ?>
                        <a href="/expedientes/<?= $e($expId) ?>/documento/<?= $e($tipo['id']) ?>"
                           class="btn btn-sm btn-outline-secondary" title="<?= $doc ? 'Reemplazar' : 'Subir' ?>">
                            <i class="bi bi-upload"></i>
                        </a>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bloque B: Lista de documentos -->
<div class="card shadow-sm mb-4 <?= $bloqueado ? 'opacity-50' : '' ?>">
    <div class="card-header">
        <?php if ($bloqueado): ?><i class="bi bi-lock me-2"></i><?php else: ?><i class="bi bi-unlock me-2"></i><?php endif ?>
        Bloque B — Documentos de Vinculación Laboral (Post-contratación)
    </div>
    <?php if ($bloqueado): ?>
    <div class="card-body text-center text-muted py-4">
        <i class="bi bi-lock fs-1 d-block mb-2"></i>
        Complete el 100% del Bloque A para desbloquear el Bloque B.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:30px">#</th>
                    <th>Documento</th>
                    <th>Estado</th>
                    <th>Vencimiento</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tiposB as $tipo):
                    $doc = $docsPorTipo[$tipo['id']] ?? null;
                    $docEstado = $doc ? EstadoDocumento::from($doc['estado']) : null;
                ?>
                <tr>
                    <td class="text-muted"><?= (int) $tipo['orden'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= $e($tipo['nombre']) ?></div>
                        <small class="text-muted">
                            <?php if ($tipo['obligatorio']): ?><span class="text-danger">Obligatorio</span><?php else: ?>Opcional<?php endif ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($doc): ?>
                        <span class="badge <?= $docEstado->badgeClass() ?>">
                            <i class="bi <?= $docEstado->iconClass() ?> me-1"></i><?= $e($docEstado->label()) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge bg-light text-dark border"><i class="bi bi-dash-circle me-1"></i>Sin subir</span>
                        <?php endif ?>
                    </td>
                    <td><span class="text-muted">—</span></td>
                    <td class="text-end">
                        <?php if ($doc): ?>
                        <a href="/expedientes/documento/<?= $e($doc['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        <?php endif ?>
                        <?php if ($user->tienePermiso('expedientes.subir')): ?>
                        <a href="/expedientes/<?= $e($expId) ?>/documento/<?= $e($tipo['id']) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-upload"></i>
                        </a>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
