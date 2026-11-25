# DigiSecure — Changelog

Estado actual: **Phase 18.1** · 37 migraciones · 151/151 tests verdes (PHP 8.3 y 8.5).

---

## Guardias solo muestra empleados; los aspirantes se gestionan en Reclutamiento (2026-11-25)

**Problema:** `/guardias` mostraba las 37 fichas de aspirantes, todas en BORRADOR y ninguno contratado. El registro crea la ficha del aspirante directamente en GEMESEG, para que el personal la revise. Pero el listado no distinguía entre empleados y aspirantes, así que aparecían como guardias antes de contratarlos. Por el mismo motivo:

- un aspirante con la ficha COMPLETA (o uno descartado) se podía asignar a un puesto;
- el Dashboard los contaba como guardias;
- la alerta "Fichas en borrador" mandaba a Guardias.

**Criterio único:** `Ficha\Infrastructure\FichaEmpleado`. Una ficha es de aspirante si quien la creó tiene una postulación que no está `contratado` (también `aprobado` y `descartado`) o si conserva el rol `aspirante`. Las fichas que registra o importa el personal son siempre de empleados.

1. **Guardias:**
   - Lista solo empleados.
   - Un aviso indica cuántos aspirantes están en proceso y lleva a Reclutamiento.
   - El estado vacío y la búsqueda sin resultados explican dónde están los aspirantes.
   - La ficha de un aspirante (`/guardias/{id}`) se sigue abriendo. Ahora muestra el aviso "Ficha de un aspirante en proceso" con un botón para volver a Reclutamiento, y su ruta de navegación es Reclutamiento › nombre › ficha.
2. **Reclutamiento:**
   - Columna **Avance**: estado de la ficha (Sin ficha / Borrador / Por revisar / Completa), barra del Bloque A (x/y) y etiqueta "Listo para contratar". Va justo después del nombre, para que la columna de acciones fija no la tape.
   - Nuevo filtro **Avance**: sin ficha, ficha en borrador, ficha por revisar, documentos pendientes y listos para contratar.
   - Botón **Ficha** en cada fila y **Revisar ficha** / **Validar documentos** en el detalle. Antes, la única forma de llegar a "Enviar a revisión" y "Completar" era pasar por Guardias.
   - Los indicadores son ahora enlaces que filtran el listado: Sin verificar, Fichas por revisar, Listos para contratar y Contratados. El total y los aspirantes en proceso pasan al subtítulo.
   - "Contratados" ya no suma el estado heredado `aprobado`, porque todavía no es una contratación.
3. **Asignaciones:**
   - El selector de guardias ofrece solo empleados con ficha COMPLETA.
   - `GestionarAsignacionesService` también rechaza a un aspirante si la petición llega por otra vía, con el mensaje "contrátala desde Reclutamiento".
4. **Dashboard:**
   - "Guardias activos" cuenta **personas**: empleados con ficha COMPLETA y sin baja; un reingreso no cuenta dos veces.
   - Nueva tarjeta **Aspirantes en proceso** con fichas por revisar y listos para contratar.
   - Los KPIs de fichas (por estado, tipo y mes) excluyen a los aspirantes.
   - La campana separa "Fichas en borrador" (solo de empleados, lleva a Guardias) de la nueva alerta "Fichas de aspirantes por revisar" (lleva a Reclutamiento filtrado).
   - "En proceso" significa lo mismo en todas partes: correo confirmado, ni contratado ni descartado.
- Probado en una copia aislada, con la sesión del superadmin:
  - Guardias pasó de 37 filas a 1 (el empleado registrado por el personal).
  - Los filtros de avance contaron 1/1/1/12/34.
  - Al contratar al aspirante listo, apareció en Guardias y en el selector de Asignaciones.
  - Celular a 390 px sin scroll horizontal y sin errores de consola.
- Tests: 151 (nuevos: `FichaEmpleadoTest`, criterio y validación del alias).

---

## Login: botón para mostrar la contraseña (2026-11-24)

- **Ojo para mostrar u ocultar la contraseña** en el login y en los dos campos de contraseña del registro de postulantes. El ícono cambia (ojo / ojo tachado) y el lector de pantalla anuncia "Mostrar contraseña" u "Ocultar contraseña".
  - Al tocarlo, el cursor se queda donde estaba y en el celular no se cierra el teclado.
  - El botón ocupa todo el alto del campo, así que se toca fácil con el dedo.
  - La contraseña se vuelve a ocultar sola al enviar el formulario y al salir de la página. Así el navegador no la guarda en el historial de campos de texto ni queda a la vista al volver con "Atrás".
  - Si el script no carga, el botón no aparece y el campo funciona como siempre.
  - Edge mostraba su propio ojo; se ocultó para que no haya dos.
- **Aviso "Bloq Mayús está activado"** al escribir la contraseña con un teclado físico.
- **Celular:**
  - Los campos usan letra de 16 px: el iPhone ya no hace zoom al tocar el correo o la contraseña.
  - El campo de correo abre el teclado de correo, sin mayúscula automática ni corrector.
  - La tecla Enter del teclado dice "Siguiente" en el correo e "Ir" en la contraseña.
- **Seguridad:** los campos de contraseña no pasan por el corrector ortográfico (`spellcheck="false"`). Con la contraseña visible, el corrector mejorado del navegador podría enviarla a un servicio externo.
- Archivos: `public/assets/js/ds-mostrar-clave.js` (reutilizable con `data-mostrar-clave`), `templates/auth/login.php`, `ds-auth.css`. Título de la pestaña con tilde: "Iniciar sesión".

---

