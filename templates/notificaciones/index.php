<?php
/**
 * Listado de notificaciones del tenant.
 *
 * Variables: $user, $csrfToken, $currentPath, $notificaciones,
 *            $total, $page, $totalPages, $filters, $kpis
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$estadoBadge = static fn(string $estado) => match ($estado) {
    'PENDIENTE' => 'bg-secondary',
    'ENVIADA' => 'bg-success',
    'FALLIDA' => 'bg-danger',
    'CANCELADA' => 'bg-warning text-dark',
    default => 'bg-dark',
};

$canalIcon = static fn(string $canal) => match ($canal) {
    'EMAIL' => 'bi-envelope',
    'WHATSAPP' => 'bi-whatsapp',
    'SMS' => 'bi-chat-dots',
    'PUSH' => 'bi-bell',
    default => 'bi-send',
};
?>

<?php $view->section('title') ?>Notificaciones — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-bell me-1"></i>Notificaciones</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-bell me-2"></i>Notificaciones</h4>
    <span class="text-muted small"><?= $total ?> registros</span>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-secondary border-4">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-bold">Pendientes</div>
                <div class="fs-4 fw-bold text-secondary"><?= (int)$kpis['pendientes'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-success border-4">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-bold">Enviadas</div>
                <div class="fs-4 fw-bold text-success"><?= (int)$kpis['enviadas'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-danger border-4">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-bold">Fallidas</div>
                <div class="fs-4 fw-bold text-danger"><?= (int)$kpis['fallidas'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-sm h-100 border-start border-primary border-4">
            <div class="card-body py-3">
                <div class="text-muted small text-uppercase fw-bold">Total</div>
                <div class="fs-4 fw-bold text-primary"><?= (int)$kpis['total'] ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<form method="get" action="<?= $view->url('/notificaciones') ?>" class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['PENDIENTE', 'ENVIADA', 'FALLIDA', 'CANCELADA'] as $est): ?>
                    <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>><?= ucfirst(strtolower($est)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Canal</label>
                <select name="canal" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['EMAIL', 'WHATSAPP', 'SMS'] as $canal): ?>
                    <option value="<?= $canal ?>" <?= ($filters['canal'] ?? '') === $canal ? 'selected' : '' ?>><?= $canal ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Evento</label>
                <input type="text" name="evento" class="form-control form-control-sm"
                       value="<?= $e($filters['evento'] ?? '') ?>" placeholder="Ej: doc.vencido">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                <a href="<?= $view->url('/notificaciones') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </div>
</form>

<!-- Tabla -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Evento</th>
                    <th>Canal</th>
                    <th>Destinatario</th>
                    <th>Asunto</th>
                    <th>Estado</th>
                    <th class="text-center">Int.</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($notificaciones)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No hay notificaciones registradas.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($notificaciones as $n): ?>
                <tr>
                    <td class="small text-nowrap"><?= $e(substr((string)$n['created_at'], 0, 16)) ?></td>
                    <td><code class="small"><?= $e((string)$n['evento']) ?></code></td>
                    <td>
                        <i class="bi <?= $canalIcon((string)$n['canal']) ?> me-1"></i>
                        <span class="small"><?= $e((string)$n['canal']) ?></span>
                    </td>
                    <td class="small"><?= $e((string)($n['destinatario_email'] ?? $n['destinatario_telefono'] ?? '—')) ?></td>
                    <td class="small text-truncate" style="max-width:200px"><?= $e((string)$n['asunto']) ?></td>
                    <td>
                        <span class="badge <?= $estadoBadge((string)$n['estado']) ?>"><?= $e((string)$n['estado']) ?></span>
                    </td>
                    <td class="text-center small"><?= (int)$n['intentos'] ?>/<?= (int)$n['max_intentos'] ?></td>
                    <td>
                        <a href="<?= $view->url('/notificaciones/' . $e((string)$n['id'])) ?>"
                           class="btn btn-outline-primary btn-sm py-0 px-2" title="Ver detalle">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach ?>
            <?php endif ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Página <?= $page ?> de <?= $totalPages ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $view->url('/notificaciones?' . http_build_query(array_merge($filters, ['page' => $page - 1]))) ?>">&laquo;</a>
                </li>
                <?php endif ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $view->url('/notificaciones?' . http_build_query(array_merge($filters, ['page' => $p]))) ?>"><?= $p ?></a>
                </li>
                <?php endfor ?>
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="<?= $view->url('/notificaciones?' . http_build_query(array_merge($filters, ['page' => $page + 1]))) ?>">&raquo;</a>
                </li>
                <?php endif ?>
            </ul>
        </nav>
    </div>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
