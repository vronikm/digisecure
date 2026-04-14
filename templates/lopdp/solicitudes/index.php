<?php
/**
 * Listado de solicitudes ARCO-P.
 *
 * Variables: $user, $csrfToken, $currentPath, $solicitudes, $total, $page, $totalPages, $filters
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

$today = date('Y-m-d');
?>

<?php $view->section('title') ?>Solicitudes ARCO-P — LOPDP<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp') ?>">LOPDP</a></li>
<li class="breadcrumb-item active">Solicitudes ARCO-P</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-list-check me-2"></i>Solicitudes ARCO-P</h4>
        <p class="text-muted mb-0"><?= (int)$total ?> solicitud<?= $total !== 1 ? 'es' : '' ?></p>
    </div>
    <a href="<?= $view->url('/lopdp/solicitudes/crear') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva solicitud
    </a>
</div>

<!-- Filtros -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="get" action="<?= $view->url('/lopdp/solicitudes') ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['RECIBIDA','EN_PROCESO','RESPONDIDA','DENEGADA'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($filters['estado'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['acceso','rectificacion','cancelacion','oposicion','portabilidad'] as $tp): ?>
                    <option value="<?= $tp ?>" <?= ($filters['tipo'] ?? '') === $tp ? 'selected' : '' ?>><?= ucfirst($tp) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Búsqueda</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       value="<?= $e($filters['busqueda'] ?? '') ?>" placeholder="Nombre, cédula o email">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tipo</th>
                    <th>Titular</th>
                    <th>Recepción</th>
                    <th>Vence</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($solicitudes === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No hay solicitudes.</td></tr>
                <?php endif ?>
                <?php foreach ($solicitudes as $s): ?>
                <?php
                $vencida = $s['estado'] !== 'RESPONDIDA' && $s['estado'] !== 'DENEGADA' && $s['fecha_limite'] < $today;
                ?>
                <tr<?= $vencida ? ' class="table-danger"' : '' ?>>
                    <td><span class="badge bg-light text-dark border"><?= $e(ucfirst((string)$s['tipo'])) ?></span></td>
                    <td>
                        <small class="d-block fw-semibold"><?= $e((string)$s['titular_nombre']) ?></small>
                        <small class="text-muted"><?= $e((string)($s['titular_cedula'] ?? '—')) ?> · <?= $e((string)$s['titular_tipo']) ?></small>
                    </td>
                    <td><small><?= $e((string)$s['fecha_recepcion']) ?></small></td>
                    <td>
                        <small class="<?= $vencida ? 'text-danger fw-bold' : '' ?>">
                            <?= $e((string)$s['fecha_limite']) ?>
                        </small>
                    </td>
                    <td><span class="badge <?= $estadoBadge((string)$s['estado']) ?>"><?= $e((string)$s['estado']) ?></span></td>
                    <td>
                        <a href="<?= $view->url('/lopdp/solicitudes/' . $s['id']) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
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
                <?php $qs = http_build_query(array_filter([
                    'estado' => $filters['estado'] ?? '',
                    'tipo' => $filters['tipo'] ?? '',
                    'q' => $filters['busqueda'] ?? '',
                ])); $qsPrefix = $qs ? "&{$qs}" : ''; ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $view->url('/lopdp/solicitudes') ?>?page=<?= $page - 1 ?><?= $qsPrefix ?>">«</a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $view->url('/lopdp/solicitudes') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
                </li>
                <?php endfor ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $view->url('/lopdp/solicitudes') ?>?page=<?= $page + 1 ?><?= $qsPrefix ?>">»</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