## Expediente: ver los archivos del documento sin descargarlos (2026-11-23)

- **Visor dentro de la página del documento** (`/expedientes/documento/{id}`) para PDF, JPG, PNG y WEBP. Incluye:
  - páginas con indicador "2 / 5" y flechas;
  - zoom −/+ y "ajustar al ancho" (muestra el %);
  - girar 90° (escaneos de lado);
  - pantalla completa.
  - Atajos: Ctrl + rueda, doble clic (100 % ↔ 200 %) y teclas + − 0 r f. Esc sale de pantalla completa.
- **Celular:**
  - Cada página ocupa el ancho del visor. Se navega deslizando y se hace zoom pellizcando.
  - Los botones miden 40 px.
  - La pantalla completa cubre todo, también en iPhone (capa propia, sin la API Fullscreen), y el botón **Atrás** la cierra.
  - Una imagen sola no deja franja gris.
- **Escritorio (≥ 1200 px):** el visor queda fijo a la izquierda mientras se completa la revisión a la derecha (Archivo, Vigencia, Validación externa y Revisión).
- **PDF con pdf.js 4.10.38 alojado en el propio servidor** (`public/assets/lib/pdfjs-4.10.38/`, build *legacy*, licencia Apache-2.0). Se usa así por dos motivos: el visor nativo no funciona dentro de la página en Chrome de Android ni en iPhone, y los documentos tienen datos personales (LOPDP), así que no deben pasar por un CDN externo.
  - Paquete verificado contra el sha512 del registro npm.
  - Configurado con `isEvalSupported: false`: nunca compila código que venga del PDF. La 4.10.38 ya corrige CVE-2024-4367.
  - Solo se descarga al abrir un PDF. Se dibujan solo las páginas cercanas a la vista y las lejanas se liberan.
- **Nueva ruta `GET /expedientes/documento/{docId}/ver`:**
  - Mismos permisos y controles que la descarga: tenant, bloque B, aspirante solo su ficha e id cifrado en la URL.
  - Responde `inline` solo para PDF e imágenes rasterizadas; cualquier otro tipo recibe 404. SVG o HTML nunca se sirven inline.
  - Se audita aparte como **`expediente.documento.ver`** (quién miró qué y cuándo).
  - `/descargar` ahora siempre entrega el archivo como descarga (`attachment`) y se audita como `expediente.documento.descargar`.
- **Errores claros con "Reintentar" y "Descargar":** sesión vencida, sin conexión, archivo inexistente, PDF con contraseña o dañado. Si no hay archivo, o es un formato sin vista previa (p. ej. DOCX), se muestra un estado vacío con el botón de descarga y el tamaño.
- La tarjeta Archivo muestra el formato legible ("PDF", "Imagen JPG") y el tamaño en KB/MB. La tarjeta Revisión ya no aparece vacía para quien no puede revisar. Se corrigieron tildes en la vista.
- Tests: 144 (nuevos: tipos con vista previa y el cifrado del id en la ruta `/ver`).

---

## Listados: búsqueda en todas las páginas, "Mostrar … / Todos" y paginación en vivo en todas las tablas (2026-11-23)

Lo que se hizo en Guardias se extiende a los 12 listados paginados: Guardias, Clientes, Puestos, Asignaciones, Reclutamiento, Expedientes (y Pendientes), Usuarios, Auditoría, Notificaciones y LOPDP (Solicitudes, Aceptaciones y Consentimientos).

- **Encabezado de cada listado:** buscador que consulta al servidor mientras se escribe, así que encuentra en **todas** las páginas, y selector **Mostrar 10 / 25 / 50 / 100 / Todos**. "Todos" tiene un tope de seguridad de 5000 filas. Si se supera, el rango lo indica ("Mostrando 1–5000 de 7000") y aparece otra página. Auditoría probó "Todos" con 2491 eventos.
- **Los filtros propios** (estado, cliente, tipo, fechas…) siguen en su tarjeta, se aplican al cambiar sin pulsar "Buscar" y se combinan con la búsqueda. "Limpiar" restablece todo sin recargar. La URL refleja búsqueda, filtros, cantidad y página: recargar, compartir y Atrás funcionan.
- **Pie común:** "Mostrando 11–20 de 50" y paginación compacta (1 … 4 5 6 … 12) con botones de 40 px en el celular.
- **Búsqueda por palabras en todos los repositorios** (`Persistence\BusquedaTexto`): cada palabra debe aparecer en alguna columna, sin distinguir tildes ni mayúsculas, y los `%`/`_` se buscan literalmente. **Auditoría, Notificaciones, Consentimientos y Usuarios no tenían búsqueda de texto**; ahora la tienen, y Usuarios además pasa a estar paginado. Varios listados buscan en más columnas que antes: ciudad y sector en Clientes, celular en Reclutamiento, código del puesto en Asignaciones, etc.
- **Piezas reutilizables:** `Kernel\Http\Paginacion` (con tests), `partials/tabla-toolbar.php`, `partials/paginacion.php`, `partials/sin-resultados.php` y `partials/filtro-local.php`. `ds-tabla-remota.js` ahora combina el buscador con los filtros de la tarjeta (`data-tabla-filtros`) y mantiene al día los enlaces de exportación (`data-exportar-de`): el CSV de Auditoría exporta lo que se está viendo. Cada controlador devuelve solo `<modulo>/_listado.php` cuando recibe `X-Partial`.
- **Catálogos sin paginar** (Tipos de documento, Instituciones financieras, Roles e Historial de fichas): buscador instantáneo en el encabezado. Instituciones pierde su tarjeta "Buscar", que hacía lo mismo recargando la página.
- **Celular:** el campo de búsqueda solo reserva espacio para la X y el contador cuando se muestran, así el texto de ayuda cabe completo en los 14 listados. La columna "Orden" ya no se usa como título de la tarjeta.
- **Arreglos encontrados de paso:**
  - Consentimientos: el diálogo de revocar insertaba el titular y el propósito como HTML sin escapar (riesgo XSS). Ahora se escapan y el envío funciona también en filas cargadas al buscar.
  - El bloque "Política de Privacidad" se desbordaba en el celular.
  - Expedientes y Pendientes duplicaban el mismo listado en el controlador; ahora lo comparten.
  - `UserRepository` pierde `listGlobalOrderedByTenant`/`countGlobal`, que no se usaban desde "solo GEMESEG".
