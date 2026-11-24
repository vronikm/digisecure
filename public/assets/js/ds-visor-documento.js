/**
 * DigiSecure - visor de documentos del expediente (PDF e imagenes).
 *
 * Muestra el archivo dentro de la pagina, sin descargarlo:
 *  - PDF con pdf.js alojado en el propio servidor (assets/lib/pdfjs-*): el
 *    visor nativo del navegador no funciona dentro de la pagina en Chrome de
 *    Android ni en iPhone, y el archivo (datos personales, LOPDP) nunca pasa
 *    por un servicio externo.
 *  - JPG / PNG / WEBP dibujados en un canvas (mismo zoom y giro que el PDF).
 *
 * Controles: paginas anterior/siguiente, zoom (+, -, ajustar al ancho), girar,
 * pantalla completa (boton "Atras" del celular la cierra), pellizcar para
 * zoom en pantallas tactiles, Ctrl + rueda y doble clic/toque.
 * Teclado (con el visor enfocado): + - 0 r f, Esc sale de pantalla completa.
 *
 * Marcado (ver templates/expediente/documento.php):
 *   <section data-visor data-src="…/ver" data-tipo="pdf|imagen" data-mime="…"
 *            data-pdfjs="…/assets/lib/pdfjs-4.10.38/" data-nombre="…">
 *     [data-visor-accion="anterior|siguiente|alejar|acercar|ajustar|girar|completo|reintentar"]
 *     [data-visor-pagina] [data-visor-zoom] [data-visor-lienzo] [data-visor-paginas]
 *     [data-visor-estado] > [data-visor-mensaje] [data-visor-progreso] [data-visor-error]
 */
