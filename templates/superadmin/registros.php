<?php
/**
 * Log de registros publicos de empresa (Fase 13).
 * Variables: $user, $csrfToken, $currentPath, $registros
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$eventoTone = [
    'creado'    => 'tone-info',
    'activado'  => 'tone-success',
    'expirado'  => 'tone-warning',
    'reenviado' => 'tone-info',
    'fallo'     => 'tone-danger',
];
?>

<?php $view->section('title') ?>Registros | Superadmin<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-journal-text"></i></span>
        <div>
            <h1>Log de registros publicos</h1>
            <small>Intentos de auto-registro desde el portal publico</small>
        </div>
    </div>
    <a href="<?= $view->url('/admin') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver al panel
    </a>
</div>

<?php if ($registros === []): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-inbox"></i></div>
        <h3>Aun no hay registros de empresa</h3>
        <p>Cuando una empresa intente auto-registrarse desde el portal publico, los eventos apareceran aqui.</p>
    </div>
</div>
<?php else: ?>
<div class="ds-table-card">
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Empresa</th>
                    <th>RUC</th>
                    <th>Email admin</th>
                    <th>Evento</th>
                    <th>Detalles</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $r):
                    $detalles = '';
                    if (!empty($r['detalles_json'])) {
                        $d = is_string($r['detalles_json']) ? json_decode($r['detalles_json'], true) : $r['detalles_json'];
                        if (is_array($d)) {
                            $detalles = implode(', ', array_map(
                                fn($k, $v) => "{$k}: {$v}",
                                array_keys($d),
                                array_values($d),
                            ));
                        }
                    }
                    $tone = $eventoTone[(string) $r['evento']] ?? 'tone-muted';
                ?>
                <tr>
                    <td><span class="ds-cell-mono"><?= $e(substr((string) $r['created_at'], 0, 19)) ?></span></td>
                    <td>
                        <?php if (!empty($r['tenant_id'])): ?>
                            <a href="<?= $view->url('/admin/tenants/' . $e((string) $r['tenant_id'])) ?>"
                               class="ds-cell-name" style="text-decoration: none;">
                                <?= $e((string) $r['razon_social']) ?>
                            </a>
                        <?php else: ?>
                            <div class="ds-cell-name"><?= $e((string) $r['razon_social']) ?></div>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-cell-mono"><?= $e((string) $r['ruc']) ?></span></td>
                    <td><span class="ds-cell-mono"><?= $e((string) $r['email_admin']) ?></span></td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e((string) $r['evento']) ?></span></td>
                    <td>
                        <?php if ($detalles !== ''): ?>
                            <div class="ds-cell-sub" style="max-width: 360px;"><?= $e($detalles) ?></div>
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