- Tests: 133 (11 nuevos para `Paginacion` y `BusquedaTexto`).

---

## Guardias: búsqueda en vivo en todas las páginas y registros por página (2026-11-23)

- **Busca en todos los guardias**, no solo en la página visible. Al escribir (300 ms sin teclear) se consulta al servidor y la tabla se reemplaza sin recargar. El subtítulo ("3 guardias encontrados") y el contador del buscador se actualizan.
- **Selector "Mostrar 10 / 25 / 50 / 100"** en el encabezado, junto al buscador. El servidor valida `por_pagina` (valor inválido → 25).
- **Búsqueda por palabras:** cada palabra debe aparecer en apellidos, nombres, correo o celular, así "paladines nixon" (apellido + nombre) encuentra a la persona; antes daba 0. Los `%` y `_` escritos se buscan literalmente. La cédula se busca completa por su índice ciego. La condición vive en `FichaRepository::filtroGuardias()`, que comparten el listado y el conteo; antes estaba duplicada.
- **Paginación** con ventana compacta (1 … 3 4 5 … 12), "Mostrando 11–20 de 37", botones de 40 px en el celular y sin recargar.
- **URL compartible:** la dirección refleja búsqueda, cantidad y página. Recargar, compartir y el botón Atrás funcionan.
- **Componente reutilizable:** `public/assets/js/ds-tabla-remota.js` + `<form data-tabla-remota="#destino" data-parcial="...">`. El controlador devuelve solo `ficha/_listado.php` (≈3 KB en vez de 68 KB) cuando recibe `X-Partial: guardias`. Descarta respuestas viejas al escribir rápido y, si la sesión vence o hay un error, navega normal. Sin JavaScript funciona como formulario GET.
- Se quita la tarjeta "Buscar" que estaba sobre la tabla: el buscador del encabezado la reemplaza. También salen de `ficha/index.php` las ramas de "modo aspirante", que no se usaban porque el controlador redirige al aspirante a su ficha.

---

## Clientes: buscador dinámico en el listado (2026-11-23)

- En el encabezado "Listado de clientes", a la derecha, hay un buscador que filtra la tabla mientras se escribe, sin recargar. Ignora tildes y mayúsculas ("fundacion" encuentra "FUNDACIÓN") y exige todas las palabras ("coordinacion riesgos"). No busca en los botones, así que "ver" no coincide con todas las filas. Muestra el conteo "1 de 8", un mensaje cuando no hay coincidencias y se limpia con la X o con Esc.
- Es un componente reutilizable, `public/assets/js/ds-filtro-tabla.js`, que se activa con `<input data-filtro-tabla="#idTabla" data-filtro-contador="#idConteo">`. Lo carga el layout, así que llevarlo a otro listado no necesita más JS.
- Filtra solo las filas cargadas. Con más de una página (25 clientes), el texto de ayuda dice "Buscar en esta página...". La búsqueda del servidor sigue en "Filtros".
- En el celular, el buscador ocupa todo el ancho bajo el título, con letra de 16 px para que iOS no acerque la pantalla al enfocar. Los títulos de tarjeta ya no se separan de su ícono.
- **Puestos** usa el mismo buscador ("Listado de puestos"): busca por nombre, código, ciudad, cliente, tipo, modalidad y estado. Sus botones "Ver" y "Editar" llevan ícono en el celular, igual que en Clientes.
- **Guardias** ("Listado de guardias") también lo tiene, salvo en modo aspirante. Son 37 guardias en 2 páginas, así que el componente admite `data-filtro-servidor="/guardias?q="`: al escribir filtra la página al instante, **Enter** busca en todos los guardias y, si en la página no hay coincidencias, el aviso ofrece *Buscar "…" en todos los registros*. "Ver fichas" lleva ícono en el celular.

---

## Acciones de los listados en una sola fila (2026-11-23)

- **Escritorio:** en Clientes, "Ver" y "Editar" se apilaban en dos filas porque `.ds-row-actions` permitía saltos de línea y la columna quedaba angosta. Ahora las acciones nunca se apilan y la columna se ajusta al ancho de sus botones. Aplica a los 20 listados.
- **Acciones siempre a la vista:** en tablet y escritorio, la columna de acciones queda fija a la derecha (`position: sticky`). En una laptop de 1366 px, Guardias, Reclutamiento, Usuarios y Tipos de documento son más anchos que su tarjeta y antes escondían los botones detrás del desplazamiento horizontal.
- **Celular:** las acciones ocupan todo el ancho de la tarjeta en una fila, con 40 px de alto, y cada botón crece según su texto. Los íconos solo se muestran en el celular. Con 3 o más acciones se ocultan los íconos y se reduce la letra, para que "Validar documentos" o "Sin expediente" no se corten.
- Clientes: íconos de ver y editar, y `aria-label` con el nombre del cliente.

