<?php
/**
 * Listado de solicitudes ARCO-P.
 *
 * Variables: $user, $csrfToken, $currentPath, $solicitudes, $paginacion (Paginacion), $filters
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$busqueda = (string) ($filters['busqueda'] ?? '');
$total = $paginacion->total();
?>

<?php $view->section('title') ?>Solicitudes ARCO-P - LOPDP<?php $view->endSection() ?>

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
<li class="breadcrumb-item"><a href="<?= $view->url('/lopdp') ?>">LOPDP</a></li>
<li class="breadcrumb-item active">Solicitudes ARCO-P</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-list-check"></i></span>
        <div>
            <h1>Solicitudes ARCO-P</h1>
            <small data-resumen-de="#solicitudesResultados" aria-live="polite"><?= (int) $total ?> solicitud<?= $total !== 1 ? 'es' : '' ?> registrada<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <a href="<?= $view->url('/lopdp/solicitudes/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nueva solicitud
    </a>
</div>

<div class="ds-card ds-op-filter" data-collapsible-mobile>
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros de solicitudes</div>
            <div class="ds-card-subtitle">Estado y tipo de derecho</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form id="filtrosSolicitudes" method="get" action="<?= $view->url('/lopdp/solicitudes') ?>" class="ds-filter-row ds-form-filter">
                <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
                <?php if ($paginacion->parametro() !== null): ?>
                <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
                <?php endif ?>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroSolEstado">Estado</label>
                        <select name="estado" id="filtroSolEstado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (['RECIBIDA', 'EN_PROCESO', 'RESPONDIDA', 'DENEGADA'] as $st): ?>
                            <option value="<?= $st ?>" <?= ($filters['estado'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroSolTipo">Tipo</label>
                        <select name="tipo" id="filtroSolTipo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (['acceso', 'rectificacion', 'cancelacion', 'oposicion', 'portabilidad'] as $tp): ?>
                            <option value="<?= $tp ?>" <?= ($filters['tipo'] ?? '') === $tp ? 'selected' : '' ?>><?= ucfirst($tp) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                    <a href="<?= $view->url('/lopdp/solicitudes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar>
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-file-earmark-lock"></i></span>
        <div>
            <div class="ds-card-title">Listado de solicitudes</div>
            <div class="ds-card-subtitle">Seguimiento de requerimientos ARCO-P por fecha limite</div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarSolicitudes',
            'destino' => '#solicitudesResultados',
            'parcial' => 'solicitudes',
            'ruta' => '/lopdp/solicitudes',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar solicitud...',
            'ayuda' => 'Buscar solicitudes por nombre, cedula o correo del titular, tipo o estado',
            'filtros' => '#filtrosSolicitudes',
            'ocultos' => ['estado' => (string) ($filters['estado'] ?? ''), 'tipo' => (string) ($filters['tipo'] ?? '')],
        ]) ?>
    </div>
    <div id="solicitudesResultados" class="ds-listado-wrap">
        <?= $view->include('lopdp/solicitudes/_listado', ['solicitudes' => $solicitudes, 'paginacion' => $paginacion, 'filters' => $filters]) ?>
    </div>
</div>
<?php $view->endSection() ?>
