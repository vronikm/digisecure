<?php
/**
 * Dashboard LOPDP — KPIs y accesos rápidos.
 *
 * Variables: $user, $csrfToken, $currentPath, $urgencia, $recientesSolicitudes,
 *            $consentActivos, $recientesConsentimientos
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>LOPDP — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-file-earmark-lock me-1"></i>LOPDP</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-file-earmark-lock me-2"></i>LOPDP — Datos Personales</h4>
        <p class="text-muted mb-0">Cumplimiento Ley Orgánica de Protección de Datos Personales del Ecuador</p>
    </div>
    <a href="<?= $view->url('/lopdp/solicitudes/crear') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva solicitud ARCO-P
    </a>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase">Solicitudes vencidas</div>
                        <div class="display-6 fw-bold text-danger"><?= (int)$urgencia['vencidas'] ?></div>
                    </div>
                    <i class="bi bi-exclamation-octagon-fill text-danger fs-1 opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase">Por vencer (≤3d)</div>
                        <div class="display-6 fw-bold text-warning"><?= (int)$urgencia['por_vencer'] ?></div>
                    </div>
                    <i class="bi bi-hourglass-split text-warning fs-1 opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase">Abiertas</div>
                        <div class="display-6 fw-bold text-primary"><?= (int)$urgencia['abiertas'] ?></div>
                    </div>
                    <i class="bi bi-folder2-open text-primary fs-1 opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small text-uppercase">Consentimientos activos</div>
                        <div class="display-6 fw-bold text-success"><?= (int)$consentActivos ?></div>
                    </div>
                    <i class="bi bi-check2-circle text-success fs-1 opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recientes -->
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-clock-history me-1"></i>Solicitudes recientes</h6>
                <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="small">Ver todas →</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tipo</th>
                            <th>Titular</th>
                            <th>Estado</th>
                            <th>Vence</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recientesSolicitudes === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Sin solicitudes registradas.</td></tr>
                        <?php endif ?>
                        <?php foreach ($recientesSolicitudes as $s): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border"><?= $e(ucfirst((string)$s['tipo'])) ?></span></td>
                            <td><small><?= $e((string)$s['titular_nombre']) ?></small></td>
                            <td><small><?= $e((string)$s['estado']) ?></small></td>
                            <td><small><?= $e((string)$s['fecha_limite']) ?></small></td>
                            <td><a href="<?= $view->url('/lopdp/solicitudes/' . $s['id']) ?>" class="btn btn-sm btn-link p-0">Ver</a></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-shield-check me-1"></i>Consentimientos recientes</h6>
                <a href="<?= $view->url('/lopdp/consentimientos') ?>" class="small">Ver todos →</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Propósito</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recientesConsentimientos === []): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">Sin consentimientos.</td></tr>
                        <?php endif ?>
                        <?php foreach ($recientesConsentimientos as $c): ?>
                        <tr>
                            <td><small><?= $e((string)$c['proposito']) ?></small></td>
                            <td>
                                <?php if (!empty($c['revocado_at'])): ?>
                                    <span class="badge bg-secondary">Revocado</span>
                                <?php elseif ((int)$c['aceptado'] === 1): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Rechazado</span>
                                <?php endif ?>
                            </td>
                            <td><small class="text-muted"><?= date('d/m/Y', strtotime((string)$c['aceptado_at'])) ?></small></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
