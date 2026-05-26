<?php
/**
 * Visor de auditoria - listado filtrable.
 *
 * Variables: $user, $csrfToken, $currentPath, $eventos, $total, $page, $totalPages, $filters, $eventosDisponibles
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

$nivelTone = static fn(string $nivel) => match ($nivel) {
    'critical' => 'tone-danger',
    'warning'  => 'tone-warning',
    'info'     => 'tone-info',
    default    => 'tone-muted',
};

$hasFilters = !empty($filters['evento'] ?? null)
    || !empty($filters['nivel'] ?? null)
    || !empty($filters['desde'] ?? null)
    || !empty($filters['hasta'] ?? null);

$qs = http_build_query(array_filter([
    'evento' => $filters['evento'] ?? '',
    'nivel' => $filters['nivel'] ?? '',
    'desde' => $filters['desde'] ?? '',
    'hasta' => $filters['hasta'] ?? '',
    'usuario_id' => $filters['usuario_id'] ?? '',
    'entidad_tipo' => $filters['entidad_tipo'] ?? '',
]));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';
?>

<?php $view->section('title') ?>Auditoria - DigiSecure<?php $view->endSection() ?>

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
<li class="breadcrumb-item active"><i class="bi bi-shield-check me-1"></i>Auditoria</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-shield-check"></i></span>
        <div>
            <h1>Registro de auditoria</h1>
            <small><?= $total ?> evento<?= $total !== 1 ? 's' : '' ?> registrado<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <?php if ($user->tienePermiso('auditoria.exportar')): ?>
    <a href="<?= $view->url('/auditoria/exportar?' . http_build_query($filters)) ?>"
       class="ds-btn ds-btn-success ds-btn-sm">
        <i class="bi bi-download"></i>Exportar CSV
    </a>
    <?php endif ?>
</div>

<div class="ds-card ds-op-filter">
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros de auditoria</div>
            <div class="ds-card-subtitle">Consulta por evento, nivel y rango de fechas</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form method="get" action="<?= $view->url('/auditoria') ?>" class="ds-filter-row ds-form-filter ds-filter-row-auditoria">
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Evento</label>
                        <input type="text" name="evento" class="form-control form-control-sm" list="eventos-list"
                               value="<?= $e($filters['evento'] ?? '') ?>" placeholder="ej: auth.login_ok">
                        <datalist id="eventos-list">
                            <?php foreach ($eventosDisponibles as $ev): ?>
                            <option value="<?= $e($ev) ?>"></option>
                            <?php endforeach ?>
                        </datalist>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Nivel</label>
                        <select name="nivel" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (['info','warning','critical'] as $niv): ?>
                            <option value="<?= $niv ?>" <?= ($filters['nivel'] ?? '') === $niv ? 'selected' : '' ?>>
                                <?= $e(ucfirst($niv)) ?>
                            </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Desde</label>
                        <input type="date" name="desde" class="form-control form-control-sm"
                               value="<?= $e($filters['desde'] ?? '') ?>">
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Hasta</label>
                        <input type="date" name="hasta" class="form-control form-control-sm"
                               value="<?= $e($filters['hasta'] ?? '') ?>">
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-search"></i>Buscar
                    </button>
                    <?php if ($hasFilters): ?>
                    <a href="<?= $view->url('/auditoria') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
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
        <span class="ds-card-mark"><i class="bi bi-journal-text"></i></span>
        <div>
            <div class="ds-card-title">Eventos de auditoria</div>
            <div class="ds-card-subtitle">Bitacora de acciones y trazabilidad de seguridad</div>
        </div>
    </div>

    <?php if ($eventos === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-journal-text"></i></div>
            <h3>No hay eventos en auditoria</h3>
            <p>
                <?php if ($hasFilters): ?>
                    Ningun evento coincide con los filtros aplicados. Ajusta los criterios o limpia los filtros.
                <?php else: ?>
                    Los eventos se registran automaticamente cuando los usuarios realizan acciones en el sistema.
                <?php endif ?>
            </p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
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
                <?php foreach ($eventos as $ev): ?>
                <tr>
                    <td><span class="ds-cell-mono"><?= date('d/m/Y H:i:s', strtotime($ev['created_at'])) ?></span></td>
                    <td><span class="ds-badge <?= $nivelTone($ev['nivel']) ?>"><?= $e(ucfirst($ev['nivel'])) ?></span></td>
                    <td><span class="ds-cell-mono"><?= $e($ev['evento']) ?></span></td>
                    <td>
                        <?php if (!empty($ev['usuario_email'])): ?>
                        <div class="ds-cell-name"><?= $e($ev['usuario_apellidos'] ?? '') ?> <?= $e($ev['usuario_nombres'] ?? '') ?></div>
                        <div class="ds-cell-sub"><?= $e($ev['usuario_email']) ?></div>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (!empty($ev['entidad_tipo'])): ?>
                        <span class="ds-chip"><i class="bi bi-tag"></i><?= $e($ev['entidad_tipo']) ?></span>
                        <div class="ds-cell-sub" style="margin-top:.2rem;"><span class="ds-cell-mono"><?= $e($ev['entidad_id'] ?? '') ?></span></div>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                    <td><span class="ds-cell-mono"><?= $e($ev['ip_text'] ?? '-') ?></span></td>
                    <td>
                        <?php if (!empty($ev['detalles_json'])): ?>
                        <details>
                            <summary style="cursor:pointer; color:#2563eb; font-size:.78rem; font-weight:600;">Ver JSON</summary>
                            <pre style="background:#0f172a; color:#e2e8f0; padding:.6rem .75rem; border-radius:8px; max-width:400px; max-height:240px; overflow:auto; font-size:.72rem; margin:.4rem 0 0; white-space:pre-wrap; word-break:break-word;"><?= $e(json_encode(json_decode((string) $ev['detalles_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                        </details>
                        <?php else: ?>
                        <span class="ds-cell-sub">-</span>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="ds-table-foot">
        <span>Pagina <strong><?= $page ?></strong> de <?= $totalPages ?> &middot; <?= $total ?> evento<?= $total !== 1 ? 's' : '' ?></span>
        <ul class="ds-pagination">
            <li>
                <a class="ds-page <?= $page <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/auditoria') ?>?page=<?= max(1, $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <li>
                <a class="ds-page <?= $p === $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/auditoria') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/auditoria') ?>?page=<?= min($totalPages, $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
