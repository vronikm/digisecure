<?php
/**
 * Log de registros públicos de empresa (Fase 13).
 * Variables: $user, $csrfToken, $currentPath, $registros
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$eventoBadge = [
    'creado'    => 'bg-primary',
    'activado'  => 'bg-success',
    'expirado'  => 'bg-warning text-dark',
    'reenviado' => 'bg-info text-dark',
    'fallo'     => 'bg-danger',
];
?>

<?php $view->section('title') ?>Registros | Superadmin<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex align-items-center mb-4">
    <a href="<?= $view->url('/admin') ?>" class="btn btn-sm btn-outline-secondary me-3"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-info"></i>Log de registros públicos</h4>
        <small class="text-muted">Intentos de auto-registro desde el portal público</small>
    </div>
</div>

<?php if ($registros === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
            Aún no hay registros de empresa.
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
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
                    <?php foreach ($registros as $r): ?>
                        <?php
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
                        ?>
                        <tr>
                            <td class="text-muted small text-nowrap"><?= $e(substr((string) $r['created_at'], 0, 19)) ?></td>
                            <td class="fw-semibold">
                                <?php if (!empty($r['tenant_id'])): ?>
                                    <a href="<?= $view->url('/admin/tenants/' . $e((string) $r['tenant_id'])) ?>" class="text-decoration-none"><?= $e((string) $r['razon_social']) ?></a>
                                <?php else: ?>
                                    <?= $e((string) $r['razon_social']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="font-monospace small"><?= $e((string) $r['ruc']) ?></td>
                            <td class="small"><?= $e((string) $r['email_admin']) ?></td>
                            <td><span class="badge <?= $eventoBadge[(string) $r['evento']] ?? 'bg-secondary' ?>"><?= $e((string) $r['evento']) ?></span></td>
                            <td class="small text-muted"><?= $detalles !== '' ? $e($detalles) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php $view->endSection() ?>
