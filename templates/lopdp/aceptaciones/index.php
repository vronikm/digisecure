<?php
/**
 * Listado de aceptaciones de Politica de Privacidad TDP desde registro publico.
 *
 * Variables: $user, $csrfToken, $currentPath, $aceptaciones, $kpis, $total,
 *            $page, $totalPages, $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$filters = is_array($filters ?? null) ? $filters : [];
$kpis = is_array($kpis ?? null) ? $kpis : [];

$hasFilters = !empty($filters['q'] ?? null)
    || !empty($filters['documento_lopdp'] ?? null)
    || !empty($filters['desde'] ?? null)
    || !empty($filters['hasta'] ?? null);

$qs = http_build_query(array_filter([
    'q' => $filters['q'] ?? '',
    'documento_lopdp' => $filters['documento_lopdp'] ?? '',
    'desde' => $filters['desde'] ?? '',
    'hasta' => $filters['hasta'] ?? '',
], static fn($v): bool => $v !== null && $v !== ''));
$qsPrefix = $qs !== '' ? "&{$qs}" : '';

$formatDateTime = static function ($value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : $value;
};
?>

<?php $view->section('title') ?>Aceptaciones politica LOPDP<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-lopdp-filter .ds-filter-bar {
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
    }

    .ds-lopdp-filter .ds-filter-row {
        padding: 0;
    }

    .ds-lopdp-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-top: .35rem;
    }

    .ds-lopdp-meta span {
        border: 1px solid rgba(148, 163, 184, .28);
        border-radius: 999px;
        color: var(--ds-text-soft);
        font-size: .76rem;
        padding: .18rem .5rem;
        white-space: nowrap;
    }

    .ds-lopdp-user-agent {
        color: var(--ds-text-soft);
        font-size: .78rem;
        max-width: 360px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp') ?>">LOPDP</a></li>
<li class="breadcrumb-item active">Aceptaciones politica</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-person-check"></i></span>
        <div>
            <h1>Aceptaciones de politica</h1>
            <small>Registro de aceptacion de Politica de Privacidad y carga de Autorizacion LOPDP</small>
        </div>
    </div>
    <div class="ds-row-actions">
        <a href="<?= $view->url('/lopdp/consentimientos/PoliticaPrivacidad_TDP.pdf') ?>" target="_blank" rel="noopener noreferrer" class="ds-btn ds-btn-outline ds-btn-sm">
            <i class="bi bi-file-earmark-pdf"></i>Ver politica
        </a>
        <a href="<?= $view->url('/lopdp') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
            <i class="bi bi-arrow-left"></i>Panel LOPDP
        </a>
    </div>
</div>

<div class="ds-kpi-grid ds-kpi-grid-solid">
    <div class="ds-kpi-card">
        <span class="ds-kpi-icon"><i class="bi bi-check2-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Aceptaron politica</div>
            <div class="ds-kpi-value"><?= (int) ($kpis['total'] ?? 0) ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-file-earmark-check"></i></span>
        <div>
            <div class="ds-kpi-label">Autorizacion subida</div>
            <div class="ds-kpi-value"><?= (int) ($kpis['autorizacion_subida'] ?? 0) ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
        <div>
            <div class="ds-kpi-label">Autorizacion pendiente</div>
            <div class="ds-kpi-value"><?= (int) ($kpis['autorizacion_pendiente'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="ds-card ds-lopdp-filter">
    <div class="ds-card-header tone-info">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros</div>
            <div class="ds-card-subtitle">Busca por nombres, correo, documento o estado de autorizacion</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form method="get" action="<?= $view->url('/lopdp/aceptaciones') ?>" class="ds-filter-row ds-form-filter">
                <div class="ds-filter-field" style="min-width: 260px;">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Busqueda</label>
                        <input type="search" name="q" class="form-control form-control-sm"
                               placeholder="Nombre, cedula o correo"
                               value="<?= $e((string) ($filters['q'] ?? '')) ?>">
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Documento LOPDP</label>
                        <select name="documento_lopdp" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="subido" <?= ($filters['documento_lopdp'] ?? '') === 'subido' ? 'selected' : '' ?>>Subido</option>
                            <option value="pendiente" <?= ($filters['documento_lopdp'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Desde</label>
                        <input type="date" name="desde" class="form-control form-control-sm"
                               value="<?= $e((string) ($filters['desde'] ?? '')) ?>">
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label">Hasta</label>
                        <input type="date" name="hasta" class="form-control form-control-sm"
                               value="<?= $e((string) ($filters['hasta'] ?? '')) ?>">
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-search"></i>Buscar
                    </button>
                    <?php if ($hasFilters): ?>
                    <a href="<?= $view->url('/lopdp/aceptaciones') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                    <?php endif ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-shield-lock"></i></span>
        <div>
            <div class="ds-card-title">Trazabilidad LOPDP</div>
            <div class="ds-card-subtitle"><?= (int) ($total ?? 0) ?> registro<?= ((int) ($total ?? 0)) !== 1 ? 's' : '' ?> encontrado<?= ((int) ($total ?? 0)) !== 1 ? 's' : '' ?></div>
        </div>
    </div>

    <?php if (($aceptaciones ?? []) === []): ?>
    <div class="ds-card-body">
        <div class="ds-empty-state">
            <div class="ds-empty-icon"><i class="bi bi-person-check"></i></div>
            <h3>Sin aceptaciones registradas</h3>
            <p>
                <?php if ($hasFilters): ?>
                    Ningun registro coincide con los filtros seleccionados.
                <?php else: ?>
                    Cuando un aspirante acepte la Politica de Privacidad desde el modal de registro, aparecera aqui.
                <?php endif ?>
            </p>
        </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Aspirante</th>
                    <th>Aceptacion politica</th>
                    <th>Origen</th>
                    <th>Autorizacion LOPDP</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aceptaciones as $row):
                    $nombre = trim((string) ($row['nombres'] ?? '') . ' ' . (string) ($row['apellidos'] ?? ''));
                    $autorizacionSubida = !empty($row['lopdp_autorizacion_subida_at']);
                ?>
                <tr>
                    <td>
                        <div class="ds-cell-name"><?= $e($nombre !== '' ? $nombre : 'Sin nombre') ?></div>
                        <div class="ds-cell-sub"><?= $e((string) ($row['correo'] ?? '')) ?></div>
                        <div class="ds-cell-sub">
                            <span class="ds-cell-mono"><?= $e(strtoupper((string) ($row['tipo_documento'] ?? ''))) ?> <?= $e((string) ($row['documento'] ?? '')) ?></span>
                        </div>
                    </td>
                    <td>
                        <div><span class="ds-cell-mono"><?= $e($formatDateTime($row['politicas_aceptadas_at'] ?? $row['created_at'] ?? '')) ?></span></div>
                        <div class="ds-cell-sub">Version: <?= $e((string) ($row['politicas_version'] ?? 'No registrada')) ?></div>
                        <div class="ds-lopdp-meta">
                            <span><i class="bi bi-globe2"></i> IP <?= $e((string) ($row['politicas_aceptadas_ip_texto'] ?? '-')) ?></span>
                            <span><i class="bi bi-envelope-check"></i> <?= ((int) ($row['email_verificado'] ?? 0)) === 1 ? 'Correo verificado' : 'Correo pendiente' ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="ds-cell-name"><?= $e((string) ($row['politicas_aceptadas_desde'] ?? '-')) ?></div>
                        <div class="ds-cell-sub"><?= $e((string) ($row['politicas_aceptadas_ruta'] ?? '-')) ?></div>
                        <?php if (!empty($row['politicas_aceptadas_user_agent'])): ?>
                        <div class="ds-lopdp-user-agent" title="<?= $e((string) $row['politicas_aceptadas_user_agent']) ?>">
                            <?= $e((string) $row['politicas_aceptadas_user_agent']) ?>
                        </div>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($autorizacionSubida): ?>
                            <span class="ds-badge tone-success">Subida</span>
                            <div class="ds-cell-sub" style="margin-top:.35rem;">
                                <?= $e($formatDateTime($row['lopdp_autorizacion_subida_at'] ?? '')) ?>
                            </div>
                            <div class="ds-cell-sub" title="<?= $e((string) ($row['lopdp_autorizacion_archivo_nombre'] ?? '')) ?>">
                                <?= $e((string) ($row['lopdp_autorizacion_archivo_nombre'] ?? 'Archivo registrado')) ?>
                            </div>
                        <?php else: ?>
                            <span class="ds-badge tone-warning">Pendiente</span>
                            <div class="ds-cell-sub" style="margin-top:.35rem;">Aun no sube el PDF de autorizacion.</div>
                        <?php endif ?>
                    </td>
                    <td>
                        <span class="ds-badge <?= (string) ($row['estado'] ?? '') === 'aspirante' ? 'tone-info' : 'tone-muted' ?>">
                            <?= $e((string) ($row['estado'] ?? 'sin_estado')) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php if (($totalPages ?? 1) > 1): ?>
    <div class="ds-table-foot">
        <span>Pagina <strong><?= (int) $page ?></strong> de <?= (int) $totalPages ?> &middot; <?= (int) $total ?> registro<?= ((int) $total) !== 1 ? 's' : '' ?></span>
        <ul class="ds-pagination">
            <li>
                <a class="ds-page <?= ((int) $page) <= 1 ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/lopdp/aceptaciones') ?>?page=<?= max(1, (int) $page - 1) ?><?= $qsPrefix ?>" aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php for ($p = max(1, (int) $page - 2); $p <= min((int) $totalPages, (int) $page + 2); $p++): ?>
            <li>
                <a class="ds-page <?= $p === (int) $page ? 'is-active' : '' ?>"
                   href="<?= $view->url('/lopdp/aceptaciones') ?>?page=<?= $p ?><?= $qsPrefix ?>"><?= $p ?></a>
            </li>
            <?php endfor ?>
            <li>
                <a class="ds-page <?= ((int) $page) >= (int) $totalPages ? 'is-disabled' : '' ?>"
                   href="<?= $view->url('/lopdp/aceptaciones') ?>?page=<?= min((int) $totalPages, (int) $page + 1) ?><?= $qsPrefix ?>" aria-label="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php endif ?>
    <?php endif ?>
</div>
<?php $view->endSection() ?>
