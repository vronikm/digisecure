<?php
/**
 * Barra del encabezado de un listado: busqueda en el servidor + "Mostrar N".
 * Va dentro de .ds-card-header; la maneja ds-tabla-remota.js.
 *
 * Variables:
 *   $id          id del formulario (unico en la pagina)
 *   $destino     selector del contenedor de resultados (p. ej. "#clientesResultados")
 *   $parcial     nombre que el controlador espera en la cabecera X-Partial
 *   $ruta        ruta del listado (p. ej. "/clientes")
 *   $paginacion  \DigiSecure\Kernel\Http\Paginacion
 *   $busqueda    texto buscado actual
 *   $placeholder texto de ayuda corto (cabe en celular)
 *   $ayuda       descripcion de que se busca (aria-label y title)
 *   $filtros     (opcional) selector del formulario de filtros propio del listado
 *   $ocultos     (opcional) filtros actuales [nombre => valor] para el envio sin JavaScript
 */

use DigiSecure\Kernel\View\ViewRenderer;

/** @var \DigiSecure\Kernel\View\ViewRenderer $view */
/** @var \DigiSecure\Kernel\Http\Paginacion $paginacion */
$e = ViewRenderer::e(...);
$busqueda = (string) ($busqueda ?? '');
$filtros = (string) ($filtros ?? '');
$ocultos = is_array($ocultos ?? null) ? $ocultos : [];
?>
<form id="<?= $e($id) ?>" class="ds-table-toolbar" role="search" method="get"
      action="<?= $view->url($ruta) ?>"
      data-tabla-remota="<?= $e($destino) ?>" data-parcial="<?= $e($parcial) ?>"
      data-por-pagina-defecto="<?= $e($paginacion->valorDefecto()) ?>"
      <?php if ($filtros !== ''): ?>data-tabla-filtros="<?= $e($filtros) ?>"<?php endif ?>>
    <?php foreach ($ocultos as $nombre => $valor): if ((string) $valor === '') continue; ?>
    <input type="hidden" name="<?= $e((string) $nombre) ?>" value="<?= $e((string) $valor) ?>" data-solo-sin-js>
    <?php endforeach ?>
    <div class="ds-table-filter">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" name="q" class="form-control" value="<?= $e($busqueda) ?>" maxlength="120"
               placeholder="<?= $e($placeholder) ?>" aria-label="<?= $e($ayuda) ?>" title="<?= $e($ayuda) ?>"
               autocomplete="off" spellcheck="false" enterkeyhint="search">
        <span class="ds-table-filter-count" data-conteo <?= $busqueda === '' ? 'hidden' : '' ?>><?= $busqueda !== '' ? $paginacion->total() : '' ?></span>
        <button type="button" class="ds-table-filter-clear" aria-label="Limpiar busqueda" <?= $busqueda === '' ? 'hidden' : '' ?>>
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <label class="ds-per-page" title="Registros por pagina">
        <span class="ds-per-page-label">Mostrar</span>
        <select name="por_pagina" class="form-select" aria-label="Registros por pagina">
            <?php foreach ($paginacion->opciones() as $op): ?>
            <option value="<?= $e($op['valor']) ?>" <?= $op['valor'] === $paginacion->valor() ? 'selected' : '' ?>><?= $e($op['etiqueta']) ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <noscript><button type="submit" class="ds-btn ds-btn-primary ds-btn-sm">Buscar</button></noscript>
</form>
