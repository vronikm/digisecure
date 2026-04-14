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
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-check me-2"></i>Consentimientos LOPDP</h4>
        <p class="text-muted mb-0"><?= (int)$activos ?> consentimiento<?= $activos !== 1 ? 's' : '' ?> activo<?= $activos !== 1 ? 's' : '' ?></p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Titular</th>
                    <th>Propósito</th>
                    <th>Aceptado</th>
                    <th>Estado</th>
                    <th>Revocado</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($consentimientos === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>Sin consentimientos registrados.</td></tr>
                <?php endif ?>
                <?php foreach ($consentimientos as $c): ?>
                <tr>
                    <td>
                        <small class="d-block"><?= $e(ucfirst((string)$c['titular_tipo'])) ?></small>
                        <small class="text-muted"><code><?= $e((string)$c['titular_id']) ?></code></small>
                    </td>
                    <td><small><?= $e((string)$c['proposito']) ?></small></td>
                    <td><small><?= date('d/m/Y H:i', strtotime((string)$c['aceptado_at'])) ?></small></td>
                    <td>
                        <?php if (!empty($c['revocado_at'])): ?>
                            <span class="badge bg-secondary">Revocado</span>
                        <?php elseif ((int)$c['aceptado'] === 1): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Rechazado</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?= !empty($c['revocado_at']) ? date('d/m/Y', strtotime((string)$c['revocado_at'])) : '—' ?>
                        </small>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php $view->endSection() ?>
