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

$estadoBadge = static fn(string $estado) => match ($estado) {
    'RECIBIDA' => 'bg-info text-dark',
    'EN_PROCESO' => 'bg-warning text-dark',
    'RESPONDIDA' => 'bg-success',
    'DENEGADA' => 'bg-secondary',
    default => 'bg-light text-dark',
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
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <i class="bi bi-file-earmark-text me-2"></i>Solicitud
            <span class="badge bg-light text-dark border"><?= $e(ucfirst((string)$solicitud['tipo'])) ?></span>
            <span class="badge <?= $estadoBadge((string)$solicitud['estado']) ?>"><?= $e((string)$solicitud['estado']) ?></span>
        </h4>
        <p class="text-muted mb-0">ID: <code><?= $e((string)$solicitud['id']) ?></code></p>
    </div>
    <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (!empty($error ?? null)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= $e($error) ?></div>
<?php endif ?>

<?php if ($vencida): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-octagon me-1"></i>
    <strong>Fuera de plazo legal.</strong> Esta solicitud venció el <?= $e((string)$solicitud['fecha_limite']) ?> y aún no fue respondida.
</div>
<?php endif ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-3">
            <div class="card-header"><strong>Datos del titular</strong></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Tipo</dt>
                    <dd class="col-sm-8"><?= $e(ucfirst((string)$solicitud['titular_tipo'])) ?></dd>
                    <dt class="col-sm-4">Nombre</dt>
                    <dd class="col-sm-8"><?= $e((string)$solicitud['titular_nombre']) ?></dd>
                    <dt class="col-sm-4">Cédula</dt>
                    <dd class="col-sm-8"><?= $e((string)($solicitud['titular_cedula'] ?? '—')) ?></dd>
                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8"><?= $e((string)($solicitud['titular_email'] ?? '—')) ?></dd>
                    <dt class="col-sm-4">Teléfono</dt>
                    <dd class="col-sm-8"><?= $e((string)($solicitud['titular_telefono'] ?? '—')) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header"><strong>Descripción</strong></div>
            <div class="card-body">
                <p class="mb-0" style="white-space:pre-wrap"><?= $e((string)$solicitud['descripcion']) ?></p>
            </div>
        </div>

        <?php if (!empty($solicitud['respuesta_texto'])): ?>
        <div class="card shadow-sm mb-3 border-success">
            <div class="card-header bg-success-subtle"><strong>Respuesta entregada</strong></div>
            <div class="card-body">
                <p class="mb-2" style="white-space:pre-wrap"><?= $e((string)$solicitud['respuesta_texto']) ?></p>
                <small class="text-muted">Respondida el <?= $e((string)$solicitud['respondida_at']) ?></small>
            </div>
        </div>
        <?php endif ?>

        <?php if (!empty($solicitud['denegada_motivo'])): ?>
        <div class="card shadow-sm mb-3 border-secondary">
            <div class="card-header bg-secondary-subtle"><strong>Motivo de denegación</strong></div>
            <div class="card-body">
                <p class="mb-2" style="white-space:pre-wrap"><?= $e((string)$solicitud['denegada_motivo']) ?></p>
                <small class="text-muted">Denegada el <?= $e((string)$solicitud['respondida_at']) ?></small>
            </div>
        </div>
        <?php endif ?>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
            <div class="card-header"><strong>Plazos</strong></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-5">Recepción</dt>
                    <dd class="col-sm-7"><?= $e((string)$solicitud['fecha_recepcion']) ?></dd>
                    <dt class="col-sm-5">Vence</dt>
                    <dd class="col-sm-7 <?= $vencida ? 'text-danger fw-bold' : '' ?>"><?= $e((string)$solicitud['fecha_limite']) ?></dd>
                    <dt class="col-sm-5">Canal</dt>
                    <dd class="col-sm-7"><?= $e(ucfirst(str_replace('_', ' ', (string)$solicitud['canal_recepcion']))) ?></dd>
                </dl>
            </div>
        </div>

        <?php if (!$cerrada): ?>
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white"><strong>Resolver solicitud</strong></div>
            <div class="card-body">
                <ul class="nav nav-tabs nav-fill mb-3" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-responder">Responder</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-denegar">Denegar</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-responder">
                        <form method="post" action="<?= $view->url('/lopdp/solicitudes/' . $solicitud['id'] . '/responder') ?>">
                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="estado" value="RESPONDIDA">
                            <div class="mb-3">
                                <label class="form-label small">Texto de respuesta *</label>
                                <textarea name="respuesta_texto" class="form-control" rows="6" required minlength="10"
                                          placeholder="Describa la respuesta entregada al titular…"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-check2-circle me-1"></i>Marcar como respondida
                            </button>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="tab-denegar">
                        <form method="post" action="<?= $view->url('/lopdp/solicitudes/' . $solicitud['id'] . '/responder') ?>">
                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="estado" value="DENEGADA">
                            <div class="mb-3">
                                <label class="form-label small">Motivo de denegación *</label>
                                <textarea name="denegada_motivo" class="form-control" rows="6" required minlength="10"
                                          placeholder="Indique el fundamento legal de la denegación…"></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-x-octagon me-1"></i>Denegar solicitud
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
