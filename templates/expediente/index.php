<?php
/**
 * Listado de expedientes con paginacion y filtros.
 *
 * Variables: $user, $csrfToken, $currentPath, $ruta, $expedientes, $paginacion (Paginacion), $filters, $mostrarBloqueB
 * Opcionales: $titulo (string)
 */

use DigiSecure\Expediente\Domain\Enum\EstadoExpediente;
use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$titulo = $titulo ?? 'Expedientes documentales';
$mostrarBloqueB = (bool) ($mostrarBloqueB ?? false);

/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$ruta = (string) ($ruta ?? '/expedientes');
$conFiltroEstado = $ruta === '/expedientes';
$busqueda = (string) ($filters['busqueda'] ?? '');
$total = $paginacion->total();
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
            <small data-resumen-de="#expedientesResultados" aria-live="polite"><?= $total ?> expediente<?= $total !== 1 ? 's' : '' ?></small>
        </div>
    </div>
</div>

<?php if ($conFiltroEstado): ?>
<!-- Filtros (la busqueda por texto esta en el encabezado del listado) -->
<div class="ds-card ds-op-filter">
    <div class="ds-card-body">
        <div class="ds-filter-bar">
            <form id="filtrosExpedientes" method="get" action="<?= $view->url($ruta) ?>" class="ds-filter-row ds-form-filter">
                <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
                <?php if ($paginacion->parametro() !== null): ?>
                <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
                <?php endif ?>
                <div class="ds-filter-field">
                    <div class="ds-form-field">
                        <label class="ds-form-label" for="filtroExpEstado">Estado</label>
                        <select name="estado" id="filtroExpEstado" class="form-select form-select-sm">
                            <option value="">Todos los estados</option>
                            <?php foreach (EstadoExpediente::cases() as $est): ?>
                            <option value="<?= $est->value ?>" <?= ($filters['estado'] ?? '') === $est->value ? 'selected' : '' ?>><?= $e($est->label()) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
                <div class="ds-filter-actions">
                    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                    <a href="<?= $view->url($ruta) ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar>
                        <i class="bi bi-x-lg"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif ?>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-folder2"></i></span>
        <div>
            <div class="ds-card-title">Listado de expedientes</div>
            <div class="ds-card-subtitle">Control documental por estado y avance</div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarExpedientes',
            'destino' => '#expedientesResultados',
            'parcial' => 'expedientes',
            'ruta' => $ruta,
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar guardia...',
            'ayuda' => 'Buscar expedientes por apellidos, nombres, correlativo, correo o celular del guardia',
            'filtros' => $conFiltroEstado ? '#filtrosExpedientes' : '',
            'ocultos' => $conFiltroEstado ? ['estado' => (string) ($filters['estado'] ?? '')] : [],
        ]) ?>
    </div>
    <div id="expedientesResultados" class="ds-listado-wrap">
        <?= $view->include('expediente/_listado', [
            'expedientes' => $expedientes,
            'paginacion' => $paginacion,
            'filters' => $filters,
            'ruta' => $ruta,
            'mostrarBloqueB' => $mostrarBloqueB,
        ]) ?>
    </div>
</div>
<?php $view->endSection() ?>
