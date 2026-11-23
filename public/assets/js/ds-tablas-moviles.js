/**
 * DigiSecure - tablas legibles en el celular.
 *
 * En pantallas angostas cada fila de una tabla .ds-table se muestra como una
 * tarjeta "Etiqueta: valor" (ver .ds-table-stack en ds-system.css). Este script
 * solo copia el titulo de cada columna a su celda (data-label); el resto es CSS.
 * Asi las plantillas no necesitan cambios y las columnas de estado y acciones
 * dejan de quedar escondidas a la derecha de una tabla que hay que deslizar.
 *
 * Exclusion: <table class="ds-table" data-no-stack> mantiene la tabla normal.
 */
(function () {
    'use strict';

    function titulosDe(table) {
        const fila = table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
        if (!fila) return null;
        const titulos = [];
        for (const th of fila.cells) {
            const texto = (th.textContent || '').replace(/\s+/g, ' ').trim();
            for (let i = 0; i < (th.colSpan || 1); i++) titulos.push(texto);
        }
        return titulos;
    }

    // Columnas que no sirven de titulo de la tarjeta: numeracion o sin nombre.
    const NO_TITULO = /^(|#|n[°º.]?|no\.?|nro\.?)$/i;

    function etiquetar(table) {
        if (table.hasAttribute('data-no-stack')) return;
        const titulos = titulosDe(table);
        if (!titulos || titulos.length < 2) return;

        // Titulo de la tarjeta: la primera columna con nombre real (p. ej. en
        // "# | Codigo | Nombre" es Codigo, no el numero de orden).
        let colTitulo = titulos.findIndex(function (t) { return !NO_TITULO.test(t); });
        if (colTitulo < 0 || colTitulo === titulos.length - 1) colTitulo = 0;

        for (const tbody of table.tBodies) {
            for (const tr of tbody.rows) {
                // Filas de grupo, vacias o de "sin resultados": ocupan todo el ancho.
                if (tr.cells.length === 1 || tr.classList.contains('ds-table-group-row')) {
                    tr.classList.add('ds-row-full');
                    continue;
                }
                let col = 0;
                for (const td of tr.cells) {
                    if (!td.hasAttribute('data-label')) td.setAttribute('data-label', titulos[col] || '');
                    if (col === colTitulo) td.classList.add('ds-cell-title');
                    col += td.colSpan || 1;
                }
            }
        }
        table.classList.add('ds-table-stack');
    }

    function aplicar(raiz) {
        (raiz || document).querySelectorAll('table.ds-table').forEach(etiquetar);
    }

    function iniciar() {
        aplicar(document);
        // Filas que se agregan despues (filtros en vivo, paginacion por JS).
        new MutationObserver(function (cambios) {
            for (const c of cambios) {
                const t = c.target.closest && c.target.closest('table.ds-table');
                if (t) etiquetar(t);
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    window.dsTablasMoviles = aplicar;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