---

## Interfaz optimizada para celular (2026-11-23)

Se revisaron 44 vistas a 390 px de ancho (superadmin y aspirante). Al terminar, ninguna desborda ni recorta contenido, y la consola queda sin errores.

- **Tablas como tarjetas.** Con menos de 576 px, cada fila de `.ds-table` se muestra como una tarjeta "Etiqueta  valor". El título de la tarjeta es la primera columna con nombre real (no "#") y las acciones van al pie. Antes, estado y botones quedaban ocultos a la derecha de una tabla que había que deslizar. `public/assets/js/ds-tablas-moviles.js` copia el título de cada columna a `data-label`; las 26 plantillas con tablas no cambian. Para excluir una tabla: `data-no-stack`.
- **Un solo margen.** El panel y las tarjetas ya no suman rellenos. Los botones de cabecera ocupan todo el ancho y los botones tienen 40 px de alto mínimo para el dedo.
- **Ficha del guardia.** Los 5 pasos van en una fila deslizable que centra el paso activo; antes ocupaban una pantalla entera antes del formulario. La columna de la foto ya no se estira a ~560 px por el aviso de "foto anterior", que se cortaba.
- **Encabezados de tarjeta** con botones (p. ej. "Marcar todos / Desmarcar" en roles): bajan de línea en vez de salirse de la tarjeta (13 encabezados de 9 vistas).
- **Avisos `login-info`, `login-error` y `login-success`** (38 usos en vistas internas): solo tenían estilo en la pantalla de login y dentro de la app salían sin formato, también en escritorio. Ahora se ven como `.ds-callout`.
- **Ícono de la app** (favicon y "agregar a pantalla de inicio") y `theme-color` para la barra del navegador. Se elimina el 404 de `/favicon.ico`.
- Editar empresa: se quita la referencia al "panel global", que ya no existe.

Escritorio (1366 px) y tablet (768 px) sin cambios: todo lo anterior vive en `@media (max-width: 575.98px)`, salvo los avisos y el ancho de la foto.

---

## Sistema a la medida de GEMESEG (2026-11-17)

El sistema deja de ser un SaaS multiempresa con planes. Es a la medida de GEMESEG: no hay planes, suscripciones ni alta de empresas, y `superadmin@digisecure.ec` tiene acceso total. Las demás cuentas no cambian.

**Superadministrador**

- **Opera dentro de GEMESEG** (migración 037). Antes estaba en la empresa de plataforma "DigiSecure", que no tiene datos, y no veía guardias, expedientes, clientes ni aspirantes. Conserva su rol global.
- **Acceso total:** `Authorizer` le carga todo el catálogo de permisos, incluidos los que se agreguen en el futuro. Menú, rutas y vistas lo respetan sin excepciones sueltas. `Authenticator` usa la misma fuente al resincronizar la sesión.
- **Su cuenta queda protegida:** las consultas de usuarios por empresa (`UserRepository::SIN_SUPERADMIN`) la excluyen, así que un admin de GEMESEG no puede verla, editarla, bloquearla ni resetear su clave.
- Recibe las alertas operativas de GEMESEG (documentos vencidos o por validar, fichas en borrador, ARCO-P) en lugar de las del panel de empresas.
- Solo él puede cambiar el RUC de la empresa. Antes lo controlaba el permiso `superadmin.panel`.
- `bin/create-superadmin.php` lo crea o actualiza en GEMESEG. Antes lo devolvía a la empresa de plataforma.

**Se retira lo comercial y multiempresa**

- Módulos `Subscription` (planes, ciclos, cupos) y `Superadmin` (dashboard global, empresas, planes, log de registros), autorregistro público de empresas (`/registro`, `/activar`) y sus vistas.
- Cupos del plan: crear guardias o usuarios ya no se bloquea por límites.
- Aviso de suscripción vencida del layout, ítem "Suscripción" y sección "Plataforma" del menú.
- Usuarios: sin ámbito "Sistema/Empresa" ni selector de empresa. Todos gestionan las cuentas de GEMESEG.
- Mi empresa: la tarjeta "Plan y límites" pasa a "Estado de la empresa".
- Migración 037: retira 8 permisos sin uso (`superadmin.*`, `suscripcion.*`) y da los 42 restantes al rol superadmin. Tiene `@down`.

Las tablas `planes`, `suscripcion_ciclos` y `registros_empresa`, y las empresas "DigiSecure" y SEG-SUR, se conservan intactas en la base.

Stack: PHP 8.3–8.5 / MySQL 8 / Chart.js 4.4 + sistema de diseño propio `ds-system` (iconos Bootstrap; la hoja CSS de Bootstrap ya no se carga).

---

## Incidente — Error 500 en todo el sitio tras actualizar PHP (2026-11-17)

**Síntoma:** desde el 23-oct-2026 producción devolvía 500 en todas las rutas (`/login`, `/healthcheck`…); los archivos estáticos sí cargaban. La base no tenía nada que ver: restaurada en local, funcionaba con PHP 8.3.

**Causa:** el hosting pasó a PHP 8.4 o superior. `ErrorHandler` convertía **cualquier** aviso en excepción, incluidos los `E_DEPRECATED`, y `SessionManager` fijaba en cada petición dos directivas obsoletas desde 8.4 (`session.sid_length` y `session.sid_bits_per_character`). Reproducido con PHP 8.4.15 y 8.5.0.

**Corrección:**

