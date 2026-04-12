<?php
/**
 * Listado de expedientes con paginación y filtros.
 *
 * Variables: $user, $csrfToken, $currentPath, $expedientes, $total, $page, $totalPages, $filters
 * Opcionales: $titulo (string)
 */

use DigiSecure\Expediente\Domain\Enum\EstadoExpediente;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$titulo = $titulo ?? 'Expedientes Documentales';
?>

<?php $view->section('title') ?><?= $e($titulo) ?> — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-folder2-open me-1"></i>Expedientes</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-folder2-open me-2"></i><?= $e($titulo) ?></h4>
        <p class="text-muted mb-0"><?= $total ?> expediente<?= $total !== 1 ? 's' : '' ?></p>
    </div>
</div>

<!-- Filtros -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Nombre, apellido o correlativo..."
                       value="<?= $e($filters['busqueda'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (EstadoExpediente::cases() as $est): ?>
                    <option value="<?= $est->value ?>" <?= ($filters['estado'] ?? '') === $est->value ? 'selected' : '' ?>>
                        <?= $e($est->label()) ?>
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
                <a href="<?= $e($currentPath) ?>" class="btn btn-sm btn-outline-secondary w-100">
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
                    <th>Guardia</th>
                    <th>Estado Expediente</th>
                    <th>Bloque A</th>
                    <th>Bloque B</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($expedientes === []): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>No se encontraron expedientes.
                    </td>
                </tr>
                <?php endif ?>
                <?php foreach ($expedientes as $exp):
                    $estado = EstadoExpediente::from($exp['estado']);
                    $pctA = $exp['bloque_a_total'] > 0 ? round(($exp['bloque_a_aceptados'] / $exp['bloque_a_total']) * 100) : 0;
                    $pctB = $exp['bloque_b_total'] > 0 ? round(($exp['bloque_b_aceptados'] / $exp['bloque_b_total']) * 100) : 0;
                ?>
                <tr>
                    <td><code class="fw-bold"><?= $e($exp['correlativo']) ?></code></td>
                    <td>
                        <div class="fw-semibold"><?= $e($exp['apellidos']) ?> <?= $e($exp['nombres']) ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $estado->badgeClass() ?>"><?= $e($estado->label()) ?></span>
                    </td>
                    <td style="min-width:120px">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px">
                                <div class="progress-bar <?= $pctA === 100 ? 'bg-success' : 'bg-primary' ?>"
                                     style="width:<?= $pctA ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $exp['bloque_a_aceptados'] ?>/<?= $exp['bloque_a_total'] ?></small>
                        </div>
                    </td>
                    <td style="min-width:120px">
                        <?php if ($exp['bloque_b_desbloqueado']): ?>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px">
                                <div class="progress-bar <?= $pctB === 100 ? 'bg-success' : 'bg-info' ?>"
                                     style="width:<?= $pctB ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $exp['bloque_b_aceptados'] ?>/<?= $exp['bloque_b_total'] ?></small>
                        </div>
                        <?php else: ?>
                        <span class="text-muted small"><i class="bi bi-lock me-1"></i>Bloqueado</span>
                        <?php endif ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= $view->url('/guardias/' . $exp['ficha_id'] . '/expediente') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-folder2-open me-1"></i>Ver
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
                <?php $qs = http_build_query(array_filter(['estado' => $filters['estado'] ?? '', 'q' => $filters['busqueda'] ?? ''])); $qsP = $qs ? "&{$qs}" : ''; ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $e($currentPath) ?>?page=<?= $page - 1 ?><?= $qsP ?>"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $e($currentPath) ?>?page=<?= $p ?><?= $qsP ?>"><?= $p ?></a>
                </li>
                <?php endfor ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $e($currentPath) ?>?page=<?= $page + 1 ?><?= $qsP ?>"><i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
