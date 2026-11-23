<?php
/**
 * Listado de asignaciones guardia <-> puesto.
 *
 * Variables: $user, $csrfToken, $currentPath, $asignaciones,
 *            $paginacion (Paginacion), $filters, $kpis, $clientes
 */

use DigiSecure\Asignacion\Domain\Enum\EstadoAsignacion;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$busqueda = (string) ($filters['q'] ?? '');
$bloqueoCreacion = $bloqueoCreacion ?? null;

$today = date('Y-m-d');
$cobertura = $kpis['plazas_total'] > 0
    ? min(100, (int) round($kpis['plazas_cubiertas'] / $kpis['plazas_total'] * 100))
    : 0;
$coberturaTone = $cobertura >= 90 ? 'tone-success' : ($cobertura >= 60 ? 'tone-warning' : 'tone-danger');
?>

<?php $view->section('title') ?>Asignaciones - DigiSecure<?php $view->endSection() ?>

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
<li class="breadcrumb-item active"><i class="bi bi-link-45deg me-1"></i>Asignaciones</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-link-45deg"></i></span>
        <div>
            <h1>Asignaciones</h1>
            <small><?= (int) $kpis['plazas_cubiertas'] ?> de <?= (int) $kpis['plazas_total'] ?> plazas cubiertas (<?= $cobertura ?>%)</small>
        </div>
    </div>
    <?php if ($user->tienePermiso('asignaciones.crear') && $bloqueoCreacion === null): ?>
    <a href="<?= $view->url('/asignaciones/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nueva asignación
    </a>
    <?php elseif ($bloqueoCreacion !== null): ?>
    <button type="button" class="ds-btn ds-btn-primary" disabled title="<?= $e($bloqueoCreacion['mensaje']) ?>">
        <i class="bi bi-plus-lg"></i>Nueva asignación
    </button>
    <?php endif ?>
</div>

<?php if ($bloqueoCreacion !== null): ?>
<div class="ds-callout tone-info" role="status">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Aún no se pueden crear asignaciones</strong>
        <?= $e($bloqueoCreacion['mensaje']) ?>
        <a href="<?= $view->url($bloqueoCreacion['url']) ?>"><?= $e($bloqueoCreacion['accion']) ?></a>
    </div>
</div>
<?php endif ?>

<!-- KPIs -->
<div class="ds-kpi-grid ds-kpi-grid-solid">
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-check2-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Activas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['activas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card <?= $coberturaTone ?>">
        <span class="ds-kpi-icon"><i class="bi bi-pie-chart-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Cobertura de plazas</div>
            <div class="ds-kpi-value"><?= $cobertura ?>%</div>
            <div class="ds-cell-sub" style="margin-top:.25rem;"><?= (int) $kpis['plazas_cubiertas'] ?> / <?= (int) $kpis['plazas_total'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-clock-history"></i></span>
        <div>
            <div class="ds-kpi-label">Por vencer (14d)</div>
            <div class="ds-kpi-value"><?= (int) $kpis['por_vencer'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-muted">
        <span class="ds-kpi-icon"><i class="bi bi-archive"></i></span>
        <div>
            <div class="ds-kpi-label">Finalizadas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['finalizadas'] ?></div>
        </div>
    </div>
</div>

<!-- Filtros (la busqueda por texto esta en el encabezado del listado) -->
<div class="ds-card ds-op-filter">
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form id="filtrosAsignaciones" method="get" action="<?= $view->url('/asignaciones') ?>" class="ds-filter-row ds-form-filter">
                <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
                <?php if ($paginacion->parametro() !== null): ?>
                <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
                <?php endif ?>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroAsigCliente">Cliente</label>
                        <select name="cliente_id" id="filtroAsigCliente" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $e($cl['id']) ?>" <?= ($filters['cliente_id'] ?? '') === $cl['id'] ? 'selected' : '' ?>><?= $e($cl['razon_social']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroAsigEstado">Estado</label>
                        <select name="estado" id="filtroAsigEstado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (EstadoAsignacion::cases() as $est): ?>
                            <option value="<?= $est->value ?>" <?= ($filters['estado'] ?? '') === $est->value ? 'selected' : '' ?>><?= $e($est->label()) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <?php foreach (['puesto_id', 'ficha_id'] as $oculto): if (!empty($filters[$oculto])): ?>
                <input type="hidden" name="<?= $oculto ?>" value="<?= $e((string) $filters[$oculto]) ?>">
                <?php endif; endforeach ?>
                <div class="ds-filter-actions">
                    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                    <a href="<?= $view->url('/asignaciones') ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar>
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-link-45deg"></i></span>
        <div>
            <div class="ds-card-title">Listado de asignaciones</div>
            <div class="ds-card-subtitle">Relacion guardia - puesto con periodo y estado operativo</div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarAsignaciones',
            'destino' => '#asignacionesResultados',
            'parcial' => 'asignaciones',
            'ruta' => '/asignaciones',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar asignacion...',
            'ayuda' => 'Buscar asignaciones por guardia, correlativo, puesto o cliente',
            'filtros' => '#filtrosAsignaciones',
            'ocultos' => [
                'cliente_id' => (string) ($filters['cliente_id'] ?? ''),
                'estado' => (string) ($filters['estado'] ?? ''),
                'puesto_id' => (string) ($filters['puesto_id'] ?? ''),
                'ficha_id' => (string) ($filters['ficha_id'] ?? ''),
            ],
        ]) ?>
    </div>
    <div id="asignacionesResultados" class="ds-listado-wrap">
        <?= $view->include('asignaciones/_listado', ['user' => $user, 'asignaciones' => $asignaciones, 'paginacion' => $paginacion, 'filters' => $filters]) ?>
    </div>
</div>
<?php $view->endSection() ?>

