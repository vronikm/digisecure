<?php
/**
 * Visor de auditoria - listado filtrable.
 *
 * Variables: $user, $csrfToken, $currentPath, $eventos, $paginacion (Paginacion), $filters, $eventosDisponibles
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$busqueda = (string) ($filters['q'] ?? '');
$total = $paginacion->total();
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
            <small data-resumen-de="#auditoriaResultados" aria-live="polite"><?= $total ?> evento<?= $total !== 1 ? 's' : '' ?> registrado<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <?php if ($user->tienePermiso('auditoria.exportar')): ?>
    <a href="<?= $view->url('/auditoria/exportar') ?><?= $filters !== [] ? '?' . $e(http_build_query($filters)) : '' ?>"
       class="ds-btn ds-btn-success ds-btn-sm"
       data-exportar-de="#auditoriaResultados" data-exportar-base="<?= $view->url('/auditoria/exportar') ?>">
        <i class="bi bi-download"></i>Exportar CSV
    </a>
    <?php endif ?>
</div>

<div class="ds-card ds-op-filter" data-collapsible-mobile>
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros de auditoria</div>
            <div class="ds-card-subtitle">Consulta por evento, nivel y rango de fechas</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form id="filtrosAuditoria" method="get" action="<?= $view->url('/auditoria') ?>" class="ds-filter-row ds-form-filter ds-filter-row-auditoria">
                <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
                <?php if ($paginacion->parametro() !== null): ?>
                <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
                <?php endif ?>
                <?php foreach (['usuario_id', 'entidad_tipo'] as $oculto): if (!empty($filters[$oculto])): ?>
                <input type="hidden" name="<?= $oculto ?>" value="<?= $e((string) $filters[$oculto]) ?>">
                <?php endif; endforeach ?>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroAudEvento">Evento</label>
                        <input type="text" name="evento" id="filtroAudEvento" class="form-control form-control-sm" list="eventos-list"
                               value="<?= $e($filters['evento'] ?? '') ?>" placeholder="ej: auth.login_ok" autocomplete="off">
                        <datalist id="eventos-list">
                            <?php foreach ($eventosDisponibles as $ev): ?>
                            <option value="<?= $e($ev) ?>"></option>
                            <?php endforeach ?>
                        </datalist>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroAudNivel">Nivel</label>
                        <select name="nivel" id="filtroAudNivel" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (['info', 'warning', 'critical'] as $niv): ?>
                            <option value="<?= $niv ?>" <?= ($filters['nivel'] ?? '') === $niv ? 'selected' : '' ?>><?= $e(ucfirst($niv)) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroAudDesde">Desde</label>
                        <input type="date" name="desde" id="filtroAudDesde" class="form-control form-control-sm" value="<?= $e($filters['desde'] ?? '') ?>">
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroAudHasta">Hasta</label>
                        <input type="date" name="hasta" id="filtroAudHasta" class="form-control form-control-sm" value="<?= $e($filters['hasta'] ?? '') ?>">
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                    <a href="<?= $view->url('/auditoria') ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar>
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
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
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarAuditoria',
            'destino' => '#auditoriaResultados',
            'parcial' => 'auditoria',
            'ruta' => '/auditoria',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar evento...',
            'ayuda' => 'Buscar en la auditoria por evento, tipo o id de entidad, correo o nombre del usuario',
            'filtros' => '#filtrosAuditoria',
            'ocultos' => array_diff_key($filters, ['q' => true]),
        ]) ?>
    </div>
    <div id="auditoriaResultados" class="ds-listado-wrap">
        <?= $view->include('auditoria/_listado', ['eventos' => $eventos, 'paginacion' => $paginacion, 'filters' => $filters]) ?>
    </div>
</div>
<?php $view->endSection() ?>
