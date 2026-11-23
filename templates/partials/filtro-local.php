<?php
/**
 * Buscador instantaneo para listados que ya cargan todas sus filas (catalogos
 * sin paginar). Va dentro de .ds-card-header; lo maneja ds-filtro-tabla.js.
 * Para listados paginados usar partials/tabla-toolbar.php (busca en el servidor).
 *
 * Variables:
 *   $id          id del input (unico en la pagina)
 *   $tabla       selector de la tabla (p. ej. "#tablaRoles")
 *   $placeholder texto de ayuda corto
 *   $ayuda       descripcion de que se busca (aria-label)
 */

use DigiSecure\Kernel\View\ViewRenderer;

$e = ViewRenderer::e(...);
?>
<div class="ds-table-filter" role="search">
    <i class="bi bi-search" aria-hidden="true"></i>
    <input type="search" id="<?= $e($id) ?>" class="form-control" placeholder="<?= $e($placeholder) ?>"
           aria-label="<?= $e($ayuda) ?>" title="<?= $e($ayuda) ?>" autocomplete="off" spellcheck="false"
           data-filtro-tabla="<?= $e($tabla) ?>" data-filtro-contador="#<?= $e($id) ?>Conteo">
    <span class="ds-table-filter-count" id="<?= $e($id) ?>Conteo" aria-live="polite" hidden></span>
    <button type="button" class="ds-table-filter-clear" aria-label="Limpiar busqueda" hidden>
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
</div>
