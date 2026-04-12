<?php
/**
 * Listado de usuarios del tenant.
 *
 * Variables: $user, $csrfToken, $currentPath, $usuarios, $rolesByUser, $total, $page, $totalPages, $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Usuarios — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-people me-1"></i>Usuarios</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-people me-2"></i>Usuarios del sistema</h4>
        <p class="text-muted mb-0"><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <?php if ($user->tienePermiso('usuarios.crear')): ?>
    <a href="<?= $view->url('/usuarios/crear') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo usuario
    </a>
    <?php endif ?>
</div>

<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $e($_GET['error']) ?></div>
<?php endif ?>

<!-- Filtros -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="get" action="<?= $view->url('/usuarios') ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['activo','inactivo','bloqueado','pendiente_verificacion'] as $est): ?>
                    <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>>
                        <?= $e(ucfirst(str_replace('_', ' ', $est))) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
            </div>
            <?php if ($filters['estado'] ?? null): ?>
            <div class="col-md-2">
                <a href="<?= $view->url('/usuarios') ?>" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-x-lg me-1"></i>Limpiar
                </a>
            </div>
            <?php endif ?>
        </form>
    </div>
</div>

<!-- Tabla -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Cargo</th>
                    <th>Roles</th>
                    <th>Estado</th>
                    <th>Último login</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($usuarios === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No se encontraron usuarios.
                    </td>
                </tr>
                <?php endif ?>
                <?php foreach ($usuarios as $u):
                    $estado = $u['estado'];
                    $badgeClass = match ($estado) {
                        'activo' => 'bg-success',
                        'inactivo' => 'bg-secondary',
                        'bloqueado' => 'bg-danger',
                        'pendiente_verificacion' => 'bg-warning text-dark',
                        default => 'bg-light text-dark',
                    };
                ?>
                <tr>
                    <td class="fw-semibold"><?= $e($u['apellidos']) ?> <?= $e($u['nombres']) ?></td>
                    <td><?= $e($u['email']) ?></td>
                    <td><?= $e($u['cargo'] ?? '—') ?></td>
                    <td>
                        <?php foreach ($rolesByUser[$u['id']] ?? [] as $r): ?>
                        <span class="badge bg-info text-dark me-1"><?= $e($r['nombre']) ?></span>
                        <?php endforeach ?>
                    </td>
                    <td><span class="badge <?= $badgeClass ?>"><?= $e(ucfirst(str_replace('_', ' ', $estado))) ?></span></td>
                    <td>
                        <?php if ($u['ultimo_login_at']): ?>
                        <small><?= date('d/m/Y H:i', strtotime($u['ultimo_login_at'])) ?></small>
                        <?php else: ?>
                        <small class="text-muted">Nunca</small>
                        <?php endif ?>
                    </td>
                    <td class="text-end">
                        <?php if ($user->tienePermiso('usuarios.editar')): ?>
                        <a href="<?= $view->url('/usuarios/' . $u['id'] . '/editar') ?>" class="btn btn-sm btn-outline-secondary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php endif ?>
                        <?php if ($user->tienePermiso('usuarios.bloquear') && $u['id'] !== $user->id): ?>
                        <?php if ($estado === 'activo'): ?>
                        <form method="post" action="<?= $view->url('/usuarios/' . $u['id'] . '/estado') ?>" class="d-inline"
                              onsubmit="return confirm('¿Bloquear a <?= $e($u['nombres']) ?>?');">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="estado" value="bloqueado">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Bloquear"><i class="bi bi-lock"></i></button>
                        </form>
                        <?php else: ?>
                        <form method="post" action="<?= $view->url('/usuarios/' . $u['id'] . '/estado') ?>" class="d-inline"
                              onsubmit="return confirm('¿Reactivar a <?= $e($u['nombres']) ?>?');">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="estado" value="activo">
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Reactivar"><i class="bi bi-unlock"></i></button>
                        </form>
                        <?php endif ?>
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
                $qs = http_build_query(array_filter(['estado' => $filters['estado'] ?? '']));
                $qsPrefix = $qs ? "&{$qs}" : '';
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $view->url('/usuarios') ?>?page=<?= $page - 1 ?><?= $qsPrefix ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $view->url('/usuarios') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
                </li>
                <?php endfor ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $view->url('/usuarios') ?>?page=<?= $page + 1 ?><?= $qsPrefix ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
