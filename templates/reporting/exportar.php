<?php
/**
 * Pantalla de exportación de datos.
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
        <p class="text-muted mb-0">Descargue reportes en formato CSV o PDF</p>
    </div>
    <a href="<?= $view->url('/reportes/kpis') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Volver a KPIs
    </a>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center py-5">
                <i class="bi bi-filetype-csv fs-1 text-success d-block mb-3"></i>
                <h5>Listado de Fichas</h5>
                <p class="text-muted">Exporta todas las fichas del tenant con datos principales.</p>
                <button class="btn btn-outline-success" disabled>
                    <i class="bi bi-download me-1"></i>Descargar CSV
                </button>
                <div class="text-muted small mt-2">Disponible en próxima fase</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center py-5">
                <i class="bi bi-filetype-pdf fs-1 text-danger d-block mb-3"></i>
                <h5>Reporte de Expedientes</h5>
                <p class="text-muted">Resumen del estado documental por guardia.</p>
                <button class="btn btn-outline-danger" disabled>
                    <i class="bi bi-download me-1"></i>Descargar PDF
                </button>
                <div class="text-muted small mt-2">Disponible en próxima fase</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center py-5">
                <i class="bi bi-calendar-x fs-1 text-warning d-block mb-3"></i>
                <h5>Documentos por Vencer</h5>
                <p class="text-muted">Lista de documentos con vencimiento próximo (30/60/90 días).</p>
                <button class="btn btn-outline-warning" disabled>
                    <i class="bi bi-download me-1"></i>Descargar CSV
                </button>
                <div class="text-muted small mt-2">Disponible en próxima fase</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center py-5">
                <i class="bi bi-journal-text fs-1 text-primary d-block mb-3"></i>
                <h5>Log de Auditoría</h5>
                <p class="text-muted">Bitácora completa de operaciones (LOPDP).</p>
                <button class="btn btn-outline-primary" disabled>
                    <i class="bi bi-download me-1"></i>Descargar CSV
                </button>
                <div class="text-muted small mt-2">Disponible en próxima fase</div>
            </div>
        </div>
    </div>
</div>
<?php $view->endSection() ?>
