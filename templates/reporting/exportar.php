<?php
/**
 * Hub de exportacion de reportes (Fase 10).
 *
 * Variables: $user, $csrfToken, $currentPath
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Exportar - DigiSecure<?php $view->endSection() ?>

<?php $view->section('head') ?>
<style>
    .ds-export-card .ds-card-body {
        display: flex;
        flex-direction: column;
        gap: .8rem;
        height: 100%;
    }

    .ds-export-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
    }

    .ds-export-meta .ds-chip {
        font-size: .72rem;
        padding: .22rem .55rem;
    }

    .ds-export-actions {
        margin-top: auto;
    }
</style>
<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/reportes/kpis') ?>">KPIs</a></li>
<li class="breadcrumb-item active">Exportar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-file-earmark-arrow-down"></i></span>
        <div>
            <h1>Exportar datos</h1>
            <small>Descarga de reportes CSV con trazabilidad en auditoria</small>
        </div>
    </div>
    <a href="<?= $view->url('/reportes/kpis') ?>" class="ds-btn ds-btn-ghost ds-btn-sm">
        <i class="bi bi-arrow-left"></i>Volver a KPIs
    </a>
</div>

<div class="login-info" style="margin-bottom:1.25rem;">
    <i class="bi bi-info-circle"></i>
    <div>
        <strong>Compatibilidad con Excel</strong>
        Los CSV se generan con BOM UTF-8 y separador <code>;</code> para abrirse directamente.
        Cada archivo incluye fecha y hora para evitar sobrescrituras.
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6 col-xl-3">
        <div class="ds-card ds-export-card h-100">
            <div class="ds-card-header tone-success">
                <span class="ds-card-mark"><i class="bi bi-people-fill"></i></span>
                <div>
                    <div class="ds-card-title">Listado de fichas</div>
                    <div class="ds-card-subtitle">Base principal de guardias</div>
                </div>
            </div>
            <div class="ds-card-body">
                <p class="ds-cell-sub">Correlativo, datos de identificacion, contacto, estado y fechas de registro.</p>
                <div class="ds-export-meta">
                    <span class="ds-chip">CSV</span>
                    <span class="ds-chip">UTF-8</span>
                    <span class="ds-chip">17 columnas</span>
                </div>
                <div class="ds-export-actions">
                    <a href="<?= $view->url('/reportes/exportar/fichas') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-download"></i>Descargar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="ds-card ds-export-card h-100">
            <div class="ds-card-header tone-info">
                <span class="ds-card-mark"><i class="bi bi-folder2-open"></i></span>
                <div>
                    <div class="ds-card-title">Resumen de expedientes</div>
                    <div class="ds-card-subtitle">Avance documental por guardia</div>
                </div>
            </div>
            <div class="ds-card-body">
                <p class="ds-cell-sub">Incluye bloques A/B y conteos de documentos aceptados, en revision y rechazados.</p>
                <div class="ds-export-meta">
                    <span class="ds-chip">CSV</span>
                    <span class="ds-chip">UTF-8</span>
                    <span class="ds-chip">14 columnas</span>
                </div>
                <div class="ds-export-actions">
                    <a href="<?= $view->url('/reportes/exportar/expedientes') ?>" class="ds-btn ds-btn-primary ds-btn-sm">
                        <i class="bi bi-download"></i>Descargar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="ds-card ds-export-card h-100">
            <div class="ds-card-header tone-warning">
                <span class="ds-card-mark"><i class="bi bi-calendar-x"></i></span>
                <div>
                    <div class="ds-card-title">Documentos por vencer</div>
                    <div class="ds-card-subtitle">Alertas por ventana de tiempo</div>
                </div>
            </div>
            <div class="ds-card-body">
                <p class="ds-cell-sub">Listado de proximos vencimientos con dias restantes para seguimiento preventivo.</p>
                <div class="ds-export-meta">
                    <span class="ds-chip">CSV</span>
                    <span class="ds-chip">12 columnas</span>
                </div>
                <div class="ds-export-actions ds-row-actions">
                    <a href="<?= $view->url('/reportes/exportar/vencimientos?dias=30') ?>" class="ds-btn ds-btn-outline ds-btn-sm">30d</a>
                    <a href="<?= $view->url('/reportes/exportar/vencimientos?dias=60') ?>" class="ds-btn ds-btn-outline ds-btn-sm">60d</a>
                    <a href="<?= $view->url('/reportes/exportar/vencimientos?dias=90') ?>" class="ds-btn ds-btn-outline ds-btn-sm">90d</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="ds-card ds-export-card h-100">
            <div class="ds-card-header tone-purple">
                <span class="ds-card-mark"><i class="bi bi-journal-text"></i></span>
                <div>
                    <div class="ds-card-title">Auditoria</div>
                    <div class="ds-card-subtitle">Eventos sensibles del sistema</div>
                </div>
            </div>
            <div class="ds-card-body">
                <p class="ds-cell-sub">Bitacora completa de operaciones con filtros por fecha, nivel y evento.</p>
                <div class="ds-export-meta">
                    <span class="ds-chip">CSV filtrable</span>
                </div>
                <div class="ds-export-actions">
                    <a href="<?= $view->url('/auditoria') ?>" class="ds-btn ds-btn-outline ds-btn-sm">
                        <i class="bi bi-arrow-up-right-square"></i>Ir al visor
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
