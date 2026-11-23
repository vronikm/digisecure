/**
 * DigiSecure - buscador instantaneo de tablas (del lado del navegador).
 *
 * Para listados que ya cargan todas sus filas (catalogos sin paginar). Los
 * listados paginados usan ds-tabla-remota.js, que busca en el servidor.
 *
 * Uso: <input type="search" data-filtro-tabla="#idTabla"
 *             data-filtro-contador="#idContador">   (contador opcional)
 *      (ver templates/partials/filtro-local.php)
 *
 * - Filtra las filas del <tbody> mientras se escribe, sin recargar.
 * - Ignora tildes y mayusculas: "gestion" encuentra "GESTIÓN".
 * - Varias palabras deben aparecer todas: "loja utpl".
 * - No busca en la columna de acciones (si no, "ver" coincidiria con todas).
 * - Esc limpia el filtro. Muestra "sin resultados" y el conteo "3 de 8".
 */
(function () {
    'use strict';

    function normalizar(texto) {
        return (texto || '')
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function textoDeFila(tr) {
        let texto = '';
        for (const td of tr.cells) {
            if (td.querySelector('.ds-row-actions')) continue;
            texto += ' ' + td.textContent;
        }
        return normalizar(texto);
    }

    function iniciar(input) {
        const tabla = document.querySelector(input.getAttribute('data-filtro-tabla'));
        if (!tabla || !tabla.tBodies[0]) return;
        const tbody = tabla.tBodies[0];
        const contador = document.querySelector(input.getAttribute('data-filtro-contador') || '');
        // Boton propio de limpiar (opcional, en el mismo contenedor que el input).
        const limpiar = input.parentElement.querySelector('.ds-table-filter-clear');
        const filas = Array.from(tbody.rows).filter(function (tr) { return tr.cells.length > 1; });
        const indice = filas.map(textoDeFila);
        const columnas = (tabla.tHead && tabla.tHead.rows[0]) ? tabla.tHead.rows[0].cells.length : 1;

        // Fila "sin resultados" (se crea una sola vez y se oculta).
        const vacia = document.createElement('tr');
        vacia.className = 'ds-filtro-vacio ds-row-full';
        vacia.hidden = true;
        const celda = document.createElement('td');
        celda.colSpan = columnas;
        celda.innerHTML = '<i class="bi bi-search" aria-hidden="true"></i> <span></span>';
        vacia.appendChild(celda);
        tbody.appendChild(vacia);

        function aplicar() {
            const terminos = normalizar(input.value).split(' ').filter(Boolean);
            let visibles = 0;
            filas.forEach(function (tr, i) {
                const coincide = terminos.every(function (t) { return indice[i].indexOf(t) !== -1; });
                tr.hidden = !coincide;
                if (coincide) visibles++;
            });
            vacia.hidden = visibles > 0;
            if (visibles === 0) {
                vacia.querySelector('span').textContent = 'Ningun registro coincide con "' + input.value.trim() + '".';
            }
            if (contador) {
                contador.textContent = terminos.length ? visibles + ' de ' + filas.length : '';
                contador.hidden = terminos.length === 0;
            }
            if (limpiar) limpiar.hidden = input.value === '';
        }

        if (limpiar) {
            limpiar.addEventListener('click', function () {
                input.value = '';
                aplicar();
                input.focus();
            });
        }

        let espera = null;
        input.addEventListener('input', function () {
            clearTimeout(espera);
            espera = setTimeout(aplicar, 120);
        });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && input.value !== '') {
                ev.preventDefault();
                input.value = '';
                aplicar();
            }
        });
        // El navegador puede restaurar el texto al volver con "Atras".
        if (input.value !== '') aplicar();
    }

    function arrancar() {
        document.querySelectorAll('input[data-filtro-tabla]').forEach(iniciar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }
})();