- `SessionManager`: esas dos directivas solo se fijan en PHP < 8.4. Desde 8.4 se usa el valor por defecto (128 bits de entropía).
- `PdoConnection`: `Pdo\Mysql::ATTR_INIT_COMMAND` cuando existe. En PHP 8.5 `PDO::MYSQL_ATTR_INIT_COMMAND` es obsoleta y rompía el login.
- `fputcsv()` con `escape: ''` explícito (exportes de reportes, auditoría y fichas), obligatorio desde 8.4.
- **Red de seguridad:** `ErrorHandler` registra los `E_DEPRECATED`/`E_USER_DEPRECATED` en el log (`DEPRECATED: …`, una vez por petición) y deja seguir la petición. Los warnings y notices siguen fallando. Test: `tests/Unit/Kernel/ErrorHandlerTest.php`.

**Verificado:** sobre una copia de la base de producción, 396 páginas recorridas con superadmin y admin de empresa, en PHP 8.4 y 8.5, sin 500 ni avisos. Lint con PHP 8.5 de `src/`, `bin/`, `templates/` y `vendor/` sin obsolescencias de compilación.

---

## QA integral — Correcciones de la prueba end to end (2026-09-22)

Una prueba de todo el flujo (registro de aspirante → contratación → asignación, más superadmin) dejó 8 fallos y una lista de mejoras de interfaz. Se corrigen todos.

**Fallos**

1. **El alta de empresa nunca llegaba.** `RegistrarTenantService` encolaba el correo de activación, pero el despachador (`bin/notify-expirations.php`) solo atiende tenants `activo`: el tenant recién registrado queda `pendiente` y su correo no salía jamás. Ahora se envía en el momento con `Mailer` y el resultado (`correo_activacion_enviado`, `correo_error`) queda en `registros_empresa`.
2. **Los cupos del plan contaban de más.** Nuevo `Subscription\Infrastructure\CriteriosCupo`, único lugar que define qué consume cupo: usuarios activos **con rol operativo** (las cuentas de aspirante no cuentan) y **personas** con ficha vigente (por `cedula_hash`, sin anuladas, bajas ni aspirantes sin contratar). Lo usan `CalcularUsoService` y las vistas del superadmin. En GEMESEG: 37/5 usuarios → 2/5; 12 fichas → 0 guardias.
3. **Requisitos básicos con Y en lugar de O.** `CompletarFichaService` exigía bachillerato **y** trabajo anterior; la decisión vinculante #10 pide bachillerato **o** un año de experiencia. El formulario ya no llama "Opcional" a los trabajos: explica el requisito.
4. **Suscripción vencida sin aviso.** Aviso fijo en el layout para quien administra la empresa, alerta "Suscripciones vencidas" para el superadmin y marca `· vencido` en el listado de empresas. No bloquea el servicio: la decisión de suspender sigue siendo comercial.
5. **`/dashboard` sin control de acceso.** Un empleado sin permisos veía los KPIs de la empresa. Ahora los KPIs exigen `guardias.ver`, `expedientes.ver` o `reportes.ver`; el resto ve una bienvenida.
6. **Un usuario torpe bloqueaba la oficina entera.** El rate-limit contaba `email OR ip` contra el mismo umbral (5/15 min): 5 fallos desde una IP dejaban fuera a todos los usuarios detrás de ese NAT. Ahora son dos umbrales: 5 por cuenta y 30 por IP.
7. **Enlaces con token en los logs.** `Mailer` y `VerificationMailer` escribían el cuerpo del correo (con el enlace de recuperación o verificación) en `var/logs` y en `error_log`, también en producción. Nuevo `RegistroEnvioFallido`: fuera de producción guarda el contenido para poder seguir el flujo sin MTA; en producción solo metadata con el destinatario enmascarado.
8. **La hoja de Bootstrap estaba bloqueada.** Su hash SRI era incorrecto, así que el navegador la descartaba desde abril: rejilla, barras de progreso, badges, pestañas y `d-none` no tenían estilo. Se retira el enlace (layout y páginas de error) y `ds-system.css` incorpora el subconjunto que las plantillas usan de verdad (rejilla de 12 columnas, tarjetas, formularios, botones, tablas, alertas, badges, progreso, pestañas y utilidades). Sin dependencia externa de CSS.

**Interfaz**

- Registro público: confirmación de correo (un correo mal escrito dejaba al aspirante sin poder verificarse) y el botón ambiguo "Registro" pasa a "Postúlate como guardia".
- Ficha: acceso al expediente desde cualquier paso, los contadores "0/0" de fases sin obligatorios se ocultan y la barra de progreso aclara que solo mide campos del formulario.
- Expediente: títulos sin el "·" suelto, numeración correlativa (antes saltaba números al usar el campo `orden`), total real de documentos (decía 26 con 25) y el estado por archivo solo cuando el tipo admite varios.
- Documentos: se muestra el nombre de quien subió y revisó, en vez del ULID; "Tamaño" y "Número" con tilde; se rechaza un vencimiento anterior o igual a la emisión (servidor y formulario).
- Privacidad: los listados enmascaran la cédula (`******1234`); el detalle la muestra completa. Lo que no se puede descifrar dice "No disponible" en vez de `[cifrado]`.
- Guardias: distintivo Aspirante / Empleado / De baja en el listado.
- Usuarios: la contraseña inicial ya no viaja visible en el HTML (campo `password` con botón de mostrar).
- Empresa: provincia y ciudad como listas encadenadas (mismo script que clientes).
- Asignaciones: si no hay fichas COMPLETA o no hay puestos, el botón queda deshabilitado con la explicación, en lugar de redirigir con un mensaje.
- Móvil: KPIs en dos columnas compactas, tarjetas de filtros plegadas, el menú lateral ya no queda tapado por su pie y el pie muestra empresa y rol en vez de "N permisos activos".

