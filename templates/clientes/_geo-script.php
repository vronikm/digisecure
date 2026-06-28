<?php
/**
 * Script de cascada Provincia -> Ciudad para los formularios de cliente.
 * Requiere selects con id="cli-provincia" y id="cli-ciudad" (este último con
 * data-selected = ciudad actual, para repoblar en edición).
 */

use DigiSecure\Shared\Domain\EcuadorGeo;
?>
<script>
(function () {
    var CIUDADES = <?= json_encode(EcuadorGeo::ciudadesPorProvincia(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var prov = document.getElementById('cli-provincia');
    var ciud = document.getElementById('cli-ciudad');
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
