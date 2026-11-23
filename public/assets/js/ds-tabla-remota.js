/**
 * DigiSecure - listados con busqueda, filtros y paginacion en el servidor,
 * sin recargar la pagina.
 *
 * <form data-tabla-remota="#destino" data-parcial="clientes" action="/clientes"
 *       data-tabla-filtros="#filtrosClientes">        (filtros: opcional)
 *   <input name="q"> <select name="por_pagina"> ...
 * </form>
 * <form id="filtrosClientes"> estado, cliente, fechas... </form>
 * <div id="destino"> fragmento que devuelve el servidor </div>
 *
 * - Escribir (300 ms sin teclear), Enter, cambiar "Mostrar" o cualquier
 *   filtro pide al servidor el fragmento con "X-Partial: <data-parcial>" y lo
 *   reemplaza en #destino. La busqueda cubre TODOS los registros.
 * - Los enlaces con data-remota del fragmento (paginacion, limpiar) tambien.
 *   data-remota-limpiar ademas vacia el buscador y los filtros.
 * - La URL del navegador refleja la busqueda: recargar, compartir y Atras
 *   funcionan. Solo se pinta la respuesta de la ultima peticion.
 * - Si algo falla (sesion vencida, error), navega a la URL normal: todo
 *   funciona tambien sin JavaScript (inputs data-solo-sin-js).
 */
