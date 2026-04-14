<?php
/**
 * Visor de auditoría — listado filtrable.
 *
 * Variables: $user, $csrfToken, $currentPath, $eventos, $total, $page, $totalPages, $filters, $eventosDisponibles
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$nivelBadge = static fn(string $nivel) => match ($nivel) {
    'critical' => 'bg-danger',
    'warning' => 'bg-warning text-dark',
    'info' => 'bg-info text-dark',
    default => 'bg-secondary',
};
?>

<?php $view->section('title') ?>Auditoría — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-shield-check me-1"></i>Auditoría</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-shield-check me-2"></i>Registro de Auditoría</h4>
        <p class="text-muted mb-0"><?= $total ?> evento<?= $total !== 1 ? 's' : '' ?> registrado<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <?php if ($user->tienePermiso('auditoria.exportar')): ?>
    <a href="<?= $view->url('/auditoria/exportar?' . http_build_query($filters)) ?>" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Exportar CSV
    </a>
    <?php endif ?>
</div>

<!-- Filtros -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="get" action="<?= $view->url('/auditoria') ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Evento</label>
                <input type="text" name="evento" class="form-control form-control-sm" list="eventos-list"
                       value="<?= $e($filters['evento'] ?? '') ?>" placeholder="ej: auth.login_ok">
                <datalist id="eventos-list">
                    <?php foreach ($eventosDisponibles as $ev): ?>
                    <option value="<?= $e($ev) ?>"></option>
                    <?php endforeach ?>
                </datalist>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Nivel</label>
                <select name="nivel" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['info','warning','critical'] as $niv): ?>
                    <option value="<?= $niv ?>" <?= ($filters['nivel'] ?? '') === $niv ? 'selected' : '' ?>>
                        <?= $e(ucfirst($niv)) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm"
                       value="<?= $e($filters['desde'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm"
                       value="<?= $e($filters['hasta'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
            </div>
            <?php if ($filters): ?>
            <div class="col-md-1">
                <a href="<?= $view->url('/auditoria') ?>" class="btn btn-sm btn-outline-secondary w-100" title="Limpiar">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
            <?php endif ?>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Nivel</th>
                    <th>Evento</th>
                    <th>Usuario</th>
                    <th>Entidad</th>
                    <th>IP</th>
                    <th>Detalles</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($eventos === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No hay eventos que coincidan.
                    </td>
                </tr>
                <?php endif ?>
                <?php foreach ($eventos as $ev): ?>
                <tr>
                    <td class="text-nowrap">
                        <small><?= date('d/m/Y H:i:s', strtotime($ev['created_at'])) ?></small>
                    </td>
                    <td>
                        <span class="badge <?= $nivelBadge($ev['nivel']) ?>"><?= $e(ucfirst($ev['nivel'])) ?></span>
                    </td>
                    <td><code class="small"><?= $e($ev['evento']) ?></code></td>
                    <td>
                        <?php if ($ev['usuario_email']): ?>
                        <small><?= $e($ev['usuario_apellidos'] ?? '') ?> <?= $e($ev['usuario_nombres'] ?? '') ?></small><br>
                        <small class="text-muted"><?= $e($ev['usuario_email']) ?></small>
                        <?php else: ?>
                        <small class="text-muted">—</small>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($ev['entidad_tipo']): ?>
                        <small><span class="badge bg-light text-dark border"><?= $e($ev['entidad_tipo']) ?></span></small><br>
                        <small class="text-muted"><code><?= $e($ev['entidad_id'] ?? '') ?></code></small>
                        <?php else: ?>
                        <small class="text-muted">—</small>
                        <?php endif ?>
                    </td>
                    <td><small class="text-muted"><?= $e($ev['ip_text'] ?? '—') ?></small></td>
                    <td>
                        <?php if ($ev['detalles_json']): ?>
                        <details>
                            <summary class="small text-primary" style="cursor:pointer">Ver</summary>
                            <pre class="small mb-0 mt-1" style="max-width:400px;white-space:pre-wrap"><?= $e(json_encode(json_decode((string)$ev['detalles_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                        </details>
                        <?php else: ?>
                        <small class="text-muted">—</small>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Página <?= $page ?> de <?= $totalPages ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $qs = http_build_query(array_filter([
                    'evento' => $filters['evento'] ?? '',
                    'nivel' => $filters['nivel'] ?? '',
                    'desde' => $filters['desde'] ?? '',
                    'hasta' => $filters['hasta'] ?? '',
                    'usuario_id' => $filters['usuario_id'] ?? '',
                    'entidad_tipo' => $filters['entidad_tipo'] ?? '',
                ]));
                $qsPrefix = $qs ? "&{$qs}" : '';
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $view->url('/auditoria') ?>?page=<?= $page - 1 ?><?= $qsPrefix ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $view->url('/auditoria') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
                </li>
                <?php endfor ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $view->url('/auditoria') ?>?page=<?= $page + 1 ?><?= $qsPrefix ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
