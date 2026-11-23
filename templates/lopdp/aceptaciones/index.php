<?php
/**
 * Listado de aceptaciones de Politica de Privacidad TDP desde registro publico.
 *
 * Variables: $user, $csrfToken, $currentPath, $aceptaciones, $kpis, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$filters = is_array($filters ?? null) ? $filters : [];
$kpis = is_array($kpis ?? null) ? $kpis : [];

/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$busqueda = (string) ($filters['q'] ?? '');
$total = $paginacion->total();
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

<div class="ds-card ds-lopdp-filter" data-collapsible-mobile>
    <div class="ds-card-header tone-info">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros</div>
            <div class="ds-card-subtitle">Estado de la autorizacion LOPDP y fechas de aceptacion</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form id="filtrosAceptaciones" method="get" action="<?= $view->url('/lopdp/aceptaciones') ?>" class="ds-filter-row ds-form-filter">
                <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
                <?php if ($paginacion->parametro() !== null): ?>
                <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
                <?php endif ?>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroAcepDoc">Documento LOPDP</label>
                        <select name="documento_lopdp" id="filtroAcepDoc" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <option value="subido" <?= ($filters['documento_lopdp'] ?? '') === 'subido' ? 'selected' : '' ?>>Subido</option>
                            <option value="pendiente" <?= ($filters['documento_lopdp'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroAcepDesde">Desde</label>
                        <input type="date" name="desde" id="filtroAcepDesde" class="form-control form-control-sm" value="<?= $e((string) ($filters['desde'] ?? '')) ?>">
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroAcepHasta">Hasta</label>
                        <input type="date" name="hasta" id="filtroAcepHasta" class="form-control form-control-sm" value="<?= $e((string) ($filters['hasta'] ?? '')) ?>">
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                    <a href="<?= $view->url('/lopdp/aceptaciones') ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar>
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
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
            <div class="ds-card-subtitle" data-resumen-de="#aceptacionesResultados" aria-live="polite"><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?></div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarAceptaciones',
            'destino' => '#aceptacionesResultados',
            'parcial' => 'aceptaciones',
            'ruta' => '/lopdp/aceptaciones',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar aspirante...',
            'ayuda' => 'Buscar aceptaciones por nombres, apellidos, documento, correo o celular',
            'filtros' => '#filtrosAceptaciones',
            'ocultos' => [
                'documento_lopdp' => (string) ($filters['documento_lopdp'] ?? ''),
                'desde' => (string) ($filters['desde'] ?? ''),
                'hasta' => (string) ($filters['hasta'] ?? ''),
            ],
        ]) ?>
    </div>
    <div id="aceptacionesResultados" class="ds-listado-wrap">
        <?= $view->include('lopdp/aceptaciones/_listado', ['aceptaciones' => $aceptaciones, 'paginacion' => $paginacion, 'filters' => $filters]) ?>
    </div>
</div>
<?php $view->endSection() ?>
