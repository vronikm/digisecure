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

$estadoTone = static fn(string $estado) => match ($estado) {
    'PENDIENTE' => 'tone-muted',
    'ENVIADA'   => 'tone-success',
    'FALLIDA'   => 'tone-danger',
    'CANCELADA' => 'tone-warning',
    default     => 'tone-muted',
};

$canalIcon = static fn(string $canal) => match ($canal) {
    'EMAIL'    => 'bi-envelope',
    'WHATSAPP' => 'bi-whatsapp',
    'SMS'      => 'bi-chat-dots',
    'PUSH'     => 'bi-bell',
    default    => 'bi-send',
};

$hasFilters = !empty($filters['estado'] ?? null)
    || !empty($filters['canal'] ?? null)
    || !empty($filters['evento'] ?? null);
?>

<?php $view->section('title') ?>Notificaciones â€” DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-bell me-1"></i>Notificaciones</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-bell-fill"></i></span>
        <div>
            <h1>Notificaciones</h1>
            <small><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?> en total</small>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="ds-kpi-grid ds-kpi-grid-solid">
    <div class="ds-kpi-card tone-muted">
        <span class="ds-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
        <div>
            <div class="ds-kpi-label">Pendientes</div>
            <div class="ds-kpi-value"><?= (int) $kpis['pendientes'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-check2-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Enviadas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['enviadas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-danger">
        <span class="ds-kpi-icon"><i class="bi bi-x-octagon-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Fallidas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['fallidas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-info">
        <span class="ds-kpi-icon"><i class="bi bi-collection"></i></span>
        <div>
            <div class="ds-kpi-label">Total</div>
            <div class="ds-kpi-value"><?= (int) $kpis['total'] ?></div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="ds-filter-bar">
    <form method="get" action="<?= $view->url('/notificaciones') ?>" class="ds-filter-row ds-form-filter">
<div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['PENDIENTE', 'ENVIADA', 'FALLIDA', 'CANCELADA'] as $est): ?>
                    <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>><?= ucfirst(strtolower($est)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Canal</label>
                <select name="canal" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['EMAIL', 'WHATSAPP', 'SMS'] as $canal): ?>
                    <option value="<?= $canal ?>" <?= ($filters['canal'] ?? '') === $canal ? 'selected' : '' ?>><?= $canal ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Evento</label>
                <input type="text" name="evento" class="form-control form-control-sm"
                       value="<?= $e($filters['evento'] ?? '') ?>" placeholder="Ej: doc.vencido">
            </div>
        </div>
        <div class="ds-filter-actions">
            <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                <i class="bi bi-search"></i>Buscar
            </button>
            <?php if ($hasFilters): ?>
            <a href="<?= $view->url('/notificaciones') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                <i class="bi bi-x-lg"></i>Limpiar
            </a>
            <?php endif ?>
        </div>
    </form>
</div>

<?php if (empty($notificaciones)): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-bell-slash"></i></div>
        <h3>No hay notificaciones</h3>
        <p>
            <?php if ($hasFilters): ?>
                No se encontraron notificaciones con los filtros aplicados.
            <?php else: ?>
                Las notificaciones se generan automaticamente al ocurrir eventos del sistema (vencimientos, registros, etc.).
            <?php endif ?>
        </p>
    </div>
</div>
<?php else: ?>
<div class="ds-table-card">
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Evento</th>
                    <th>Canal</th>
                    <th>Destinatario</th>
                    <th>Asunto</th>
                    <th>Estado</th>
                    <th class="ds-cell-end">Intentos</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notificaciones as $n): ?>
                <tr>
                    <td><span class="ds-cell-mono"><?= $e(substr((string) $n['created_at'], 0, 16)) ?></span></td>
                    <td><span class="ds-cell-mono"><?= $e((string) $n['evento']) ?></span></td>
                    <td><span class="ds-chip"><i class="bi <?= $canalIcon((string) $n['canal']) ?>"></i><?= $e((string) $n['canal']) ?></span></td>
                    <td class="ds-cell-mono"><?= $e((string) ($n['destinatario_email'] ?? $n['destinatario_telefono'] ?? 'â€”')) ?></td>
                    <td>
                        <div style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= $e((string) $n['asunto']) ?>">
                            <?= $e((string) $n['asunto']) ?>
                        </div>
                    </td>
                    <td><span class="ds-badge <?= $estadoTone((string) $n['estado']) ?>"><?= $e((string) $n['estado']) ?></span></td>
                    <td class="ds-cell-end">
                        <span class="ds-cell-sub"><?= (int) $n['intentos'] ?>/<?= (int) $n['max_intentos'] ?></span>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/notificaciones/' . $e((string) $n['id'])) ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="Ver detalle">
                                Ver
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="ds-table-foot">
        <span>Pagina <strong><?= $page ?></strong> de <?= $totalPages ?> &middot; <?= $total ?> registro<?= $total !== 1 ? 's' : '' ?></span>
        <ul class="ds-pagination">
            <li>
                <a class="ds-page <?= $page <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/notificaciones?' . http_build_query(array_merge($filters, ['page' => max(1, $page - 1)]))) ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li>
                <a class="ds-page <?= $p === $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/notificaciones?' . http_build_query(array_merge($filters, ['page' => $p]))) ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/notificaciones?' . http_build_query(array_merge($filters, ['page' => min($totalPages, $page + 1)]))) ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
</div>
<?php endif ?>
<?php $view->endSection() ?>

