<?php
/**
 * Listado global de tenants (Fase 13).
 *
 * Variables: $user, $csrfToken, $currentPath, $tenants, $total, $pagina,
 *            $porPagina, $busqueda, $estadoFiltro, $planFiltro, $planes
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$totalPaginas = (int) ceil($total / $porPagina);

$estadoBadge = [
    'activo'     => 'bg-success',
    'pendiente'  => 'bg-warning text-dark',
    'suspendido' => 'bg-danger',
    'cancelado'  => 'bg-secondary',
];
?>

<?php $view->section('title') ?>Empresas | Superadmin<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-buildings me-2 text-primary"></i>Empresas</h4>
        <small class="text-muted"><?= $total ?> empresa<?= $total !== 1 ? 's' : '' ?> registrada<?= $total !== 1 ? 's' : '' ?></small>
    </div>
</div>

<!-- Filtros -->
<form method="GET" action="<?= $view->url('/admin/tenants') ?>" class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Buscar</label>
                <input type="text" class="form-control form-control-sm" name="q" placeholder="Razón social, RUC o email..."
                       value="<?= $e((string) ($busqueda ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Estado</label>
                <select class="form-select form-select-sm" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (['activo','pendiente','suspendido','cancelado'] as $est): ?>
                        <option value="<?= $est ?>" <?= ($estadoFiltro ?? '') === $est ? 'selected' : '' ?>><?= ucfirst($est) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Plan</label>
                <select class="form-select form-select-sm" name="plan">
                    <option value="">Todos</option>
                    <?php foreach ($planes as $p): ?>
                        <option value="<?= $e($p->codigo) ?>" <?= ($planFiltro ?? '') === $p->codigo ? 'selected' : '' ?>><?= $e($p->nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
            </div>
            <div class="col-md-2">
                <a href="<?= $view->url('/admin/tenants') ?>" class="btn btn-outline-secondary btn-sm w-100">Limpiar</a>
            </div>
        </div>
    </div>
</form>

<!-- Tabla -->
<?php if ($tenants === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-building-slash fs-1 d-block mb-2 opacity-50"></i>
            No se encontraron empresas con los filtros aplicados.
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Empresa</th>
                        <th>RUC</th>
                        <th>Plan</th>
                        <th class="text-center">Usuarios</th>
                        <th class="text-center">Fichas</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Alta</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $t): ?>
                        <?php
                            $venc = $t['plan_vencimiento'] ?? null;
                            $diasRest = $venc ? (int)(( strtotime($venc) - time() ) / 86400) : null;
                            $vencClass = $diasRest !== null && $diasRest <= 0 ? 'text-danger fw-semibold' : ($diasRest !== null && $diasRest <= 15 ? 'text-warning' : '');
                        ?>
                        <tr>
                            <td>
                                <a href="<?= $view->url('/admin/tenants/' . $e((string) $t['id'])) ?>" class="text-decoration-none fw-semibold">
                                    <?= $e((string) $t['razon_social']) ?>
                                </a>
                                <br><small class="text-muted"><?= $e((string) $t['email_contacto']) ?></small>
                            </td>
                            <td class="font-monospace small"><?= $e((string) $t['ruc']) ?></td>
                            <td><span class="badge bg-primary-subtle text-primary"><?= $e((string) $t['plan_codigo']) ?></span></td>
                            <td class="text-center"><?= (int) ($t['usuarios_activos'] ?? 0) ?> / <?= (int) ($t['max_usuarios'] ?? 0) ?></td>
                            <td class="text-center"><?= (int) ($t['total_fichas'] ?? 0) ?> / <?= (int) ($t['max_guardias'] ?? 0) ?></td>
                            <td class="small <?= $vencClass ?>"><?= $venc ? $e(substr($venc, 0, 10)) : '—' ?></td>
                            <td><span class="badge <?= $estadoBadge[(string) $t['estado']] ?? 'bg-secondary' ?>"><?= $e((string) $t['estado']) ?></span></td>
                            <td class="small text-muted"><?= $e(substr((string) ($t['created_at'] ?? ''), 0, 10)) ?></td>
                            <td>
                                <a href="<?= $view->url('/admin/tenants/' . $e((string) $t['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <?php if ($totalPaginas > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center">
                <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                    <?php
                        $params = array_filter(['q' => $busqueda, 'estado' => $estadoFiltro, 'plan' => $planFiltro, 'pagina' => $p > 1 ? $p : null]);
                        $qs = $params !== [] ? '?' . http_build_query($params) : '';
                    ?>
                    <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $view->url('/admin/tenants') . $qs ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php $view->endSection() ?>
