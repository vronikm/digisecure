<?php
/**
 * Listado de expedientes con paginacion y filtros.
 *
 * Variables: $user, $csrfToken, $currentPath, $expedientes, $total, $page, $totalPages, $filters
 * Opcionales: $titulo (string)
 */

use DigiSecure\Expediente\Domain\Enum\EstadoExpediente;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$titulo = $titulo ?? 'Expedientes documentales';
$mostrarBloqueB = (bool) ($mostrarBloqueB ?? false);

$estadoTone = [
    'INCOMPLETO'         => 'tone-warning',
    'BLOQUE_A_COMPLETO'  => 'tone-info',
    'COMPLETO'           => 'tone-success',
];

$hasFilters = !empty($filters['estado'] ?? null) || !empty($filters['busqueda'] ?? null);

$qs = http_build_query(array_filter([
    'estado' => $filters['estado'] ?? '',
    'q' => $filters['busqueda'] ?? '',
]));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
?>

<?php $view->section('title') ?><?= $e($titulo) ?> - DigiSecure<?php $view->endSection() ?>

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
<li class="breadcrumb-item active"><i class="bi bi-folder2-open me-1"></i>Expedientes</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-folder2-open"></i></span>
        <div>
            <h1><?= $e($titulo) ?></h1>
            <small><?= $total ?> expediente<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
</div>

<div class="ds-card ds-op-filter">
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form method="get" action="<?= $view->url($currentPath) ?>" class="ds-filter-row ds-form-filter">
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Buscar</label>
                        <input type="text" name="q" class="form-control form-control-sm"
                               placeholder="Nombre, apellido o correlativo..."
                               value="<?= $e($filters['busqueda'] ?? '') ?>">
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <?php foreach (EstadoExpediente::cases() as $est): ?>
                            <option value="<?= $est->value ?>" <?= ($filters['estado'] ?? '') === $est->value ? 'selected' : '' ?>>
                                <?= $e($est->label()) ?>
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
                    <a href="<?= $view->url($currentPath) ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
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
        <span class="ds-card-mark"><i class="bi bi-folder2"></i></span>
        <div>
            <div class="ds-card-title">Listado de expedientes</div>
            <div class="ds-card-subtitle">Control documental por estado y avance</div>
        </div>
    </div>

    <?php if ($expedientes === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-folder2"></i></div>
            <h3>No hay expedientes para mostrar</h3>
            <p>
                <?php if ($hasFilters): ?>
                    Ningun expediente coincide con los criterios. Ajusta los filtros o limpia los criterios.
                <?php else: ?>
                    Los expedientes se generan automaticamente al crear una ficha de guardia.
                <?php endif ?>
            </p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Correlativo</th>
                    <th>Guardia</th>
                    <th>Estado</th>
                    <th>Bloque A</th>
                    <?php if ($mostrarBloqueB): ?>
                    <th>Bloque B</th>
                    <?php endif ?>
                    <th class="ds-cell-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expedientes as $exp):
                    $estado = EstadoExpediente::from($exp['estado']);
                    $tone = $estadoTone[$exp['estado']] ?? 'tone-muted';
                    $pctA = $exp['bloque_a_total'] > 0 ? round(($exp['bloque_a_aceptados'] / $exp['bloque_a_total']) * 100) : 0;
                    $pctB = $exp['bloque_b_total'] > 0 ? round(($exp['bloque_b_aceptados'] / $exp['bloque_b_total']) * 100) : 0;
                    $toneA = $pctA === 100 ? 'tone-success' : ($pctA >= 50 ? '' : 'tone-warning');
                    $toneB = $pctB === 100 ? 'tone-success' : 'tone-info';
                ?>
                <tr>
                    <td><span class="ds-cell-mono" style="font-weight:700;color:var(--ds-text);"><?= $e($exp['correlativo']) ?></span></td>
                    <td>
                        <div class="ds-cell-name"><?= $e($exp['apellidos']) ?> <?= $e($exp['nombres']) ?></div>
                    </td>
                    <td><span class="ds-badge <?= $tone ?>"><?= $e($estado->label()) ?></span></td>
                    <td>
                        <div class="ds-progress-row">
                            <div class="ds-progress">
                                <div class="ds-progress-bar <?= $toneA ?>" style="width: <?= $pctA ?>%"></div>
                            </div>
                            <small><?= $exp['bloque_a_aceptados'] ?>/<?= $exp['bloque_a_total'] ?></small>
                        </div>
                    </td>
                    <?php if ($mostrarBloqueB): ?>
                    <td>
                        <?php if ($exp['bloque_b_desbloqueado']): ?>
                        <div class="ds-progress-row">
                            <div class="ds-progress">
                                <div class="ds-progress-bar <?= $toneB ?>" style="width: <?= $pctB ?>%"></div>
                            </div>
                            <small><?= $exp['bloque_b_aceptados'] ?>/<?= $exp['bloque_b_total'] ?></small>
                        </div>
                        <?php else: ?>
                        <span class="ds-cell-sub"><i class="bi bi-lock"></i> Bloqueado</span>
                        <?php endif ?>
                    </td>
                    <?php endif ?>
                    <td class="ds-cell-end">
                        <div class="ds-row-actions">
                            <a href="<?= $view->url('/guardias/' . $exp['ficha_id'] . '/expediente') ?>"
                               class="ds-btn ds-btn-outline ds-btn-sm">
                                Abrir expediente
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
        <span>Pagina <strong><?= $page ?></strong> de <?= $totalPages ?> &middot; <?= $total ?> expediente<?= $total !== 1 ? 's' : '' ?></span>
        <ul class="ds-pagination">
            <li>
                <a class="ds-page <?= $page <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= $view->url($currentPath) ?>?page=<?= max(1, $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li>
                <a class="ds-page <?= $p === $page ? 'is-active' : '' ?>"
                   href="<?= $view->url($currentPath) ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url($currentPath) ?>?page=<?= min($totalPages, $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
