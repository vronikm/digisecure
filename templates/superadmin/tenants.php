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

$estadoTone = [
    'activo'     => 'tone-success',
    'pendiente'  => 'tone-warning',
    'suspendido' => 'tone-danger',
    'cancelado'  => 'tone-muted',
];

$hasFilters = !empty($busqueda) || !empty($estadoFiltro) || !empty($planFiltro);

$baseParams = array_filter([
    'q' => $busqueda ?? '',
    'estado' => $estadoFiltro ?? '',
    'plan' => $planFiltro ?? '',
]);
?>

<?php $view->section('title') ?>Empresas | Superadmin<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-buildings"></i></span>
        <div>
            <h1>Empresas</h1>
            <small><?= $total ?> empresa<?= $total !== 1 ? 's' : '' ?> registrada<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <div style="display:flex; gap:.5rem;">
        <a href="<?= $view->url('/admin/tenants/crear') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
            <i class="bi bi-plus-lg"></i>Crear tenant
        </a>
        <a href="<?= $view->url('/admin') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Volver al panel
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="ds-filter-bar">
    <form method="GET" action="<?= $view->url('/admin/tenants') ?>" class="ds-filter-row ds-form-filter">
<div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Buscar</label>
                <input type="text" class="form-control form-control-sm" name="q"
                       placeholder="Razon social, RUC o email..."
                       value="<?= $e((string) ($busqueda ?? '')) ?>">
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Estado</label>
                <select class="form-select form-select-sm" name="estado">
                    <option value="">Todos</option>
                    <?php foreach (['activo','pendiente','suspendido','cancelado'] as $est): ?>
                        <option value="<?= $est ?>" <?= ($estadoFiltro ?? '') === $est ? 'selected' : '' ?>><?= ucfirst($est) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="ds-filter-field">
            <div class="ds-form-field">
                <label class="ds-form-label">Plan</label>
                <select class="form-select form-select-sm" name="plan">
                    <option value="">Todos</option>
                    <?php foreach ($planes as $p): ?>
                        <option value="<?= $e($p->codigo) ?>" <?= ($planFiltro ?? '') === $p->codigo ? 'selected' : '' ?>><?= $e($p->nombre) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="ds-filter-actions">
            <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                <i class="bi bi-search"></i>Buscar
            </button>
            <?php if ($hasFilters): ?>
            <a href="<?= $view->url('/admin/tenants') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                <i class="bi bi-x-lg"></i>Limpiar
            </a>
            <?php endif ?>
        </div>
    </form>
</div>

<?php if ($tenants === []): ?>
<div class="ds-table-card">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-building-slash"></i></div>
        <h3>No hay empresas para mostrar</h3>
        <p>
            <?php if ($hasFilters): ?>
                Ninguna empresa coincide con los filtros aplicados.
            <?php else: ?>
                Cuando una empresa complete el registro publico, aparecera en este listado.
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
                    <th>Empresa</th>
                    <th>RUC</th>
                    <th>Plan</th>
                    <th class="ds-cell-end">Usuarios</th>
                    <th class="ds-cell-end">Fichas</th>
                    <th>Vencimiento</th>
                    <th>Estado</th>
                    <th>Alta</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenants as $t):
                    $venc = $t['plan_vencimiento'] ?? null;
                    $diasRest = $venc ? (int) ((strtotime($venc) - time()) / 86400) : null;
                    $vencCls = '';
                    if ($diasRest !== null) {
                        if ($diasRest <= 0) { $vencCls = 'tone-danger'; }
                        elseif ($diasRest <= 15) { $vencCls = 'tone-warning'; }
                    }
                    $tone = $estadoTone[(string) $t['estado']] ?? 'tone-muted';
                ?>
                <tr>
                    <td>
                        <a href="<?= $view->url('/admin/tenants/' . $e((string) $t['id'])) ?>"
                           class="ds-cell-name" style="text-decoration: none;">
                            <?= $e((string) $t['razon_social']) ?>
                        </a>
                        <div class="ds-cell-sub"><?= $e((string) $t['email_contacto']) ?></div>
                    </td>
                    <td><span class="ds-cell-mono"><?= $e((string) $t['ruc']) ?></span></td>
                    <td><span class="ds-chip"><i class="bi bi-gem"></i><?= $e((string) $t['plan_codigo']) ?></span></td>
                    <td class="ds-cell-end">
                        <span class="ds-cell-sub"><?= (int) ($t['usuarios_activos'] ?? 0) ?> / <?= (int) ($t['max_usuarios'] ?? 0) ?></span>
                    </td>
                    <td class="ds-cell-end">
                        <span class="ds-cell-sub"><?= (int) ($t['total_fichas'] ?? 0) ?> / <?= (int) ($t['max_guardias'] ?? 0) ?></span>
                    </td>
                    <td>
                        <?php if ($venc): ?>
                            <?php if ($vencCls !== ''): ?>
                                <span class="ds-badge <?= $vencCls ?> no-dot"><?= $e(substr($venc, 0, 10)) ?></span>
                            <?php else: ?>
                                <span class="ds-cell-mono"><?= $e(substr($venc, 0, 10)) ?></span>
                            <?php endif ?>
                        <?php else: ?>
                            <span class="ds-cell-sub">&mdash;</span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e((string) $t['estado']) ?></span></td>
                    <td><span class="ds-cell-mono"><?= $e(substr((string) ($t['created_at'] ?? ''), 0, 10)) ?></span></td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/admin/tenants/' . $e((string) $t['id'])) ?>"
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

    <?php if ($totalPaginas > 1): ?>
    <div class="ds-table-foot">
        <span>Pagina <strong><?= $pagina ?></strong> de <?= $totalPaginas ?> &middot; <?= $total ?> empresa<?= $total !== 1 ? 's' : '' ?></span>
        <ul class="ds-pagination">
            <?php for ($p = 1; $p <= $totalPaginas; $p++):
                $params = array_filter($baseParams + ['pagina' => $p > 1 ? $p : null]);
                $qs = $params !== [] ? '?' . http_build_query($params) : '';
            ?>
            <li>
                <a class="ds-page <?= $p === $pagina ? 'is-active' : '' ?>"
                   href="<?= $view->url('/admin/tenants') . $qs ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
        </ul>
    </div>
    <?php endif ?>
</div>
<?php endif ?>
<?php $view->endSection() ?>

