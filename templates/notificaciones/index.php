<?php
/**
 * Listado de notificaciones del tenant.
 *
 * Variables: $user, $csrfToken, $currentPath, $notificaciones,
 *            $paginacion (Paginacion), $filters, $kpis
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$busqueda = (string) ($filters['q'] ?? '');
$total = $paginacion->total();
?>

<?php $view->section('title') ?>Notificaciones - DigiSecure<?php $view->endSection() ?>

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
<li class="breadcrumb-item active"><i class="bi bi-bell me-1"></i>Notificaciones</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-bell-fill"></i></span>
        <div>
            <h1>Notificaciones</h1>
            <small data-resumen-de="#notificacionesResultados" aria-live="polite"><?= $total ?> notificacion<?= $total !== 1 ? 'es' : '' ?> en total</small>
        </div>
    </div>
</div>

<div class="ds-kpi-grid ds-kpi-grid-solid">
    <div class="ds-kpi-card tone-muted">
        <span class="ds-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
        <div>
            <div class="ds-kpi-label">Pendientes</div>
            <div class="ds-kpi-value"><?= (int) $kpis['pendientes'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-check2-circle"></i></span>
        <div>
            <div class="ds-kpi-label">Enviadas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['enviadas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-danger">
        <span class="ds-kpi-icon"><i class="bi bi-x-octagon-fill"></i></span>
        <div>
            <div class="ds-kpi-label">Fallidas</div>
            <div class="ds-kpi-value"><?= (int) $kpis['fallidas'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-info">
        <span class="ds-kpi-icon"><i class="bi bi-collection"></i></span>
        <div>
            <div class="ds-kpi-label">Total</div>
            <div class="ds-kpi-value"><?= (int) $kpis['total'] ?></div>
        </div>
    </div>
</div>

<div class="ds-card ds-op-filter" data-collapsible-mobile>
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros de notificaciones</div>
            <div class="ds-card-subtitle">Estado, canal y tipo de evento</div>
        </div>
    </div>
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form id="filtrosNotificaciones" method="get" action="<?= $view->url('/notificaciones') ?>" class="ds-filter-row ds-form-filter">
                <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
                <?php if ($paginacion->parametro() !== null): ?>
                <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
                <?php endif ?>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroNotifEstado">Estado</label>
                        <select name="estado" id="filtroNotifEstado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (['PENDIENTE', 'ENVIADA', 'FALLIDA', 'CANCELADA'] as $est): ?>
                            <option value="<?= $est ?>" <?= ($filters['estado'] ?? '') === $est ? 'selected' : '' ?>><?= ucfirst(strtolower($est)) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroNotifCanal">Canal</label>
                        <select name="canal" id="filtroNotifCanal" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach (['EMAIL', 'WHATSAPP', 'SMS'] as $canal): ?>
                            <option value="<?= $canal ?>" <?= ($filters['canal'] ?? '') === $canal ? 'selected' : '' ?>><?= $canal ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroNotifEvento">Evento</label>
                        <input type="text" name="evento" id="filtroNotifEvento" class="form-control form-control-sm"
                               value="<?= $e($filters['evento'] ?? '') ?>" placeholder="Ej: doc.vencido" autocomplete="off">
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                    <a href="<?= $view->url('/notificaciones') ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar>
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-send"></i></span>
        <div>
            <div class="ds-card-title">Listado de notificaciones</div>
            <div class="ds-card-subtitle">Historial de mensajes generados por eventos del sistema</div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarNotificaciones',
            'destino' => '#notificacionesResultados',
            'parcial' => 'notificaciones',
            'ruta' => '/notificaciones',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar mensaje...',
            'ayuda' => 'Buscar notificaciones por asunto, destinatario, evento, canal o tipo de entidad',
            'filtros' => '#filtrosNotificaciones',
            'ocultos' => array_diff_key($filters, ['q' => true]),
        ]) ?>
    </div>
    <div id="notificacionesResultados" class="ds-listado-wrap">
        <?= $view->include('notificaciones/_listado', ['notificaciones' => $notificaciones, 'paginacion' => $paginacion, 'filters' => $filters]) ?>
    </div>
</div>
<?php $view->endSection() ?>
