<?php
/**
 * Script de cascada Provincia -> Ciudad reutilizable.
 * Requiere selects cuyos id se pasan en $provinciaId / $ciudadId (el de ciudad
 * con data-selected = ciudad actual, para repoblar en edición).
 *
 * Por defecto usa los ids de los formularios de cliente (cli-provincia /
 * cli-ciudad). Otras vistas pueden incluirlo con sus propios ids:
 *   $view->include('clientes/_geo-script', ['provinciaId' => '...', 'ciudadId' => '...'])
 */

use DigiSecure\Shared\Domain\EcuadorGeo;

$provinciaId = isset($provinciaId) && is_string($provinciaId) && $provinciaId !== '' ? $provinciaId : 'cli-provincia';
$ciudadId = isset($ciudadId) && is_string($ciudadId) && $ciudadId !== '' ? $ciudadId : 'cli-ciudad';
?>
<script>
(function () {
    var CIUDADES = <?= json_encode(EcuadorGeo::ciudadesPorProvincia(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var prov = document.getElementById(<?= json_encode($provinciaId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    var ciud = document.getElementById(<?= json_encode($ciudadId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    if (!prov || !ciud) { return; }

    function fill(seleccion) {
        var lista = CIUDADES[prov.value] || [];
        ciud.innerHTML = '';
        var ph = document.createElement('option');
        ph.value = '';
        ph.textContent = prov.value ? '— Selecciona ciudad —' : '— Selecciona provincia primero —';
        ciud.appendChild(ph);
        lista.forEach(function (c) {
            var o = document.createElement('option');
            o.value = c;
            o.textContent = c;
            if (c === seleccion) { o.selected = true; }
            ciud.appendChild(o);
        });
        // Preservar un valor existente que no esté en el catálogo (datos previos).
        if (seleccion && lista.indexOf(seleccion) === -1) {
            var ex = document.createElement('option');
            ex.value = seleccion;
            ex.textContent = seleccion + ' (actual)';
            ex.selected = true;
            ciud.appendChild(ex);
        }
    }

    prov.addEventListener('change', function () { fill(''); });
    fill(ciud.getAttribute('data-selected') || '');
})();
</script>