**Segunda pasada (lo que había quedado a decisión)**

- **Guardar la ficha desde cualquier paso.** El botón ya no aparece solo en el último paso: al guardar, el formulario lleva al paso del primer campo incompleto (el manejador de submit ya lo hacía, pero el botón estaba oculto). Además, las dos referencias personales entran en el porcentaje de avance — antes marcaba 100% sin poder guardar — y los nombres de los pasos dejan de recortarse ("Historia…") ahora que la rejilla comparte ancho con el resumen.
- **Migración 036:** los tipos documentales cuyo nombre declara una cantidad ("2 fotografías tamaño carnet", "2 certificados de trabajo") pedían un solo archivo, así que el expediente daba el requisito por cumplido con una foto. Se ajusta `numero_documentos` a la cantidad del nombre (2..10), sin pisar lo que ya estaba bien configurado. Efecto: los expedientes con una sola foto vuelven a contarse como incompletos, que es el estado real.
- **Nuevo `bin/diagnostico-cifrado.php`:** de solo lectura, informa cuántos valores no se descifran con la `ENCRYPTION_KEY` actual (nunca imprime valores ni claves) e indica el procedimiento con `recifrar-datos.php`. En la base local: 26 valores (6 cédulas de ficha, 12 cuentas bancarias y 8 cédulas de familiares) siguen cifrados con la clave anterior.

**Migración 035** concede `suscripcion.ver` a los roles con `tenants.ver` (la 007 lo hizo antes de que `admin_tenant` tuviera ese permiso, así que el administrador de la empresa no podía ver su propio plan). `suscripcion.gestionar` sigue siendo del administrador de la plataforma.

---

## Operación — Cambio de clave de cifrado (2026-09-16)

- **Nuevo `bin/recifrar-datos.php --dry-run|--apply`:** pasa a la `ENCRYPTION_KEY` actual todo lo cifrado con `ENCRYPTION_KEY_ANTERIOR`.
  - **Columnas:** cédula y cuenta bancaria de fichas, cédula de familiares, cédula y MFA de usuarios, staging GEMESEG (clave por tenant + AAD).
  - Recalcula `cedula_hash` al recifrar.
  - Lo que ya descifra con la clave actual no se toca; lo que no descifra con ninguna se reporta.
- Sirve para rotar la clave o para una base copiada entre entornos con claves distintas. Procedimiento en `docs/PRODUCTION_DEPLOY.md`.

---

## Reclutamiento — Corrección de datos del aspirante (2026-09-16)

Un postulante que escribe mal su correo nunca recibe el enlace de verificación y queda bloqueado en estado `nuevo`. Reclutamiento ahora puede corregir sus datos (rectificación LOPDP).

- **Rutas:** `GET|POST /reclutamiento/{id}/editar` (permiso `aspirantes.gestionar`). Botón **Editar** en el listado y **Editar datos** en el detalle, solo en estados `nuevo`, `aspirante`, `en_revision` y `contactado`.
- **`ActualizarAspiranteService`:**
  - Aplica las mismas reglas que el registro público, extraídas a `ValidadorDatosAspirante`.
  - El correo nuevo se confirma dos veces y no puede pertenecer a otro aspirante ni a otro usuario.
  - Sin verificar: emite un token nuevo (48 h) que invalida el anterior y se envía el enlace al correo corregido. También se puede reenviar sin cambiar datos.
  - Con usuario: el correo de inicio de sesión, los nombres y el teléfono se actualizan en la misma transacción.
  - El documento queda bloqueado si el aspirante ya creó su ficha.
- **Trazabilidad:**
  - Auditoría `aspirante.datos_actualizados`, con correo anterior y nuevo. Queda en nivel `warning` si cambia el login de una cuenta existente.
  - Evento `datos_actualizados` en `aspirante_eventos`.
  - **Migración 034:** amplía el ENUM de eventos.
- **UI:**
  - Formulario con validación en vivo de la confirmación de correo y avisos contextuales.
  - Nuevo componente `.ds-callout` en `ds-system.css`, que reemplaza en el detalle las alertas `login-*` (no tenían estilo dentro del layout interno).
- **Tests:** `ActualizarAspiranteServiceTest` (20 casos).

---

## Seguridad — Blind index HMAC para cédulas (2026-09-16)

`cedula_hash` era `sha256(cedula)` sin clave: con ~1.5e8 cédulas válidas, un dump de la BD revelaba todas por fuerza bruta aunque `cedula_cifrada` use AES-256-GCM (riesgo LOPDP).

- **Nuevo `Security\Crypto\BlindIndex`:** HMAC-SHA256 con subclave HKDF de `ENCRYPTION_KEY` (`Encryption::blindIndexer()`), normaliza a dígitos y separa dominio (`cedula:`). Índice global a propósito: los flujos de aspirantes mueven fichas entre tenants.
- **Se elimina `Cedula::hash()`.** Usan el índice: `CrearFichaService`, `ActualizarFichaService`, `NormalizarFamiliaresFicha`, `FichaController` (búsqueda y prefill), `LopdpController` (búsqueda de titular), importación GEMESEG (web y CLI). El procesador de staging recalcula el índice desde la cédula descifrada.
- **Nuevo `bin/rehash-cedulas.php --dry-run|--apply`:** recalcula `fichas`, `fichas_familiares`, `usuarios` e `importacion_excel_registros` desde el dato cifrado; idempotente; reporta filas no descifrables sin tocarlas.
- `bin/repair-encrypted-cedulas.php` acepta el hash vigente o el legado y deja el índice HMAC al reparar.
- **LOPDP:** `bin/anonimizar-retencion.php` ahora también borra `fichas_familiares` (cédula del cónyuge y datos de padres) al vencer la retención.
- Tests: `BlindIndexTest`, `NormalizarFamiliaresFichaTest`.

