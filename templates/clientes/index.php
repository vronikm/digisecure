<?php
/**
 * Listado de clientes del tenant.
 *
 * Variables: $user, $csrfToken, $currentPath, $clientes,
 *            $paginacion (Paginacion), $filters, $kpis
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$busqueda = (string) ($filters['q'] ?? '');
?>

<?php $view->section('title') ?>Clientes - DigiSecure<?php $view->endSection() ?>

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

<!--?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-briefcase me-1"></i>Clientes</li-->
<!--?php $view->endSection() ?-->

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-briefcase-fill"></i></span>
        <div>
            <h1>Clientes</h1>
            <small><?= (int) $kpis['total'] ?> registrado<?= (int) $kpis['total'] !== 1 ? 's' : '' ?> en el sistema</small>
        </div>
    </div>
    <?php if ($user->tienePermiso('clientes.crear')): ?>
    <a href="<?= $view->url('/clientes/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nuevo cliente
    </a>
    <?php endif ?>
</div>

<!-- KPIs -->
<div class="ds-kpi-grid ds-kpi-grid-4 ds-kpi-grid-solid">
    <div class="ds-kpi-card">
        <span class="ds-kpi-icon"><i class="bi bi-collection"></i></span>
        <div>
            <div class="ds-kpi-label">Total</div>
            <div class="ds-kpi-value"><?= (int) $kpis['total'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-check2-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Activos</div>
            <div class="ds-kpi-value"><?= (int) $kpis['activos'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-pause-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Inactivos</div>
            <div class="ds-kpi-value"><?= (int) $kpis['inactivos'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-muted">
        <span class="ds-kpi-icon"><i class="bi bi-archive"></i></span>
        <div>
            <div class="ds-kpi-label">Archivados</div>
            <div class="ds-kpi-value"><?= (int) $kpis['archivados'] ?></div>
        </div>
    </div>
</div>

<!-- Filtros (la busqueda por texto esta en el encabezado del listado) -->
<div class="ds-card ds-op-filter">
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form id="filtrosClientes" method="get" action="<?= $view->url('/clientes') ?>" class="ds-filter-row ds-form-filter">
                <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
                <?php if ($paginacion->parametro() !== null): ?>
                <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
                <?php endif ?>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroClientesEstado">Estado</label>
                        <select name="estado" id="filtroClientesEstado" class="form-select form-select-sm">
                            <option value="">Activos e inactivos</option>
                            <?php foreach (['activo', 'inactivo', 'archivado'] as $est): ?>
                            <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>><?= ucfirst($est) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                    <a href="<?= $view->url('/clientes') ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar>
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-briefcase"></i></span>
        <div>
            <div class="ds-card-title">Listado de clientes</div>
            <div class="ds-card-subtitle">Empresas cliente con estado y contacto principal</div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarClientes',
            'destino' => '#clientesResultados',
            'parcial' => 'clientes',
            'ruta' => '/clientes',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar cliente...',
            'ayuda' => 'Buscar clientes por razon social, nombre comercial, RUC o cedula, contacto, correo, ciudad o sector',
            'filtros' => '#filtrosClientes',
            'ocultos' => ['estado' => (string) ($filters['estado'] ?? '')],
        ]) ?>
    </div>
    <div id="clientesResultados" class="ds-listado-wrap">
        <?= $view->include('clientes/_listado', ['user' => $user, 'clientes' => $clientes, 'paginacion' => $paginacion, 'filters' => $filters]) ?>
    </div>
</div>
<?php $view->endSection() ?>

