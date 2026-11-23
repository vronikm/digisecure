<?php
/**
 * Listado de puestos (sitios donde el tenant presta servicios).
 *
 * Variables: $user, $csrfToken, $currentPath, $puestos,
 *            $paginacion (Paginacion), $filters, $kpis, $clientes, $tiposPuesto
 */

use DigiSecure\Kernel\View\ViewRenderer;
use DigiSecure\Puesto\Domain\Enum\TipoPuesto;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$busqueda = (string) ($filters['q'] ?? '');
?>

<?php $view->section('title') ?>Puestos - DigiSecure<?php $view->endSection() ?>

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
<li class="breadcrumb-item active"><i class="bi bi-geo-alt me-1"></i>Puestos</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-geo-alt-fill"></i></span>
        <div>
            <h1>Puestos / Sitios</h1>
            <small><?= (int) $kpis['total'] ?> registrado<?= (int) $kpis['total'] !== 1 ? 's' : '' ?> &middot; <?= (int) $kpis['plazas'] ?> plaza<?= (int) $kpis['plazas'] !== 1 ? 's' : '' ?> activa<?= (int) $kpis['plazas'] !== 1 ? 's' : '' ?></small>
        </div>
    </div>
    <?php if ($user->tienePermiso('puestos.crear')): ?>
    <a href="<?= $view->url('/puestos/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nuevo puesto
    </a>
    <?php endif ?>
</div>

<!-- KPIs -->
<div class="ds-kpi-grid ds-kpi-grid-solid">
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
    <div class="ds-kpi-card tone-purple">
        <span class="ds-kpi-icon"><i class="bi bi-people-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Plazas activas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['plazas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-pause-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Inactivos</div>
            <div class="ds-kpi-value"><?= (int) $kpis['inactivos'] ?></div>
        </div>
    </div>
</div>

<!-- Filtros (la busqueda por texto esta en el encabezado del listado) -->
<div class="ds-card ds-op-filter">
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form id="filtrosPuestos" method="get" action="<?= $view->url('/puestos') ?>" class="ds-filter-row ds-form-filter">
                <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
                <?php if ($paginacion->parametro() !== null): ?>
                <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
                <?php endif ?>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroPuestosCliente">Cliente</label>
                        <select name="cliente_id" id="filtroPuestosCliente" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $e($cl['id']) ?>" <?= ($filters['cliente_id'] ?? '') === $cl['id'] ? 'selected' : '' ?>><?= $e($cl['razon_social']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroPuestosTipo">Tipo</label>
                        <select name="tipo" id="filtroPuestosTipo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($tiposPuesto as $tp): ?>
                            <option value="<?= $tp->value ?>" <?= ($filters['tipo'] ?? '') === $tp->value ? 'selected' : '' ?>><?= $e($tp->label()) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroPuestosEstado">Estado</label>
                        <select name="estado" id="filtroPuestosEstado" class="form-select form-select-sm">
                            <option value="">Activos e inactivos</option>
                            <?php foreach (['activo', 'inactivo', 'archivado'] as $est): ?>
                            <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>><?= ucfirst($est) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                    <a href="<?= $view->url('/puestos') ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar>
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-geo-alt"></i></span>
        <div>
            <div class="ds-card-title">Listado de puestos</div>
            <div class="ds-card-subtitle">Sitios operativos con cliente asociado y plazas requeridas</div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarPuestos',
            'destino' => '#puestosResultados',
            'parcial' => 'puestos',
            'ruta' => '/puestos',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar puesto...',
            'ayuda' => 'Buscar puestos por nombre, codigo, cliente, direccion, ciudad, tipo o modalidad',
            'filtros' => '#filtrosPuestos',
            'ocultos' => [
                'cliente_id' => (string) ($filters['cliente_id'] ?? ''),
                'tipo' => (string) ($filters['tipo'] ?? ''),
                'estado' => (string) ($filters['estado'] ?? ''),
            ],
        ]) ?>
    </div>
    <div id="puestosResultados" class="ds-listado-wrap">
        <?= $view->include('puestos/_listado', ['user' => $user, 'puestos' => $puestos, 'paginacion' => $paginacion, 'filters' => $filters]) ?>
    </div>
</div>
<?php $view->endSection() ?>

