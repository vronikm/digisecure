<?php
/**
 * Estado vacio cuando una busqueda o filtro no encuentra registros.
 *
 * Variables:
 *   $busqueda    texto buscado ('' si solo hay filtros)
 *   $urlLimpiar  URL del listado sin busqueda ni filtros
 *   $sugerencia  (opcional) que se puede buscar
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
$busqueda = (string) ($busqueda ?? '');
?>
<div class="ds-card-body">
    <div class="ds-empty-state">
        <div class="ds-empty-icon"><i class="bi bi-search"></i></div>
        <h3><?= $busqueda !== '' ? 'Sin resultados para "' . $e($busqueda) . '"' : 'Ningun registro coincide con los filtros' ?></h3>
        <?php if (!empty($sugerencia)): ?>
        <p><?= $e((string) $sugerencia) ?></p>
        <?php endif ?>
        <a href="<?= $e($urlLimpiar) ?>" class="ds-btn ds-btn-outline" data-remota data-remota-limpiar>
            <i class="bi bi-x-lg"></i>Limpiar busqueda y filtros
        </a>
    </div>
</div>
