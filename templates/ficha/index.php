<?php
/**
 * Listado de fichas de guardia con paginación y filtros.
 *
 * Variables: $user, $csrfToken, $currentPath, $fichas, $total, $page, $totalPages, $filters
 */

use DigiSecure\Ficha\Domain\Enum\EstadoFicha;
use DigiSecure\Ficha\Domain\Enum\TipoFicha;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Guardias — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-person-badge me-1"></i>Guardias</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-person-badge me-2"></i>Fichas de Guardia</h4>
        <p class="text-muted mb-0"><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <?php if ($user->tienePermiso('fichas.crear')): ?>
    <a href="/guardias/crear" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva Ficha
    </a>
    <?php endif ?>
</div>

<!-- Filtros -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="get" action="/guardias" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Nombre, apellido o correlativo..."
                       value="<?= $e($filters['busqueda'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (EstadoFicha::cases() as $est): ?>
                    <option value="<?= $est->value ?>" <?= ($filters['estado'] ?? '') === $est->value ? 'selected' : '' ?>>
                        <?= $e($est->label()) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Tipo</label>
                <select name="tipo_ficha" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (TipoFicha::cases() as $tipo): ?>
                    <option value="<?= $tipo->value ?>" <?= ($filters['tipo_ficha'] ?? '') === $tipo->value ? 'selected' : '' ?>>
                        <?= $e($tipo->label()) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
            </div>
            <?php if ($filters): ?>
            <div class="col-md-2">
                <a href="/guardias" class="btn btn-sm btn-outline-secondary w-100">
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
                    <th>Correlativo</th>
                    <th>Apellidos y Nombres</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Fecha Emisión</th>
                    <th>Celular</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($fichas === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No se encontraron fichas.
                    </td>
                </tr>
                <?php endif ?>
                <?php foreach ($fichas as $f):
                    $estado = EstadoFicha::from($f['estado']);
                    $tipoF = TipoFicha::from($f['tipo_ficha']);
                ?>
                <tr>
                    <td>
                        <code class="fw-bold"><?= $e($f['correlativo']) ?></code>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= $e($f['apellidos']) ?> <?= $e($f['nombres']) ?></div>
                        <?php if ($f['email']): ?>
                        <small class="text-muted"><?= $e($f['email']) ?></small>
                        <?php endif ?>
                    </td>
                    <td>
                        <span class="badge bg-info text-dark"><?= $e($tipoF->label()) ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $estado->badgeClass() ?>"><?= $e($estado->label()) ?></span>
                    </td>
                    <td><?= $f['fecha_emision'] ? date('d/m/Y', strtotime($f['fecha_emision'])) : '—' ?></td>
                    <td><?= $e($f['celular'] ?? '—') ?></td>
                    <td class="text-end">
                        <a href="/guardias/<?= $e($f['id']) ?>" class="btn btn-sm btn-outline-primary" title="Ver">
                            <i class="bi bi-eye"></i>
                        </a>
                        <?php if ($user->tienePermiso('fichas.editar') && in_array($f['estado'], ['BORRADOR', 'EN_REVISION'])): ?>
                        <a href="/guardias/<?= $e($f['id']) ?>/editar" class="btn btn-sm btn-outline-secondary" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>
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
                    'estado' => $filters['estado'] ?? '',
                    'tipo_ficha' => $filters['tipo_ficha'] ?? '',
                    'q' => $filters['busqueda'] ?? '',
                ]));
                $qsPrefix = $qs ? "&{$qs}" : '';
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="/guardias?page=<?= $page - 1 ?><?= $qsPrefix ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="/guardias?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
                </li>
                <?php endfor ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="/guardias?page=<?= $page + 1 ?><?= $qsPrefix ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