**Despliegue:** ejecutar `php bin/rehash-cedulas.php --apply` inmediatamente después de publicar el código (ver `docs/PRODUCTION_DEPLOY.md`).

---

## Mantenimiento — Integridad de migraciones y tests (2026-09-16)

- **Checksums estables entre entornos:** `Migration::fromFile()` normaliza CRLF→LF antes de calcular el sha256, y `.gitattributes` fuerza `eol=lf` en `*.sql`. Git en Windows (`core.autocrlf=true`) ya no produce `CHECKSUM_MISMATCH` falsos.
- **Collation `utf8mb4_unicode_ci` aplicable con FKs:** 017, 018 y 019 desactivan `FOREIGN_KEY_CHECKS` solo durante la conversión (evita el error 3780). La 018 convierte además, si existen, las tablas de aspirantes y catálogos creadas fuera de orden (evita el 1267 en 027/031).
- **026** declara `utf8mb4_unicode_ci` (antes `0900_ai_ci`, no soportado en MariaDB).
- **025** ya no falla en instalación limpia sin tenants (FK `fk_rp_role`).
- **Nueva 033:** normaliza las tablas posteriores a la 018 y la collation por defecto de la base.
- **Tests:** se corrigieron 17 tests desfasados del código (`AuthenticatedUser::$cargo`, `tenantGemeseg()`, `tipoId` plano en rutas de expediente) y se agregó `MigrationTest`. La caché de PHPUnit deja de versionarse.

**Al desplegar en un entorno donde 017/018/019/025/026 ya estaban aplicadas:** ejecutar `php bin/repair-migration-checksums.php` (revisar) y luego `--apply`, antes de `php bin/migrate.php migrate`.

---

## Fase 16 — Módulo Clientes + Sistema de diseño consolidado (2026-04-17 → 2026-05-06)

### Nueva funcionalidad: Módulo Clientes

Bloque base para los siguientes módulos operativos (puestos, turnos, asignaciones).

- **Migración:** `010_clientes.sql`
  - Tabla `clientes` con identidad (RUC/cédula), contacto, domicilio, sector, estado (activo/inactivo/archivado), audit fields (`archivado_at`, `archivado_por`).
  - Índices: `(tenant_id, estado)`, `(tenant_id, razon_social)`, **UNIQUE `(tenant_id, documento)`**.
  - 4 permisos sembrados: `clientes.ver|crear|editar|archivar` repartidos por rol.
- **Domain:** [`ValidadorRuc::esCedulaValida()`](src/Tenant/Domain/ValidadorRuc.php) — método nuevo para validar cédula ecuatoriana (10 dígitos, módulo 10 Registro Civil).
- **Capas:**
  - [`ClienteRepository`](src/Cliente/Infrastructure/ClienteRepository.php) extiende `BaseRepository`.
  - [`GestionarClientesService`](src/Cliente/Application/GestionarClientesService.php) con `crear/actualizar/cambiarEstado` + auditoría (`cliente.creado|actualizado|archivado|estado_cambiado`).
  - [`ClienteController`](src/Cliente/Controller/ClienteController.php) con flash messages.
- **Vistas:** `templates/clientes/{index,create,edit,show}.php`.
- **Rutas:** `/clientes/*` (7 endpoints).
- **Menú:** Sección "Operación" inicia con "Clientes" antes de "Guardias".

### Sistema de diseño visual extraído a archivos externos

- **`public/assets/css/ds-system.css`** (~33 KB) — sistema completo del shell autenticado con tokens, componentes `ds-*` documentados en 9 secciones.
- **`public/assets/css/ds-auth.css`** (~16 KB) — pantallas públicas standalone (login, registro, forgot/reset, gracias/activado/fallida).
- Layout `templates/layout/app.php` redujo de 1188 → ~465 líneas.
- Componentes establecidos: `.ds-page-header`, `.ds-card` con `.ds-card-header.tone-*`, `.ds-table-card`, `.ds-table` con `.ds-cell-*`, `.ds-badge` con tonos, `.ds-kpi-card`, `.ds-empty-state`, `.ds-filter-bar`, `.ds-form-field`, `.ds-btn` con variantes, `.ds-toast`, `.ds-bell-menu`, `.ds-progress`, `.ds-chip`.

### Migración masiva de templates al sistema `ds-*`

Más de 40 templates migrados (cards, tablas, badges, kpis, formularios, empty states, paginación):

**Pantallas públicas:** login, registro/formulario, registro/gracias, registro/activado, registro/activacion-fallida, auth/forgot-password, auth/reset-password.

**Errores:** errors/404, errors/403, errors/500.

**Listados:** user/index, ficha/index, expediente/index, roles/index, notificaciones/index, auditoria/index, superadmin/tenants, documento-tipos/index, lopdp/solicitudes/index, lopdp/consentimientos/index, superadmin/registros.

**Detalle/show:** ficha/show, user/profile, tenant/show, roles/show, notificaciones/show, expediente/dashboard, expediente/documento, lopdp/solicitudes/show, lopdp/index, suscripcion/show, superadmin/dashboard, superadmin/tenant-detalle, clientes/show.

