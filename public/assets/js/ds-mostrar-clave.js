/*
 * Mostrar / ocultar contrasena (pantallas publicas: login, registro).
 *
 * Marcado esperado:
 *   <div class="login-input-wrap has-clave">
 *     <input type="password" id="password" data-aviso-mayus="password-mayus" ...>
 *     <button type="button" class="login-clave-toggle" data-mostrar-clave
 *             aria-controls="password" aria-pressed="false" hidden>...</button>
 *   </div>
 *   <p class="login-capslock" id="password-mayus" hidden>...</p>
 *
 * - Mejora progresiva: el boton nace oculto y solo aparece si este script corre.
 * - Al tocar el ojo el foco y el cursor se quedan en el campo (en celular no se
 *   cierra el teclado).
 * - Antes de enviar el formulario y al salir de la pagina la clave vuelve a
 *   ocultarse: asi el navegador no la guarda en el historial de campos de texto
 *   ni queda visible al volver con "Atras".
 * - Aviso de Bloq Mayus (teclados fisicos; en celular el navegador no lo informa).
 */
(function () {
    'use strict';

    function iniciar(btn) {
        var input = document.getElementById(btn.getAttribute('aria-controls') || '');
        if (!input) { return; }
        var icono = btn.querySelector('i');
        var aviso = document.getElementById(input.getAttribute('data-aviso-mayus') || '');

        function fijar(mostrar) {
            var ini = null;
            var fin = null;
            try { ini = input.selectionStart; fin = input.selectionEnd; } catch (e) { /* sin seleccion */ }
            input.type = mostrar ? 'text' : 'password';
            var texto = mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña';
            btn.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
            btn.setAttribute('aria-label', texto);
            btn.title = texto;
            if (icono) { icono.className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye'; }
            if (document.activeElement === input && ini !== null) {
                try { input.setSelectionRange(ini, fin); } catch (e) { /* ignorar */ }
            }
        }

        // Si el campo ya tiene el foco, que el toque no se lo quite (teclado abierto).
        btn.addEventListener('pointerdown', function (e) {
            if (document.activeElement === input) { e.preventDefault(); }
        });
        btn.addEventListener('mousedown', function (e) {
            if (document.activeElement === input) { e.preventDefault(); }
        });
        btn.addEventListener('click', function () {
            fijar(input.type === 'password');
        });

        if (input.form) {
            input.form.addEventListener('submit', function (e) {
                if (!e.defaultPrevented) { fijar(false); }
            });
        }
        window.addEventListener('pagehide', function () { fijar(false); });

        if (aviso) {
            var revisar = function (e) {
                if (typeof e.getModifierState !== 'function') { return; }
                aviso.hidden = !e.getModifierState('CapsLock');
            };
            input.addEventListener('keydown', revisar);
            input.addEventListener('keyup', revisar);
            input.addEventListener('blur', function () { aviso.hidden = true; });
        }

        btn.hidden = false;
    }

    function arrancar() {
        var botones = document.querySelectorAll('[data-mostrar-clave]');
        for (var i = 0; i < botones.length; i++) { iniciar(botones[i]); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }
})();