(function () {
    'use strict';

    const RESERVADOS = ['q', 'por_pagina', 'page'];

    function iniciar(form) {
        const destino = document.querySelector(form.getAttribute('data-tabla-remota'));
        const parcial = form.getAttribute('data-parcial');
        if (!destino || !parcial || !window.fetch || !window.URLSearchParams) return;

        const input = form.querySelector('input[name="q"]');
        const select = form.querySelector('select[name="por_pagina"]');
        const limpiar = form.querySelector('.ds-table-filter-clear');
        const conteo = form.querySelector('[data-conteo]');
        const porPaginaDefecto = form.getAttribute('data-por-pagina-defecto') || '';
        const filtros = form.getAttribute('data-tabla-filtros')
            ? Array.from(document.querySelectorAll(form.getAttribute('data-tabla-filtros')))
            : [];
        const resumenes = document.querySelectorAll('[data-resumen-de="#' + destino.id + '"]');
        let controlador = null;
        let espera = null;
        let urlActual = window.location.pathname + window.location.search;

        function rutaBase() {
            return new URL(form.action, window.location.href).pathname;
        }

        function urlDelFormulario() {
            const params = new URLSearchParams();
            filtros.forEach(function (f) {
                new FormData(f).forEach(function (valor, nombre) {
                    if (RESERVADOS.indexOf(nombre) === -1 && String(valor).trim() !== '') params.append(nombre, valor);
                });
            });
            const q = input ? input.value.trim() : '';
            if (q !== '') params.set('q', q);
            if (select && select.value !== porPaginaDefecto) params.set('por_pagina', select.value);
            const qs = params.toString();
            return rutaBase() + (qs ? '?' + qs : '');
        }

        // El buscador, el selector y los filtros reflejan la URL que se ve.
        function sincronizar(url) {
            const params = new URL(url, window.location.href).searchParams;
            if (input && document.activeElement !== input) input.value = params.get('q') || '';
            if (select) select.value = params.get('por_pagina') || porPaginaDefecto;
            if (limpiar) limpiar.hidden = !input || input.value === '';
            filtros.forEach(function (f) {
                Array.from(f.elements).forEach(function (el) {
                    if (!el.name || RESERVADOS.indexOf(el.name) !== -1 || el.type === 'submit' || el.type === 'button') return;
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        el.checked = params.getAll(el.name).indexOf(el.value) !== -1;
                    } else if (el.type !== 'hidden') {
                        el.value = params.get(el.name) || '';
                    }
                });
            });
        }

        // Enlaces de exportacion (CSV) con los mismos filtros que se ven:
        // <a data-exportar-de="#destino" data-exportar-base="/auditoria/exportar">
        const exportes = document.querySelectorAll('a[data-exportar-de="#' + destino.id + '"]');
        function actualizarExportes(url) {
            if (!exportes.length) return;
            const params = new URL(url, window.location.href).searchParams;
            params.delete('page');
            params.delete('por_pagina');
            const qs = params.toString();
            exportes.forEach(function (a) {
                a.href = a.getAttribute('data-exportar-base') + (qs ? '?' + qs : '');
            });
        }

        function pintarResumen() {
            const raiz = destino.querySelector('[data-total]');
            if (!raiz) return;
            const texto = raiz.getAttribute('data-resumen');
            if (texto) resumenes.forEach(function (el) { el.textContent = texto; });
            if (conteo) {
                const hayBusqueda = !!input && input.value.trim() !== '';
                conteo.hidden = !hayBusqueda;
                conteo.textContent = hayBusqueda ? raiz.getAttribute('data-total') : '';
            }
        }

        function cargar(url, opciones) {
            opciones = opciones || {};
            if (url === urlActual && !opciones.forzar) return;
            urlActual = url;
            if (controlador) controlador.abort();
            const este = controlador = new AbortController();

            destino.setAttribute('aria-busy', 'true');
            destino.classList.add('is-loading');

            fetch(url, {
                headers: { 'X-Partial': parcial, 'Accept': 'text/html' },
                credentials: 'same-origin',
                signal: este.signal,
            }).then(function (r) {
                // Redireccion (sesion vencida -> login) o error: navegacion normal.
                if (r.redirected || !r.ok) throw new Error('respuesta ' + r.status);
                return r.text();
            }).then(function (html) {
                if (este !== controlador) return;
                destino.innerHTML = html;
                if (window.dsTablasMoviles) window.dsTablasMoviles(destino);
                pintarResumen();
                actualizarExportes(url);
                const estado = { tablaRemota: destino.id };
                if (opciones.historial === 'push') {
                    history.pushState(estado, '', url);
                } else {
                    history.replaceState(estado, '', url);
                }
                if (opciones.subir) {
                    const tarjeta = destino.closest('.ds-card') || destino;
                    if (tarjeta.getBoundingClientRect().top < 0) tarjeta.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }).catch(function (err) {
                if (err && err.name === 'AbortError') return;
                window.location.href = url;
            }).finally(function () {
                if (este === controlador) {
                    destino.removeAttribute('aria-busy');
                    destino.classList.remove('is-loading');
                }
            });
        }

        function aplicarYa(opciones) {
            clearTimeout(espera);
            cargar(urlDelFormulario(), opciones);
        }

        function aplicarLuego() {
            clearTimeout(espera);
            espera = setTimeout(function () { cargar(urlDelFormulario()); }, 300);
        }

        function limpiarTodo() {
            if (input) input.value = '';
            if (limpiar) limpiar.hidden = true;
            filtros.forEach(function (f) { f.reset(); sincronizarVacio(f); });
            aplicarYa({ forzar: true });
        }

        // reset() vuelve a los valores renderizados por el servidor; aqui se vacian,
        // incluidos los filtros ocultos que venian en la URL (p. ej. usuario_id).
        function sincronizarVacio(f) {
            Array.from(f.elements).forEach(function (el) {
                if (!el.name || el.type === 'submit' || el.type === 'button' || RESERVADOS.indexOf(el.name) !== -1) return;
                if (el.type === 'checkbox' || el.type === 'radio') { el.checked = false; } else { el.value = ''; }
            });
        }

        // Los campos solo para el envio sin JavaScript no deben duplicar valores.
        form.querySelectorAll('[data-solo-sin-js]').forEach(function (el) { el.disabled = true; });

        if (input) {
            input.addEventListener('input', function () {
                if (limpiar) limpiar.hidden = input.value === '';
                aplicarLuego();
            });
            input.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && input.value !== '') {
                    ev.preventDefault();
                    input.value = '';
                    if (limpiar) limpiar.hidden = true;
                    aplicarYa();
                }
            });
        }
        if (select) select.addEventListener('change', function () { aplicarYa({ subir: true }); });
        if (limpiar && input) {
            limpiar.addEventListener('click', function () {
                input.value = '';
                limpiar.hidden = true;
                aplicarYa();
                input.focus();
            });
        }
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            aplicarYa({ forzar: true });
        });

        // Filtros propios del listado: se aplican al cambiar, sin "Buscar".
        filtros.forEach(function (f) {
            f.addEventListener('submit', function (ev) { ev.preventDefault(); aplicarYa({ forzar: true }); });
            f.addEventListener('change', function (ev) {
                if (ev.target.matches('select, input[type="date"], input[type="checkbox"], input[type="radio"]')) aplicarYa();
            });
            f.addEventListener('input', function (ev) {
                if (ev.target.matches('input[type="text"], input[type="search"]')) aplicarLuego();
            });
            f.addEventListener('click', function (ev) {
                const a = ev.target instanceof Element ? ev.target.closest('a[data-remota-limpiar]') : null;
                if (!a) return;
                ev.preventDefault();
                limpiarTodo();
            });
        });

        // Paginacion y "limpiar" dentro del fragmento.
        destino.addEventListener('click', function (ev) {
            const a = ev.target instanceof Element ? ev.target.closest('a[data-remota]') : null;
            if (!a || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button !== 0) return;
            ev.preventDefault();
            if (a.hasAttribute('data-remota-limpiar')) { limpiarTodo(); return; }
            if (a.classList.contains('is-disabled') || a.getAttribute('aria-current') === 'page') return;
            const url = new URL(a.href, window.location.href);
            sincronizar(url.href);
            cargar(url.pathname + url.search, { historial: 'push', subir: true });
        });

        window.addEventListener('popstate', function () {
            const url = window.location.pathname + window.location.search;
            sincronizar(url);
            cargar(url, { forzar: true });
        });
    }

    function arrancar() {
        document.querySelectorAll('form[data-tabla-remota]').forEach(iniciar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }
})();