**Forms:** user/create, user/edit, user/change-password, tenant/edit, roles/create, roles/edit, documento-tipos/edit, suscripcion/nuevo-ciclo, lopdp/solicitudes/create, expediente/upload, clientes/create, clientes/edit.

### Adopción de FlashBag end-to-end

Todos los controllers principales ahora emiten toasts en éxito/error tras redirect:

- UserController, SuperadminController (Fase 15)
- FichaController, RoleController, TenantController, SuscripcionController (Fase 16)
- LopdpController, DocumentoTipoController, NotificacionController, ExpedienteController, ClienteController (Fase 16)

Patrón consolidado:
```php
try {
    $this->service->hacerAlgo(...);
    $this->flash->success('Acción completada.');
} catch (DomainException $e) {
    $this->flash->error($e->getMessage(), 'No se pudo completar');
}
return Response::redirect('/destino');
```

### Pendientes diferidos en esta fase

- `templates/ficha/create.php` y `templates/ficha/edit.php` — stepper JS complejo de 4 pasos (960 + 510 líneas), no migrados para evitar romper navegación.
- `templates/dashboard/index.php`, `reporting/kpis.php`, `reporting/exportar.php` — funcionan con CSS propio, charts complejos.

---

## Fase 15 — Mensajes flash + toasts + páginas de error

- **`FlashBag`** (`src/Kernel/Http/FlashBag.php`) con `success/error/warning/info/pull` sobre `SessionManager`.
- **`FlashMiddleware`** extrae los flashes en GETs y los publica al layout vía `ViewRenderer::share('flashes', ...)`.
- **`ViewRenderer::share/shared`** para datos transversales sin tocar cada controller.
- Toasts custom `.ds-toast` arriba-derecha con animación + auto-dismiss 5.5s.
- Páginas de error 404/403/500 rediseñadas con identidad propia (gradient, marca, código, CTAs).
- Adopción inicial en UserController y SuperadminController.

---

## Fase 14 — Centro de alertas en topbar

- **`AlertasCentroService`** (`src/Notification/Application/`) agrega items accionables del usuario actual.
- Para tenant: documentos vencidos/por vencer, fichas borrador, solicitudes LOPDP pendientes, suscripción por vencer.
- Para superadmin: tenants pendientes/suspendidos/venciendo, registros fallidos.
- **`AlertasLayoutMiddleware`** publica el bundle al layout.
- Campana en topbar con badge de conteo + dropdown categorizado por severidad (`crítica|alta|media|info`).

---

## Fase 13 — Panel Superadmin

- Migración `009_superadmin_panel.sql` con permisos `superadmin.*`.
- Dashboard global con KPIs, charts, registros recientes y acciones rápidas.
- CRUD de tenants global (estado, plan), log de registros públicos.
- Templates: `templates/superadmin/{dashboard,tenants,tenant-detalle,registros}.php`.

---

## Fase 12 — Activación de cuenta

- Token de activación SHA256 con expiración 48h.
- Email transaccional con enlace `/activar/{token}`.
- Flujos: `gracias.php` (post-registro) → email → `activado.php` o `activacion-fallida.php`.

---

## Fase 11 — Auto-registro público

- Migración `008_registro_publico.sql` con tabla `registros_empresa` (audit trail).
- Formulario público en `/registro` con planes + datos empresa + admin principal.
- Trial gratis 30 días automático al registrar.
- Validación de RUC con algoritmo SRI (módulo 10/11) en `ValidadorRuc`.
- Rate limiting: 5 creaciones/hora por IP.

---

## Fases 1-10 — Construcción del core

| Fase | Migración | Módulo |
|------|-----------|--------|
| 1-2  | `001_initial_security_core.sql`, `002_fichas_guardia.sql`, `003_expediente_documental.sql` | Seguridad base, Fichas, Expediente |
| 3    | (en `001`)                                                                              | Reportes/KPIs                      |
| 4    | (en `001`)                                                                              | Usuarios + Auditoría + Roles       |
| 5    | `004_notificaciones.sql`                                                                | Notificaciones (Email/WhatsApp/SMS) |
| 6    | `005_lopdp_arcop.sql`                                                                   | LOPDP + PDFs (lealtad, huellas)    |
| 7    | (en `001`)                                                                              | Empresa/Tenant                     |
| 8-9  | `006_permisos_fase8_9.sql`                                                              | Configuración docs + CRUD roles    |
| 10   | `007_suscripciones.sql`                                                                 | Suscripción + descarga documentos  |

---

## Próximas fases naturales

- **Fase 17 — Puestos/Sitios**: ubicaciones físicas con `cliente_id` (FK), dirección, coordenadas, tipo (oficina, planta, residencial).
- **Fase 18 — Asignaciones**: vincular guardia a puesto en un periodo.
- **Fase 19 — Turnos**: rotaciones por puesto con horarios.
- **Fase 20 — Asistencia/Check-in**: registro de presencia con geolocalización.

---

## Cómo aplicar las migraciones

```bash
# Ver estado
php bin/migrate.php status

# Aplicar pendientes
php bin/migrate.php migrate

# Revertir la última
php bin/migrate.php rollback
```

## Cómo correr tests

`vendor/` se versiona sin dependencias de desarrollo (despliegue Git-only en cPanel). PHPUnit y PHPStan se instalan aparte, en `.devtools/` (ignorado por Git):

```bash
COMPOSER_VENDOR_DIR=.devtools/vendor composer install
php .devtools/vendor/bin/phpunit --bootstrap .devtools/vendor/autoload.php
```

## Healthcheck

```bash
curl http://localhost/digisecure/public/healthcheck
# {"status":"ok","service":"DigiSecure","phase":"16.0",...}
```