(function () {
    'use strict';

    const ZOOM_MIN = 0.25;
    const ZOOM_MAX = 5;
    const PASO = 1.25;
    // Limite de pixeles por canvas (iOS Safari no dibuja mas de ~16,7 M).
    const MAX_PIXELES = 15000000;

    class ErrorVisor extends Error {}

    function iniciar(raiz) {
        const src = raiz.getAttribute('data-src');
        const tipo = raiz.getAttribute('data-tipo');
        const mime = raiz.getAttribute('data-mime') || '';
        const nombre = raiz.getAttribute('data-nombre') || 'Documento';
        const lienzo = raiz.querySelector('[data-visor-lienzo]');
        const contenedor = raiz.querySelector('[data-visor-paginas]');
        const estado = raiz.querySelector('[data-visor-estado]');
        if (!src || !lienzo || !contenedor || !estado) return;

        const mensaje = estado.querySelector('[data-visor-mensaje]');
        const progreso = estado.querySelector('[data-visor-progreso]');
        const cajaError = estado.querySelector('[data-visor-error]');
        const indicador = raiz.querySelector('[data-visor-pagina]');
        const textoZoom = raiz.querySelector('[data-visor-zoom]');
        const boton = (accion) => raiz.querySelector('[data-visor-accion="' + accion + '"]');

        let paginas = [];          // {ancho, alto, giro, dibujar(), el, canvas, sucia, tarea}
        let visibles = new Set();
        let zoom = 1;              // 1 = ajustado al ancho del visor
        let giro = 0;              // 0, 90, 180, 270
        let actual = 1;
        let completo = false;
        let pdf = null;
        let anchoPrevio = 0;

        // ---------- Estado / mensajes ----------
        function mostrarEstado(texto, tipoEstado) {
            estado.hidden = false;
            estado.setAttribute('data-estado', tipoEstado);
            if (mensaje) mensaje.textContent = texto;
            if (cajaError) cajaError.hidden = tipoEstado !== 'error';
            if (progreso) progreso.parentElement.hidden = tipoEstado !== 'cargando';
        }

        function ocultarEstado() {
            estado.hidden = true;
        }

        function habilitarControles(activos) {
            raiz.querySelectorAll('[data-visor-accion]').forEach(function (b) {
                if (b.getAttribute('data-visor-accion') === 'reintentar') return;
                if (b.getAttribute('data-visor-accion') === 'completo') return;
                b.disabled = !activos;
            });
        }

        // ---------- Descarga del archivo (con progreso) ----------
        async function traerBytes() {
            let res;
            try {
                res = await fetch(src, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': tipo === 'pdf' ? 'application/pdf' : 'image/*' },
                });
            } catch (e) {
                throw new ErrorVisor('Sin conexión con el servidor. Revisa tu internet e intenta de nuevo.');
            }
            if (res.status === 404) throw new ErrorVisor('El archivo no está disponible o ya no tienes acceso.');
            if (!res.ok) throw new ErrorVisor('No se pudo cargar el archivo (error ' + res.status + ').');
            const ct = (res.headers.get('content-type') || '').toLowerCase();
            if (ct.indexOf('text/html') !== -1) {
                throw new ErrorVisor('Tu sesión expiró. Recarga la página para volver a ingresar.');
            }

            const total = parseInt(res.headers.get('content-length') || '0', 10);
            if (!res.body || !total || !res.body.getReader) {
                return new Uint8Array(await res.arrayBuffer());
            }
            const lector = res.body.getReader();
            const partes = [];
            let recibido = 0;
            for (;;) {
                const paso = await lector.read();
                if (paso.done) break;
                partes.push(paso.value);
                recibido += paso.value.length;
                if (progreso) progreso.style.width = Math.min(100, Math.round(recibido * 100 / total)) + '%';
            }
            const bytes = new Uint8Array(recibido);
            let pos = 0;
            partes.forEach(function (p) { bytes.set(p, pos); pos += p.length; });
            return bytes;
        }

        // ---------- Carga del documento ----------
        async function cargarPdf(bytes) {
            const base = raiz.getAttribute('data-pdfjs');
            let pdfjs;
            try {
                pdfjs = await import(base + 'pdf.min.js?v=4.10.38');
            } catch (e) {
                throw new ErrorVisor('Este navegador no puede mostrar el PDF aquí. Ábrelo en otra pestaña o descárgalo.');
            }
            pdfjs.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.js?v=4.10.38';
            try {
                pdf = await pdfjs.getDocument({
                    data: bytes,
                    isEvalSupported: false,       // nunca compilar codigo desde el PDF
                    enableXfa: false,
                    cMapUrl: base + 'cmaps/',
                    cMapPacked: true,
                    standardFontDataUrl: base + 'standard_fonts/',
                }).promise;
            } catch (e) {
                if (e && e.name === 'PasswordException') {
                    throw new ErrorVisor('El PDF está protegido con contraseña. Descárgalo para abrirlo.');
                }
                throw new ErrorVisor('El archivo PDF está dañado o no se puede leer.');
            }
            const lista = [];
            for (let n = 1; n <= pdf.numPages; n++) {
                const pagina = await pdf.getPage(n);
                const vp = pagina.getViewport({ scale: 1 });
                lista.push({
                    ancho: vp.width,
                    alto: vp.height,
                    dibujar: function (canvas, escala, giroExtra, p) {
                        const vista = pagina.getViewport({ scale: escala, rotation: (pagina.rotate + giroExtra) % 360 });
                        canvas.width = Math.floor(vista.width);
                        canvas.height = Math.floor(vista.height);
                        p.tarea = pagina.render({ canvasContext: canvas.getContext('2d'), viewport: vista });
                        return p.tarea.promise;
                    },
                });
            }
            return lista;
        }

        async function cargarImagen(bytes) {
            const url = URL.createObjectURL(new Blob([bytes], { type: mime || 'image/*' }));
            const img = new Image();
            img.decoding = 'async';
            img.src = url;
            try {
                await img.decode();
            } catch (e) {
                URL.revokeObjectURL(url);
                throw new ErrorVisor('La imagen está dañada o su formato no es compatible con este navegador.');
            }
            return [{
                ancho: img.naturalWidth,
                alto: img.naturalHeight,
                dibujar: function (canvas, escala, giroExtra) {
                    const lado = giroExtra % 180 !== 0;
                    const w = Math.max(1, Math.round(img.naturalWidth * escala));
                    const h = Math.max(1, Math.round(img.naturalHeight * escala));
                    canvas.width = lado ? h : w;
                    canvas.height = lado ? w : h;
                    const ctx = canvas.getContext('2d');
                    ctx.imageSmoothingQuality = 'high';
                    ctx.translate(canvas.width / 2, canvas.height / 2);
                    ctx.rotate(giroExtra * Math.PI / 180);
                    ctx.drawImage(img, -w / 2, -h / 2, w, h);
                    return Promise.resolve();
                },
            }];
        }

        // ---------- Medidas y dibujo ----------
        function anchoDisponible() {
            const estilo = getComputedStyle(lienzo);
            const relleno = parseFloat(estilo.paddingLeft) + parseFloat(estilo.paddingRight);
            return Math.max(120, lienzo.clientWidth - relleno);
        }

        // Tamano en pantalla de una pagina (CSS px) con el zoom y giro actuales.
        // p.ancho / p.alto ya incluyen el giro propio de la pagina del PDF.
        // "Ajustar" (zoom 1) = cada pagina al ancho del visor: en el celular una
        // pagina vertical no se achica por tener una horizontal en el mismo PDF.
        function medidas(p) {
            const lado = giro % 180 !== 0;
            const anchoGirado = lado ? p.alto : p.ancho;
            const altoGirado = lado ? p.ancho : p.alto;
            const ancho = anchoDisponible() * zoom;
            return { ancho: ancho, alto: ancho * altoGirado / anchoGirado, base: anchoGirado };
        }

        function maquetar() {
            paginas.forEach(function (p) {
                const m = medidas(p);
                p.el.style.width = Math.round(m.ancho) + 'px';
                p.el.style.height = Math.round(m.alto) + 'px';
                p.sucia = true;
            });
            if (textoZoom) textoZoom.textContent = Math.round(zoom * 100) + '%';
            const b = boton('ajustar');
            if (b) b.setAttribute('aria-pressed', zoom === 1 ? 'true' : 'false');
            visibles.forEach(dibujar);
        }

        function dibujar(p) {
            if (!p.sucia) return;
            p.sucia = false;
            if (p.tarea && p.tarea.cancel) p.tarea.cancel();
            const m = medidas(p);
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            // Escala sobre el tamano sin girar (el giro solo intercambia lados).
            let escala = (m.ancho * dpr) / m.base;
            const pixeles = (p.ancho * escala) * (p.alto * escala);
            if (pixeles > MAX_PIXELES) escala *= Math.sqrt(MAX_PIXELES / pixeles);
            // Canvas nuevo: el anterior se ve hasta que el nuevo este listo.
            const canvas = document.createElement('canvas');
            canvas.setAttribute('aria-hidden', 'true');
            p.dibujar(canvas, escala, giro, p).then(function () {
                if (p.canvas) p.canvas.remove();
                p.canvas = canvas;
                p.el.appendChild(canvas);
                p.el.classList.add('is-lista');
            }).catch(function (e) {
                if (e && e.name === 'RenderingCancelledException') return;
                p.el.classList.add('is-error');
            });
        }

        function liberar(p) {
            if (p.tarea && p.tarea.cancel) p.tarea.cancel();
            if (p.canvas) {
                p.canvas.width = 0;
                p.canvas.height = 0;
                p.canvas.remove();
                p.canvas = null;
            }
            p.el.classList.remove('is-lista');
            p.sucia = true;
        }

        // ---------- Pagina actual ----------
        function actualizarIndicador() {
            if (!paginas.length) return;
            const tope = lienzo.scrollTop + lienzo.clientHeight * 0.35;
            let n = 1;
            paginas.forEach(function (p, i) { if (p.el.offsetTop <= tope) n = i + 1; });
            actual = n;
            if (indicador) indicador.textContent = n + ' / ' + paginas.length;
            const ant = boton('anterior');
            const sig = boton('siguiente');
            if (ant) ant.disabled = n <= 1;
            if (sig) sig.disabled = n >= paginas.length;
        }

        function irA(n) {
            const p = paginas[Math.max(1, Math.min(paginas.length, n)) - 1];
            if (!p) return;
            lienzo.scrollTo({ top: p.el.offsetTop - 12, behavior: 'smooth' });
        }

        // Cambia el zoom manteniendo fijo el punto (x, y) del visor.
        function fijarZoom(nuevo, x, y) {
            nuevo = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, nuevo));
            if (Math.abs(nuevo - zoom) < 0.001 || !paginas.length) return;
            const px = x === undefined ? lienzo.clientWidth / 2 : x;
            const py = y === undefined ? lienzo.clientHeight / 3 : y;
            const k = nuevo / zoom;
            const cx = lienzo.scrollLeft + px;
            const cy = lienzo.scrollTop + py;
            zoom = Math.abs(nuevo - 1) < 0.02 ? 1 : nuevo;
            maquetar();
            lienzo.scrollLeft = cx * k - px;
            lienzo.scrollTop = cy * k - py;
            actualizarIndicador();
        }

        // ---------- Pantalla completa (capa propia: funciona tambien en iPhone) ----------
        // El visor se mueve a <body> mientras ocupa la pantalla: algun contenedor
        // de la pagina (animacion/transform) convertiria el position:fixed en
        // relativo a el. Un marcador conserva su lugar y su alto.
        let marcador = null;

        function posicionRelativa() {
            return lienzo.scrollHeight > 0 ? lienzo.scrollTop / lienzo.scrollHeight : 0;
        }

        function abrirCompleto() {
            if (completo) return;
            const rel = posicionRelativa();
            completo = true;
            marcador = document.createElement('div');
            marcador.className = 'ds-visor-marcador';
            marcador.style.height = raiz.offsetHeight + 'px';
            raiz.parentNode.insertBefore(marcador, raiz);
            document.body.appendChild(raiz);
            raiz.classList.add('is-completo');
            document.documentElement.classList.add('ds-visor-abierto');
            actualizarBotonCompleto();
            try { history.pushState({ dsVisor: true }, ''); } catch (e) { /* sin historial */ }
            reajustar(true, rel);
            lienzo.focus({ preventScroll: true });
        }

        function cerrarCompleto(desdeHistorial) {
            if (!completo) return;
            const rel = posicionRelativa();
            completo = false;
            raiz.classList.remove('is-completo');
            if (marcador) {
                marcador.parentNode.insertBefore(raiz, marcador);
                marcador.remove();
                marcador = null;
            }
            document.documentElement.classList.remove('ds-visor-abierto');
            actualizarBotonCompleto();
            if (!desdeHistorial && history.state && history.state.dsVisor) history.back();
            reajustar(true, rel);
            const b = boton('completo');
            if (b) b.focus({ preventScroll: true });
        }

        function actualizarBotonCompleto() {
            const b = boton('completo');
            if (!b) return;
            const etiqueta = completo ? 'Salir de pantalla completa' : 'Pantalla completa';
            b.setAttribute('aria-label', etiqueta);
            b.title = etiqueta;
            b.setAttribute('aria-pressed', completo ? 'true' : 'false');
            const icono = b.querySelector('i');
            if (icono) icono.className = 'bi ' + (completo ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen');
        }

        // relGuardada: posicion previa (0..1) cuando el visor se movio en el DOM
        // (al moverlo el navegador reinicia su scroll).
        function reajustar(forzar, relGuardada) {
            const ancho = lienzo.clientWidth;
            if (!forzar && Math.abs(ancho - anchoPrevio) < 2) return;
            anchoPrevio = ancho;
            if (paginas.length) {
                const rel = relGuardada !== undefined ? relGuardada : posicionRelativa();
                maquetar();
                lienzo.scrollTop = rel * lienzo.scrollHeight;
                actualizarIndicador();
            }
        }

        // ---------- Eventos ----------
        raiz.addEventListener('click', function (ev) {
            const b = ev.target.closest('[data-visor-accion]');
            if (!b || !raiz.contains(b)) return;
            switch (b.getAttribute('data-visor-accion')) {
                case 'anterior': irA(actual - 1); break;
                case 'siguiente': irA(actual + 1); break;
                case 'acercar': fijarZoom(zoom * PASO); break;
                case 'alejar': fijarZoom(zoom / PASO); break;
                case 'ajustar': fijarZoom(1); break;
                case 'girar':
                    giro = (giro + 90) % 360;
                    maquetar();
                    actualizarIndicador();
                    break;
                case 'completo': completo ? cerrarCompleto(false) : abrirCompleto(); break;
                case 'reintentar': cargar(); break;
            }
        });

        window.addEventListener('popstate', function () { if (completo) cerrarCompleto(true); });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && completo) {
                ev.preventDefault();
                cerrarCompleto(false);
            }
        });

        lienzo.addEventListener('keydown', function (ev) {
            if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
            const teclas = {
                '+': function () { fijarZoom(zoom * PASO); },
                '=': function () { fijarZoom(zoom * PASO); },
                '-': function () { fijarZoom(zoom / PASO); },
                '0': function () { fijarZoom(1); },
                'r': function () { boton('girar') && boton('girar').click(); },
                'f': function () { completo ? cerrarCompleto(false) : abrirCompleto(); },
            };
            const accion = teclas[ev.key];
            if (accion && paginas.length) {
                ev.preventDefault();
                accion();
            }
        });

        let pendienteScroll = false;
        lienzo.addEventListener('scroll', function () {
            if (pendienteScroll) return;
            pendienteScroll = true;
            requestAnimationFrame(function () {
                pendienteScroll = false;
                actualizarIndicador();
            });
        }, { passive: true });

        // Ctrl + rueda (y el pellizco del trackpad, que llega igual) = zoom.
        lienzo.addEventListener('wheel', function (ev) {
            if (!ev.ctrlKey || !paginas.length) return;
            ev.preventDefault();
            const r = lienzo.getBoundingClientRect();
            fijarZoom(zoom * Math.exp(-ev.deltaY * 0.01), ev.clientX - r.left, ev.clientY - r.top);
        }, { passive: false });

        // Doble clic / doble toque: alterna entre ajustado y 200 %.
        lienzo.addEventListener('dblclick', function (ev) {
            if (!paginas.length) return;
            const r = lienzo.getBoundingClientRect();
            fijarZoom(zoom > 1.05 ? 1 : 2, ev.clientX - r.left, ev.clientY - r.top);
        });

        // Pellizcar para zoom: se escala con CSS durante el gesto y se dibuja al soltar.
        let gesto = null;
        function distancia(t) {
            return Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY);
        }
        lienzo.addEventListener('touchstart', function (ev) {
            if (ev.touches.length !== 2 || !paginas.length) return;
            const r = lienzo.getBoundingClientRect();
            const x = (ev.touches[0].clientX + ev.touches[1].clientX) / 2 - r.left;
            const y = (ev.touches[0].clientY + ev.touches[1].clientY) / 2 - r.top;
            gesto = { d0: distancia(ev.touches), x: x, y: y, escala: 1 };
            contenedor.style.transformOrigin = (lienzo.scrollLeft + x - contenedor.offsetLeft) + 'px '
                + (lienzo.scrollTop + y - contenedor.offsetTop) + 'px';
        }, { passive: true });
        lienzo.addEventListener('touchmove', function (ev) {
            if (!gesto || ev.touches.length !== 2) return;
            ev.preventDefault();
            const limite = function (s) { return Math.max(ZOOM_MIN / zoom, Math.min(ZOOM_MAX / zoom, s)); };
            gesto.escala = limite(distancia(ev.touches) / gesto.d0);
            contenedor.style.transform = 'scale(' + gesto.escala + ')';
        }, { passive: false });
        function terminarGesto() {
            if (!gesto) return;
            const g = gesto;
            gesto = null;
            contenedor.style.transform = '';
            fijarZoom(zoom * g.escala, g.x, g.y);
        }
        lienzo.addEventListener('touchend', function (ev) { if (ev.touches.length < 2) terminarGesto(); });
        lienzo.addEventListener('touchcancel', terminarGesto);

        if ('ResizeObserver' in window) {
            let espera = null;
            new ResizeObserver(function () {
                clearTimeout(espera);
                espera = setTimeout(function () { reajustar(false); }, 120);
            }).observe(lienzo);
        }

        // Solo se dibujan las paginas cercanas a la vista; las lejanas se liberan.
        const observador = 'IntersectionObserver' in window ? new IntersectionObserver(function (entradas) {
            entradas.forEach(function (en) {
                const p = paginas[Number(en.target.getAttribute('data-n')) - 1];
                if (!p) return;
                if (en.isIntersecting) {
                    visibles.add(p);
                    dibujar(p);
                } else {
                    visibles.delete(p);
                    liberar(p);
                }
            });
        }, { root: lienzo, rootMargin: '600px 0px' }) : null;

        // ---------- Arranque ----------
        async function cargar() {
            habilitarControles(false);
            if (progreso) progreso.style.width = '0%';
            mostrarEstado(tipo === 'pdf' ? 'Cargando el PDF…' : 'Cargando la imagen…', 'cargando');
            paginas.forEach(function (p) { if (observador) observador.unobserve(p.el); p.el.remove(); });
            paginas = [];
            visibles = new Set();
            if (pdf) { pdf.destroy(); pdf = null; }

            try {
                const bytes = await traerBytes();
                const lista = tipo === 'pdf' ? await cargarPdf(bytes) : await cargarImagen(bytes);
                if (!lista.length) throw new ErrorVisor('El documento no tiene páginas.');
                lista.forEach(function (p, i) {
                    const el = document.createElement('div');
                    el.className = 'ds-visor-hoja';
                    el.setAttribute('data-n', String(i + 1));
                    el.setAttribute('role', 'img');
                    el.setAttribute('aria-label', lista.length > 1
                        ? nombre + ', página ' + (i + 1) + ' de ' + lista.length
                        : nombre);
                    contenedor.appendChild(el);
                    p.el = el;
                    p.sucia = true;
                });
                paginas = lista;
                raiz.classList.toggle('is-una-pagina', paginas.length === 1);
                anchoPrevio = lienzo.clientWidth;
                maquetar();
                if (observador) {
                    paginas.forEach(function (p) { observador.observe(p.el); });
                } else {
                    paginas.forEach(function (p) { visibles.add(p); dibujar(p); });
                }
                ocultarEstado();
                habilitarControles(true);
                actualizarIndicador();
            } catch (e) {
                mostrarEstado(e instanceof ErrorVisor ? e.message : 'No se pudo mostrar el archivo.', 'error');
                if (!(e instanceof ErrorVisor) && window.console) console.error(e);
            }
        }

        cargar();
    }

    function arrancar() {
        document.querySelectorAll('[data-visor]').forEach(iniciar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }
})();
