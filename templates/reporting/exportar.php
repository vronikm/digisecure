<?php
/**
 * Hub de exportación de reportes (Fase 10).
 *
 * Variables: $user, $csrfToken, $currentPath
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
?>

<?php $view->section('title') ?>Exportar — DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= $view->url('/reportes/kpis') ?>">KPIs</a></li>
<li class="breadcrumb-item active">Exportar</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-file-earmark-arrow-down me-2"></i>Exportar Datos</h4>
        <p class="text-muted mb-0">Descarga reportes en formato CSV. Las exportaciones quedan registradas en auditoría.</p>
    </div>
    <a href="<?= $view->url('/reportes/kpis') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver a KPIs
    </a>
</div>

<div class="alert alert-info d-flex align-items-start mb-4" role="alert">
    <i class="bi bi-info-circle-fill fs-4 me-3 mt-1"></i>
    <div>
        <strong>Compatibilidad con Excel:</strong> los CSV se generan con BOM UTF-8 y separador <code>;</code>
        para que Excel los abra directamente sin necesidad de importación manual. Los archivos se nombran
        con fecha y hora para evitar sobrescrituras.
    </div>
</div>

<div class="row g-4">
    <!-- Fichas -->
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-success-subtle">
            <div class="card-body d-flex flex-column">
                <div class="text-center mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success" style="width:72px;height:72px">
                        <i class="bi bi-people-fill fs-1"></i>
                    </div>
                </div>
                <h5 class="text-center mb-2">Listado de Fichas</h5>
                <p class="text-muted small text-center flex-grow-1">
                    Datos principales de todas las fichas del tenant: correlativo, nombres,
                    contacto, estado, fechas de creación.
                </p>
                <div class="d-flex justify-content-center gap-1 mb-3">
                    <span class="badge bg-light text-dark border">CSV</span>
                    <span class="badge bg-light text-dark border">UTF-8</span>
                    <span class="badge bg-light text-dark border">17 columnas</span>
                </div>
                <a href="<?= $view->url('/reportes/exportar/fichas') ?>" class="btn btn-success">
                    <i class="bi bi-download me-1"></i>Descargar CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Expedientes -->
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-primary-subtle">
            <div class="card-body d-flex flex-column">
                <div class="text-center mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width:72px;height:72px">
                        <i class="bi bi-folder2-open fs-1"></i>
                    </div>
                </div>
                <h5 class="text-center mb-2">Resumen de Expedientes</h5>
                <p class="text-muted small text-center flex-grow-1">
                    Estado documental por guardia: bloques A/B, conteo de documentos
                    aceptados, en revisión, rechazados y vencidos.
                </p>
                <div class="d-flex justify-content-center gap-1 mb-3">
                    <span class="badge bg-light text-dark border">CSV</span>
                    <span class="badge bg-light text-dark border">UTF-8</span>
                    <span class="badge bg-light text-dark border">14 columnas</span>
                </div>
                <a href="<?= $view->url('/reportes/exportar/expedientes') ?>" class="btn btn-primary">
                    <i class="bi bi-download me-1"></i>Descargar CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Vencimientos -->
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-warning-subtle">
            <div class="card-body d-flex flex-column">
                <div class="text-center mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning-emphasis" style="width:72px;height:72px">
                        <i class="bi bi-calendar-x fs-1"></i>
                    </div>
                </div>
                <h5 class="text-center mb-2">Documentos por Vencer</h5>
                <p class="text-muted small text-center flex-grow-1">
                    Lista de documentos con vencimiento próximo, con días restantes calculados.
                    Elige la ventana de alerta:
                </p>
                <div class="d-flex justify-content-center gap-1 mb-3">
                    <span class="badge bg-light text-dark border">CSV</span>
                    <span class="badge bg-light text-dark border">12 columnas</span>
                </div>
                <div class="btn-group">
                    <a href="<?= $view->url('/reportes/exportar/vencimientos?dias=30') ?>" class="btn btn-outline-warning">30d</a>
                    <a href="<?= $view->url('/reportes/exportar/vencimientos?dias=60') ?>" class="btn btn-outline-warning">60d</a>
                    <a href="<?= $view->url('/reportes/exportar/vencimientos?dias=90') ?>" class="btn btn-outline-warning">90d</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Auditoría (link al visor existente) -->
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-secondary-subtle">
            <div class="card-body d-flex flex-column">
                <div class="text-center mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-secondary-subtle text-secondary" style="width:72px;height:72px">
                        <i class="bi bi-journal-text fs-1"></i>
                    </div>
                </div>
                <h5 class="text-center mb-2">Log de Auditoría</h5>
                <p class="text-muted small text-center flex-grow-1">
                    Bitácora completa de operaciones sensibles (LOPDP). Disponible con filtros
                    de fecha, evento y nivel desde el visor de auditoría.
                </p>
                <div class="d-flex justify-content-center gap-1 mb-3">
                    <span class="badge bg-light text-dark border">CSV filtrable</span>
                </div>
                <a href="<?= $view->url('/auditoria') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-up-right-square me-1"></i>Ir al visor de auditoría
                </a>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
