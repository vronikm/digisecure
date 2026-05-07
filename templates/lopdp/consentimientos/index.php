<?php
/**
 * Listado de consentimientos LOPDP.
 *
 * Variables: $user, $csrfToken, $currentPath, $consentimientos, $activos
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Consentimientos LOPDP<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp') ?>">LOPDP</a></li>
<li class="breadcrumb-item active">Consentimientos</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-shield-check"></i></span>
        <div>
            <h1>Consentimientos LOPDP</h1>
            <small><?= (int) $activos ?> consentimiento<?= $activos !== 1 ? 's' : '' ?> activo<?= $activos !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <a href="<?= $view->url('/lopdp') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Panel LOPDP
    </a>
</div>

<?php if ($consentimientos === []): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-shield-check"></i></div>
        <h3>Sin consentimientos registrados</h3>
        <p>Cuando un titular acepte el tratamiento de sus datos, el consentimiento quedara registrado aqui con trazabilidad completa.</p>
    </div>
</div>
<?php else: ?>
<div class="ds-table-card">
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Titular</th>
                    <th>Proposito</th>
                    <th>Aceptado</th>
                    <th>Estado</th>
                    <th>Revocado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($consentimientos as $c):
                    $revocado = !empty($c['revocado_at']);
                    $aceptado = (int) $c['aceptado'] === 1;
                ?>
                <tr>
                    <td>
                        <div class="ds-cell-name"><?= $e(ucfirst((string) $c['titular_tipo'])) ?></div>
                        <div class="ds-cell-sub"><span class="ds-cell-mono"><?= $e((string) $c['titular_id']) ?></span></div>
                    </td>
                    <td><div style="max-width: 360px;"><?= $e((string) $c['proposito']) ?></div></td>
                    <td><span class="ds-cell-mono"><?= date('d/m/Y H:i', strtotime((string) $c['aceptado_at'])) ?></span></td>
                    <td>
                        <?php if ($revocado): ?>
                            <span class="ds-badge tone-muted">Revocado</span>
                        <?php elseif ($aceptado): ?>
                            <span class="ds-badge tone-success">Activo</span>
                        <?php else: ?>
                            <span class="ds-badge tone-warning">Rechazado</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($revocado): ?>
                            <span class="ds-cell-mono"><?= date('d/m/Y', strtotime((string) $c['revocado_at'])) ?></span>
                        <?php else: ?>
                            <span class="ds-cell-sub">—</span>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif ?>
<?php $view->endSection() ?>
