<?php
/**
 * Listado de guardias (un registro por persona) con busqueda en vivo y
 * paginacion configurable.
 *
 * La tabla vive en ficha/_listado.php. El formulario del encabezado
 * (#formGuardias) busca en TODOS los guardias del servidor mientras se
 * escribe (ds-tabla-remota.js) y elige cuantos registros mostrar. Sin
 * JavaScript sigue funcionando como un formulario GET normal.
 *
 * Variables: $user, $csrfToken, $currentPath, $guardias,
 *            $paginacion (Paginacion), $filters, $listError
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
$view->layout('layout/app');
$e = ViewRenderer::e(...);
$listError = (bool) ($listError ?? false);
$busqueda = (string) ($filters['busqueda'] ?? '');
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$totalInt = $paginacion->total();
$resumen = $totalInt . ' guardia' . ($totalInt !== 1 ? 's' : '') . ($busqueda !== '' ? ' encontrado' . ($totalInt !== 1 ? 's' : '') : ' registrado' . ($totalInt !== 1 ? 's' : ''));
?>

<?php $view->section('title') ?>Guardias - DigiSecure<?php $view->endSection() ?>

<?php $view->section('breadcrumb') ?>
<li class="breadcrumb-item active">Guardias</li>
<?php $view->endSection() ?>

<?php $view->section('content') ?>
<div class="ds-page-header">
    <div class="ds-page-title">
        <span class="ds-page-mark"><i class="bi bi-person-badge"></i></span>
        <div>
            <h1>Guardias</h1>
            <small data-resumen-de="#guardiasResultados" aria-live="polite"><?= $e($resumen) ?></small>
        </div>
    </div>
    <?php if ($user->tienePermiso('guardias.crear')): ?>
    <a href="<?= $view->url('/guardias/crear') ?>" class="ds-btn ds-btn-primary">
        <i class="bi bi-plus-lg"></i>Nueva ficha
    </a>
    <?php endif ?>
</div>

<?php if ($listError): ?>
<div class="ds-callout tone-warning" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>No se pudo cargar el listado de guardias. Puedes continuar navegando mientras soporte revisa el log del servidor.</div>
</div>
<?php endif; ?>

<?php $aspEnProceso = (int) ($aspirantesEnProceso ?? 0); ?>
<?php if ($aspEnProceso > 0): ?>
<div class="ds-callout tone-info ds-callout-accion" role="status">
    <i class="bi bi-person-vcard"></i>
    <div>
        <strong><?= $aspEnProceso ?> aspirante<?= $aspEnProceso !== 1 ? 's' : '' ?> en proceso de contratación</strong>
        Los aspirantes no aparecen aquí hasta que se los contrata. Revisa su ficha y sus documentos desde Reclutamiento.
    </div>
    <a href="<?= $view->url('/reclutamiento') ?>" class="ds-btn ds-btn-outline ds-btn-sm">
        <i class="bi bi-clipboard-check"></i>Ir a Reclutamiento
    </a>
</div>
<?php endif ?>

<div class="ds-card">
    <div class="ds-card-header tone-purple">
        <span class="ds-card-mark"><i class="bi bi-people"></i></span>
        <div>
            <div class="ds-card-title">Listado de guardias</div>
            <div class="ds-card-subtitle">Empleados contratados: un registro por persona con acceso a su historial de fichas</div>
        </div>
        <?= $view->include('partials/tabla-toolbar', [
            'id' => 'formGuardias',
            'destino' => '#guardiasResultados',
            'parcial' => 'guardias',
            'ruta' => '/guardias',
            'paginacion' => $paginacion,
            'busqueda' => $busqueda,
            'placeholder' => 'Buscar guardia...',
            'ayuda' => 'Buscar guardias por apellido, nombre, celular, correo o cedula completa (10 digitos)',
        ]) ?>
    </div>

    <div id="guardiasResultados" class="ds-listado-wrap">
        <?= $view->include('ficha/_listado', [
            'guardias' => $guardias,
            'paginacion' => $paginacion,
            'filters' => $filters,
            'aspirantesEnProceso' => $aspirantesEnProceso ?? 0,
        ]) ?>
    </div>
</div>
<?php $view->endSection() ?>

<?php $view->section('scripts') ?>
<script>
// Clic en cualquier parte de la fila abre el historial del guardia. Delegado en
// el contenedor: las filas se reemplazan al buscar o paginar.
document.getElementById('guardiasResultados')?.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element) || target.closest('a,button,form,input,select,textarea,label')) return;
    const row = target.closest('tr.js-row-link');
    if (row?.dataset.href) window.location.href = row.dataset.href;
});
</script>
<?php $view->endSection() ?>
