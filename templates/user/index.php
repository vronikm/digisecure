<?php
/**
 * Listado de usuarios.
 *
 * Variables: $user, $csrfToken, $currentPath, $usuarios, $rolesByUser, $total, $page, $totalPages, $filters
 * Opcional: $esSuperAdmin
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$esSuperAdmin = (bool) ($esSuperAdmin ?? false);

$badgeMap = [
    'activo' => 'tone-success',
    'inactivo' => 'tone-muted',
    'bloqueado' => 'tone-danger',
    'pendiente_verificacion' => 'tone-warning',
];

$qs = http_build_query(array_filter(['estado' => $filters['estado'] ?? '']));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
$tableColumns = $esSuperAdmin ? 7 : 6;
$hasFilters = !empty($filters['estado'] ?? null);
?>

<?php $view->section('title') ?>Usuarios - DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-op-filter .ds-filter-bar {
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
    }

    .ds-op-filter .ds-filter-row {
        padding: 0;
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-people me-1"></i>Usuarios</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-people-fill"></i></span>
        <div>
            <h1>Usuarios del sistema</h1>
            <small><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <?php if ($user->tienePermiso('usuarios.crear')): ?>
    <a href="<?= $view->url('/usuarios/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nuevo usuario
    </a>
    <?php endif ?>
</div>

<div class="ds-card ds-op-filter" data-collapsible-mobile>
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros de usuarios</div>
            <div class="ds-card-subtitle">Consulta por estado de cuenta</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form method="get" action="<?= $view->url('/usuarios') ?>" class="ds-filter-row ds-form-filter">
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <?php foreach (['activo', 'inactivo', 'bloqueado', 'pendiente_verificacion'] as $est): ?>
                            <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>>
                                <?= $e(ucfirst(str_replace('_', ' ', $est))) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-search"></i>Buscar
                    </button>
                    <?php if ($hasFilters): ?>
                    <a href="<?= $view->url('/usuarios') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                    <?php endif ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-people"></i></span>
        <div>
            <div class="ds-card-title">Listado de usuarios</div>
            <div class="ds-card-subtitle">Todos los usuarios creados, roles y ultimo acceso</div>
        </div>
    </div>

    <?php if ($usuarios === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-people"></i></div>
            <h3>No hay usuarios para mostrar</h3>
            <p>
                <?php if ($hasFilters): ?>
                    Ningun usuario coincide con el estado seleccionado. Intenta con otro filtro o limpia los criterios.
                <?php else: ?>
                    Empieza dando de alta a tu equipo. Cada usuario podra acceder con su email y rol asignado.
                <?php endif ?>
            </p>
            <?php if ($user->tienePermiso('usuarios.crear') && !$hasFilters): ?>
            <a href="<?= $view->url('/usuarios/crear') ?>" class="ds-btn ds-btn-primary">
                <i class="bi bi-plus-lg"></i>Crear el primer usuario
            </a>
            <?php endif ?>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <?php if ($esSuperAdmin): ?>
                    <th>Empresa</th>
                    <?php endif ?>
                    <th>Roles</th>
                    <th>Estado</th>
                    <th>Ultimo login</th>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php $lastTenantGroup = null; ?>
                <?php foreach ($usuarios as $u):
                    $estado = $u['estado'];
                    $tone = $badgeMap[$estado] ?? 'tone-muted';
                    $tenantId = (string) ($u['tenant_id'] ?? '');
                    $tenantNombre = (string) ($u['tenant_nombre'] ?? 'Sin empresa');
                ?>
                <?php if ($esSuperAdmin && $tenantId !== $lastTenantGroup): ?>
                <tr class="ds-table-group-row">
                    <td colspan="<?= $tableColumns ?>">
                        <div class="ds-group-title"><?= $e($tenantNombre !== '' ? $tenantNombre : 'Sin empresa') ?></div>
                    </td>
                </tr>
                <?php $lastTenantGroup = $tenantId; ?>
                <?php endif ?>
                <tr>
                    <td>
                        <div class="ds-cell-name"><?= $e($u['apellidos']) ?> <?= $e($u['nombres']) ?></div>
                        <?php if (!empty($u['cargo'])): ?>
                        <div class="ds-cell-sub"><?= $e($u['cargo']) ?></div>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-mono"><?= $e($u['email']) ?></td>
                    <?php if ($esSuperAdmin): ?>
                    <td>
                        <div class="ds-cell-name"><?= $e($tenantNombre) ?></div>
                    </td>
                    <?php endif ?>
                    <td>
                        <?php foreach ($rolesByUser[$u['id']] ?? [] as $r): ?>
                        <span class="ds-chip"><i class="bi bi-shield-check"></i><?= $e($r['nombre']) ?></span>
                        <?php endforeach ?>
                        <?php if (empty($rolesByUser[$u['id']])): ?>
                        <span class="ds-cell-sub">Sin rol asignado</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <span class="ds-badge <?= $tone ?>"><?= $e(ucfirst(str_replace('_', ' ', $estado))) ?></span>
                    </td>
                    <td>
                        <?php if ($u['ultimo_login_at']): ?>
                        <span class="ds-cell-mono"><?= date('d/m/Y H:i', strtotime($u['ultimo_login_at'])) ?></span>
                        <?php else: ?>
                        <span class="ds-cell-sub">Nunca</span>
                        <?php endif ?>
                    </td>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <?php if ($user->tienePermiso('usuarios.editar')): ?>
                            <a href="<?= $view->url('/usuarios/' . $u['id'] . '/editar') ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm" title="Editar">
                                Editar
                            </a>
                            <?php endif ?>
                            <?php if ($user->tienePermiso('usuarios.bloquear') && $u['id'] !== $user->id): ?>
                                <?php if ($estado === 'activo'): ?>
                                <form method="post" action="<?= $view->url('/usuarios/' . $u['id'] . '/estado') ?>" class="d-inline ds-form-inline"
                                      data-confirm
                                      data-confirm-title="Bloquear usuario"
                                      data-confirm-text="Bloquear a <?= $e($u['nombres']) ?>?"
                                      data-confirm-type="warning"
                                      data-confirm-button="Si, bloquear">
                                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="estado" value="bloqueado">
                                    <button type="submit" class="ds-btn ds-btn-danger ds-btn-sm" title="Bloquear">
                                        Bloquear
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="post" action="<?= $view->url('/usuarios/' . $u['id'] . '/estado') ?>" class="d-inline ds-form-inline"
                                      data-confirm
                                      data-confirm-title="Reactivar usuario"
                                      data-confirm-text="Reactivar a <?= $e($u['nombres']) ?>?"
                                      data-confirm-type="success"
                                      data-confirm-button="Si, reactivar">
                                    <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="estado" value="activo">
                                    <button type="submit" class="ds-btn ds-btn-outline ds-btn-sm" title="Reactivar">
                                        Reactivar
                                    </button>
                                </form>
                                <?php endif ?>
                            <?php endif ?>
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
                   href="<?= $view->url('/usuarios') ?>?page=<?= max(1, $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li>
                <a class="ds-page <?= $p === $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/usuarios') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/usuarios') ?>?page=<?= min($totalPages, $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
