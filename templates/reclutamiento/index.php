<?php
/**
 * Panel de reclutamiento: listado de aspirantes (Fase 4).
 *
 * Variables: $user, $csrfToken, $currentPath, $aspirantes,
 *            $paginacion (Paginacion), $filters, $kpis, $estadosEditables
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);

['label' => $estadoLabel] = require __DIR__ . '/_estados.php';

/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$busqueda = (string) ($filters['q'] ?? '');
?>

<?php $view->section('title') ?>Reclutamiento - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active"><i class="bi bi-person-plus-fill me-1"></i>Reclutamiento</li>
<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-reclutamiento-filter .ds-filter-row {
        display: grid;
        grid-template-columns: minmax(280px, 1fr) minmax(150px, 180px) minmax(150px, 180px) auto;
        gap: .75rem;
        align-items: end;
    }

    .ds-reclutamiento-filter .ds-filter-field {
        min-width: 0;
    }

    .ds-reclutamiento-filter .ds-filter-actions {
        display: flex;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: nowrap;
        white-space: nowrap;
        padding-bottom: .05rem;
    }

    @media (max-width: 991.98px) {
        .ds-reclutamiento-filter .ds-filter-row {
            grid-template-columns: 1fr 1fr;
        }

        .ds-reclutamiento-filter .ds-filter-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 575.98px) {
        .ds-reclutamiento-filter .ds-filter-row {
            grid-template-columns: 1fr;
        }
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-person-plus-fill"></i></span>
        <div>
            <h1>Reclutamiento</h1>
            <small><?= (int) $kpis['total'] ?> postulante<?= (int) $kpis['total'] !== 1 ? 's' : '' ?> registrado<?= (int) $kpis['total'] !== 1 ? 's' : '' ?></small>
        </div>
    </div>
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
    <div class="ds-kpi-card tone-warning">
        <span class="ds-kpi-icon"><i class="bi bi-envelope-exclamation"></i></span>
        <div>
            <div class="ds-kpi-label">Sin verificar</div>
            <div class="ds-kpi-value"><?= (int) $kpis['pendientes'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-info">
        <span class="ds-kpi-icon"><i class="bi bi-clipboard-check"></i></span>
        <div>
            <div class="ds-kpi-label">Por revisar</div>
            <div class="ds-kpi-value"><?= (int) $kpis['verificados'] ?></div>
        </div>
    </div>
    <div class="ds-kpi-card tone-success">
        <span class="ds-kpi-icon"><i class="bi bi-person-check"></i></span>
        <div>
            <div class="ds-kpi-label">Contratados</div>
            <div class="ds-kpi-value"><?= (int) ($kpis['contratados'] ?? $kpis['aprobados']) ?></div>
        </div>
    </div>
</div>

<!-- Filtros (la busqueda por texto esta en el encabezado del listado) -->
<div class="ds-card ds-reclutamiento-filter" data-collapsible-mobile>
    <div class="ds-card-header tone-success">
        <span class="ds-card-mark"><i class="bi bi-funnel"></i></span>
        <div>
            <div class="ds-card-title">Filtros</div>
            <div class="ds-card-subtitle">Estado y verificación del correo</div>
        </div>
    </div>
    <div class="ds-card-body">
        <form id="filtrosReclutamiento" method="get" action="<?= $view->url('/reclutamiento') ?>" class="ds-filter-row ds-form-filter">
            <input type="hidden" name="q" value="<?= $e($busqueda) ?>">
            <?php if ($paginacion->parametro() !== null): ?>
            <input type="hidden" name="por_pagina" value="<?= $e((string) $paginacion->parametro()) ?>">
            <?php endif ?>
            <div class="ds-filter-field">
                <div class="ds-form-field">
                    <label class="ds-form-label" for="filtroReclEstado">Estado</label>
                    <select name="estado" id="filtroReclEstado" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <?php foreach ($estadoLabel as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($filters['estado'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="ds-filter-field">
                <div class="ds-form-field">
                    <label class="ds-form-label" for="filtroReclVerificado">Verificación</label>
                    <select name="verificado" id="filtroReclVerificado" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="1" <?= (string) ($filters['verificado'] ?? '') === '1' ? 'selected' : '' ?>>Verificado</option>
                        <option value="0" <?= (string) ($filters['verificado'] ?? '') === '0' ? 'selected' : '' ?>>Sin verificar</option>
                    </select>
                </div>
            </div>
            <div class="ds-filter-actions">
                <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm"><i class="bi bi-funnel"></i>Aplicar</button></noscript>
                <a href="<?= $view->url('/reclutamiento') ?>" class="ds-btn ds-btn-ghost ds-btn-sm" data-remota-limpiar><i class="bi bi-x-lg"></i>Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-person-vcard"></i></span>
        <div>
            <div class="ds-card-title">Aspirantes</div>
            <div class="ds-card-subtitle">Postulantes y su estado de revisión</div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'buscarReclutamiento',
            'destino' => '#reclutamientoResultados',
            'parcial' => 'reclutamiento',
            'ruta' => '/reclutamiento',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar aspirante...',
            'ayuda' => 'Buscar aspirantes por nombres, apellidos, documento, correo o celular',
            'filtros' => '#filtrosReclutamiento',
            'ocultos' => [
                'estado' => (string) ($filters['estado'] ?? ''),
                'verificado' => (string) ($filters['verificado'] ?? ''),
            ],
        ]) ?>
    </div>
    <div id="reclutamientoResultados" class="ds-listado-wrap">
        <?= $view->include('reclutamiento/_listado', [
            'user' => $user,
            'aspirantes' => $aspirantes,
            'paginacion' => $paginacion,
            'filters' => $filters,
            'estadosEditables' => $estadosEditables,
        ]) ?>
    </div>
</div>
<?php $view->endSection() ?>
