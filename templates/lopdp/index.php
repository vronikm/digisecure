<?php
/**
 * Dashboard LOPDP - KPIs y accesos rapidos.
 *
 * Variables: $user, $csrfToken, $currentPath, $urgencia, $recientesSolicitudes,
 *            $consentActivos, $recientesConsentimientos, $aceptacionesPolitica
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
?>

<?php $view->section('title') ?>LOPDP - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-file-earmark-lock me-1"></i>LOPDP</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-file-earmark-lock"></i></span>
        <div>
            <h1>LOPDP &middot; Datos personales</h1>
            <small>Cumplimiento Ley Organica de Proteccion de Datos Personales del Ecuador</small>
        </div>
    </div>
    <a href="<?= $view->url('/lopdp/solicitudes/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nueva solicitud ARCO-P
    </a>
</div>

<!-- KPIs -->
<div class="ds-kpi-grid ds-kpi-grid-solid">
    <div class="ds-kpi-card tone-danger">
        <span class="ds-kpi-icon"><i class="bi bi-exclamation-octagon-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Solicitudes vencidas</div>
            <div class="ds-kpi-value"><?= (int) $urgencia['vencidas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
        <div>
            <div class="ds-kpi-label">Por vencer (3 dias)</div>
            <div class="ds-kpi-value"><?= (int) $urgencia['por_vencer'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card">
        <span class="ds-kpi-icon"><i class="bi bi-folder2-open"></i></span>
        <div>
            <div class="ds-kpi-label">Abiertas</div>
            <div class="ds-kpi-value"><?= (int) $urgencia['abiertas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-shield-check"></i></span>
        <div>
            <div class="ds-kpi-label">Consentimientos activos</div>
            <div class="ds-kpi-value"><?= (int) $consentActivos ?></div>
        </div>
    </div>
    <div class="ds-kpi-card">
        <span class="ds-kpi-icon"><i class="bi bi-person-check"></i></span>
        <div>
            <div class="ds-kpi-label">Aceptaciones politica</div>
            <div class="ds-kpi-value"><?= (int) ($aceptacionesPolitica['total'] ?? 0) ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-file-earmark-pdf"></i></span>
        <div>
            <div class="ds-kpi-label">Autorizacion pendiente</div>
            <div class="ds-kpi-value"><?= (int) ($aceptacionesPolitica['autorizacion_pendiente'] ?? 0) ?></div>
        </div>
    </div>
</div>

<!-- Recientes -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="ds-card">
            <div class="ds-card-header">
                <span class="ds-card-mark"><i class="bi bi-clock-history"></i></span>
                <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <div class="ds-card-title">Solicitudes recientes</div>
                        <div class="ds-card-subtitle">Ultimas solicitudes ARCO-P registradas</div>
                    </div>
                    <a href="<?= $view->url('/lopdp/solicitudes') ?>" style="font-size: .82rem; color: #2563eb; text-decoration: none; font-weight: 600;">Ver todas &rarr;</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Titular</th>
                            <th>Estado</th>
                            <th>Vence</th>
                            <th class="ds-cell-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recientesSolicitudes === []): ?>
                        <tr>
                            <td colspan="5">
                                <div class="ds-empty-state" style="padding: 1.75rem 1rem;">
                                    <div class="ds-empty-icon" style="width: 56px; height: 56px; font-size: 1.6rem;"><i class="bi bi-inbox"></i></div>
                                    <p>Sin solicitudes registradas todavia.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif ?>
                        <?php foreach ($recientesSolicitudes as $s): ?>
                        <tr>
                            <td><span class="ds-chip"><i class="bi bi-tag"></i><?= $e(ucfirst((string) $s['tipo'])) ?></span></td>
                            <td><div class="ds-cell-name"><?= $e((string) $s['titular_nombre']) ?></div></td>
                            <td><span class="ds-badge <?= $estadoTone((string) $s['estado']) ?>"><?= $e((string) $s['estado']) ?></span></td>
                            <td><span class="ds-cell-mono"><?= $e((string) $s['fecha_limite']) ?></span></td>
                            <td class="ds-cell-end">
                                <a href="<?= $view->url('/lopdp/solicitudes/' . $s['id']) ?>" class="ds-btn ds-btn-outline ds-btn-sm" title="Ver">
                                    Ver
                                </a>
                            </td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="ds-card">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-shield-check"></i></span>
                <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                    <div>
                        <div class="ds-card-title">Consentimientos recientes</div>
                        <div class="ds-card-subtitle">Aceptaciones registradas</div>
                    </div>
                    <a href="<?= $view->url('/lopdp/consentimientos') ?>" style="font-size: .82rem; color: #2563eb; text-decoration: none; font-weight: 600;">Ver todos &rarr;</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Proposito</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recientesConsentimientos === []): ?>
                        <tr>
                            <td colspan="3">
                                <div class="ds-empty-state" style="padding: 1.75rem 1rem;">
                                    <div class="ds-empty-icon" style="width: 56px; height: 56px; font-size: 1.6rem;"><i class="bi bi-shield-check"></i></div>
                                    <p>Sin consentimientos registrados.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif ?>
                        <?php foreach ($recientesConsentimientos as $c):
                            $revocado = !empty($c['revocado_at']);
                            $aceptado = (int) $c['aceptado'] === 1;
                        ?>
                        <tr>
                            <td><div style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= $e((string) $c['proposito']) ?>"><?= $e((string) $c['proposito']) ?></div></td>
                            <td>
                                <?php if ($revocado): ?>
                                    <span class="ds-badge tone-muted">Revocado</span>
                                <?php elseif ($aceptado): ?>
                                    <span class="ds-badge tone-success">Activo</span>
                                <?php else: ?>
                                    <span class="ds-badge tone-warning">Rechazado</span>
                                <?php endif ?>
                            </td>
                            <td><span class="ds-cell-mono"><?= date('d/m/Y', strtotime((string) $c['aceptado_at'])) ?></span></td>
                        </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
