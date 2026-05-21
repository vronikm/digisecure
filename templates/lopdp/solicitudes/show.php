<?php
/**
 * Detalle y respuesta de solicitud ARCO-P.
 *
 * Variables: $user, $csrfToken, $currentPath, $solicitud, $error?
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoTone = static fn(string $estado) => match ($estado) {
    'RECIBIDA'   => 'tone-info',
    'EN_PROCESO' => 'tone-warning',
    'RESPONDIDA' => 'tone-success',
    'DENEGADA'   => 'tone-muted',
    default      => 'tone-muted',
};

$cerrada = in_array($solicitud['estado'], ['RESPONDIDA', 'DENEGADA'], true);
$today = date('Y-m-d');
$vencida = !$cerrada && $solicitud['fecha_limite'] < $today;
?>

<?php $view->section('title') ?>Solicitud ARCO-P — LOPDP<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp') ?>">LOPDP</a></li>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp/solicitudes') ?>">Solicitudes</a></li>
<li class="breadcrumb-item active">Detalle</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-file-earmark-text"></i></span>
        <div>
            <h1>Solicitud ARCO-P</h1>
            <small style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap;">
                <span class="ds-chip"><i class="bi bi-tag"></i><?= $e(ucfirst((string) $solicitud['tipo'])) ?></span>
                <span class="ds-badge <?= $estadoTone((string) $solicitud['estado']) ?>"><?= $e((string) $solicitud['estado']) ?></span>
                <span class="ds-cell-mono" style="font-size: .72rem;">ID: <?= $e((string) $solicitud['id']) ?></span>
            </small>
        </div>
    </div>
    <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al listado
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1rem;">
    <div class="ds-card-body" style="display: flex; gap: .75rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.25rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #dc2626; margin-bottom: .15rem;">Error en la operacion</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;"><?= $e($error) ?></div>
        </div>
    </div>
</div>
<?php endif ?>

<?php if ($vencida): ?>
<div class="ds-card" style="border-left: 4px solid #dc2626; margin-bottom: 1.25rem;">
    <div class="ds-card-body" style="display: flex; gap: .85rem; align-items: flex-start;">
        <span style="color: #dc2626; font-size: 1.4rem; line-height: 1;"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div style="font-weight: 700; color: #b91c1c; margin-bottom: .15rem;">Fuera de plazo legal</div>
            <div style="color: var(--ds-text-soft); font-size: .85rem;">Esta solicitud vencio el <span class="ds-cell-mono" style="font-weight: 700; color: #b91c1c;"><?= $e((string) $solicitud['fecha_limite']) ?></span> y aun no fue respondida. Atender lo antes posible.</div>
        </div>
    </div>
</div>
<?php endif ?>

<?php
$ds_dt = 'font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--ds-text-soft); margin-bottom: .15rem;';
$ds_dd = 'font-size: .9rem; color: var(--ds-text); font-weight: 600;';
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="ds-card">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-person-vcard"></i></span>
                <div>
                    <div class="ds-card-title">Datos del titular</div>
                    <div class="ds-card-subtitle">Persona que ejerce el derecho</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Tipo</div>
                        <div style="<?= $ds_dd ?>"><?= $e(ucfirst((string) $solicitud['titular_tipo'])) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div style="<?= $ds_dt ?>">Nombre</div>
                        <div style="<?= $ds_dd ?>"><?= $e((string) $solicitud['titular_nombre']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Cedula</div>
                        <div class="ds-cell-mono"><?= $e((string) ($solicitud['titular_cedula'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Email</div>
                        <div class="ds-cell-mono"><?= $e((string) ($solicitud['titular_email'] ?? '—')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div style="<?= $ds_dt ?>">Telefono</div>
                        <div class="ds-cell-mono"><?= $e((string) ($solicitud['titular_telefono'] ?? '—')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-card-text"></i></span>
                <div>
                    <div class="ds-card-title">Descripcion</div>
                    <div class="ds-card-subtitle">Detalle del derecho ejercido</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="white-space: pre-wrap; color: var(--ds-text); font-size: .92rem; line-height: 1.55;"><?= $e((string) $solicitud['descripcion']) ?></div>
            </div>
        </div>

        <?php if (!empty($solicitud['respuesta_texto'])): ?>
        <div class="ds-card" style="border-left: 4px solid #059669;">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-check2-circle"></i></span>
                <div>
                    <div class="ds-card-title">Respuesta entregada</div>
                    <div class="ds-card-subtitle">Respondida el <span class="ds-cell-mono"><?= $e((string) $solicitud['respondida_at']) ?></span></div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="white-space: pre-wrap; color: var(--ds-text); font-size: .92rem; line-height: 1.55;"><?= $e((string) $solicitud['respuesta_texto']) ?></div>
            </div>
        </div>
        <?php endif ?>

        <?php if (!empty($solicitud['denegada_motivo'])): ?>
        <div class="ds-card" style="border-left: 4px solid #64748b;">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-x-octagon"></i></span>
                <div>
                    <div class="ds-card-title">Motivo de denegacion</div>
                    <div class="ds-card-subtitle">Denegada el <span class="ds-cell-mono"><?= $e((string) $solicitud['respondida_at']) ?></span></div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="white-space: pre-wrap; color: var(--ds-text); font-size: .92rem; line-height: 1.55;"><?= $e((string) $solicitud['denegada_motivo']) ?></div>
            </div>
        </div>
        <?php endif ?>
    </div>

    <div class="col-lg-5">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-clock"></i></span>
                <div>
                    <div class="ds-card-title">Plazos</div>
                    <div class="ds-card-subtitle">15 dias calendario por LOPDP Art. 32</div>
                </div>
            </div>
            <div class="ds-card-body">
                <div style="<?= $ds_dt ?>">Recepcion</div>
                <div class="ds-cell-mono" style="margin-bottom: .65rem;"><?= $e((string) $solicitud['fecha_recepcion']) ?></div>

                <div style="<?= $ds_dt ?>">Vence</div>
                <div style="margin-bottom: .65rem;">
                    <?php if ($vencida): ?>
                        <span class="ds-badge tone-danger no-dot"><i class="bi bi-exclamation-triangle"></i><?= $e((string) $solicitud['fecha_limite']) ?> &middot; vencida</span>
                    <?php else: ?>
                        <span class="ds-cell-mono" style="font-weight: 700;"><?= $e((string) $solicitud['fecha_limite']) ?></span>
                    <?php endif ?>
                </div>

                <div style="<?= $ds_dt ?>">Canal de recepcion</div>
                <div><span class="ds-chip"><?= $e(ucfirst(str_replace('_', ' ', (string) $solicitud['canal_recepcion']))) ?></span></div>
            </div>
        </div>

        <?php if (!$cerrada): ?>
        <div class="ds-card">
            <div class="ds-card-header" style="background: linear-gradient(135deg, #2f80ed, #1e63c5); color: #fff;">
                <span class="ds-card-mark" style="background: rgba(255,255,255,.2); color: #fff;"><i class="bi bi-pencil-square"></i></span>
                <div>
                    <div class="ds-card-title" style="color: #fff;">Resolver solicitud</div>
                    <div class="ds-card-subtitle" style="color: rgba(255,255,255,.7);">Responder o denegar el ejercicio de derechos</div>
                </div>
            </div>
            <div class="ds-card-body">
                <ul class="nav nav-tabs nav-fill" style="margin-bottom: 1rem; border: 0; gap: .35rem;">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tab-responder"
                           style="border-radius: 8px; font-weight: 600; font-size: .85rem; color: var(--ds-text-soft);">Responder</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-denegar"
                           style="border-radius: 8px; font-weight: 600; font-size: .85rem; color: var(--ds-text-soft);">Denegar</a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-responder">
                        <form method="post" action="<?= $view->url('/lopdp/solicitudes/' . $solicitud['id'] . '/responder') ?>" class="ds-form-main">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="estado" value="RESPONDIDA">
                            <div class="ds-form-field" style="margin-bottom: 1rem;">
                                <label class="ds-form-label">Texto de respuesta <span class="ds-required">*</span></label>
                                <textarea name="respuesta_texto" class="form-control" rows="6" required minlength="10"
                                          placeholder="Describe la respuesta entregada al titular..."></textarea>
                            </div>
                            <button type="submit" class="ds-btn ds-btn-success" style="width: 100%;">
                                <i class="bi bi-check2-circle"></i>Marcar como respondida
                            </button>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="tab-denegar">
                        <form method="post" action="<?= $view->url('/lopdp/solicitudes/' . $solicitud['id'] . '/responder') ?>" class="ds-form-main">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="estado" value="DENEGADA">
                            <div class="ds-form-field" style="margin-bottom: 1rem;">
                                <label class="ds-form-label">Motivo de denegacion <span class="ds-required">*</span></label>
                                <textarea name="denegada_motivo" class="form-control" rows="6" required minlength="10"
                                          placeholder="Indica el fundamento legal de la denegacion..."></textarea>
                            </div>
                            <button type="submit" class="ds-btn ds-btn-danger" style="width: 100%;">
                                <i class="bi bi-x-octagon"></i>Denegar solicitud
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif ?>
    </div>
</div>
<?php $view->endSection() ?>
