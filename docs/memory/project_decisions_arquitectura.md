---
name: DigiSecure - Decisiones arquitectónicas confirmadas
description: Decisiones técnicas y de negocio que el usuario aprobó explícitamente para DigiSecure. Son vinculantes para todo el desarrollo.
type: project
---

**Decisiones arquitectónicas (vinculantes):**

1. **Aislamiento multi-tenant:** `BaseRepository` que inyecta el filtro `tenant_id` automáticamente desde el contexto de sesión. No se permite query a tablas tenant-aware sin pasar por el repositorio base.
2. **Identificación de tenant:** **solo sesión post-login**. NO subdominios, NO path. El tenant se resuelve después de autenticar al usuario y se inyecta en el contexto del request.
3. **Routing:** **vanilla PHP 8.3 propio** — sin FastRoute, sin Symfony Routing. Implementación propia con clase `Router` mínima y segura.
4. **Acceso a datos:** **vanilla PHP 8.3 propio** — PDO directo + repositorios manuales. NO Doctrine DBAL, NO ORM.
5. **Autenticación:** **vanilla PHP 8.3 propio** — sesiones nativas PHP con cookies `HttpOnly`, `Secure`, `SameSite=Strict`. `password_hash` con `PASSWORD_ARGON2ID`. NO JWT.
6. **Estructura de carpetas:** PSR-4, propuesta aprobada (src/Kernel, src/Shared, src/Security, src/Tenant, etc.).
7. **LOPDP Ecuador:** cumplimiento obligatorio desde día 1 (consentimientos, bitácora ARCO-P, retención).

**Decisiones de negocio (vinculantes):**

1. **Foto del guardia:** upload de archivo (sin webcam por ahora).
2. **Huellas dactilares:** NO se capturan digitalmente. El sistema debe **generar e imprimir un formato físico** (PDF) donde las huellas se toman a mano con tinta.
3. **Firma:** NO hay firma digital.
4. **Croquis:** se toma desde **Google Maps** (integración con API de Google Maps).
5. **Declaración de lealtad:** plantilla dinámica por tenant, **se descarga del sistema** con los datos del guardia y la empresa rellenados (PDF).
6. **Aislamiento entre tenants:** total. Un guardia es entidad independiente por tenant. NO compartir información entre empresas.
7. **Correlativo de ficha:** generado automáticamente + prefijo/formato configurable por usuario tenant (ej: `GEM-2026-0001`).
8. **REINGRESO:** siempre genera ficha nueva (no se reactiva la anterior). Se debe poder enlazar la nueva con las anteriores del mismo guardia (mismo `cedula_hash` dentro del tenant).
9. **Retención de datos:** **5 años** tras la baja del guardia, después se anonimiza automáticamente.
10. **Requisitos básicos:** **bloqueo duro**. Si un guardia no cumple edad 20-45, estatura mínima por género, bachiller o ≥1 año experiencia, el sistema impide marcar la ficha como COMPLETA. Sin excepción ni override.
11. **Carpeta color crema (doc físico #2):** ELIMINADA del expediente digital. Solo se gestionan los 26 documentos digitales restantes.
12. **Campo `genero`:** valores Masculino / Femenino / Otro / Prefiero no decir. La regla de estatura mínima (1.65m H, 1.50m M) solo aplica cuando es Masculino o Femenino. Para "Otro" y "Prefiero no decir" se debe pedir confirmación al usuario sobre qué umbral aplicar (decisión pendiente con usuario cuando se llegue al caso).
13. **WhatsApp:** proveedor **Meta WhatsApp Cloud API** (gratis hasta cierto volumen). Email vía **PHPMailer** sobre SMTP autenticado.
14. **Notificaciones de vencimiento:** se envían a **ambos**, guardia y administrador del tenant. Canales: email y WhatsApp. Recordatorios: 30/15/7 días antes (mínimo 15 según el usuario, los otros umbrales son recomendación pendiente de aprobar).
15. **PHPMailer:** aprobado como única dependencia externa de email para Fase 0/1.
16. **Validación externa de documentos:** opción A confirmada — el revisor visita el sitio externo en otra pestaña, captura, sube las 2 imágenes manualmente. NO scraping ni integración con APIs gubernamentales.
17. **Catálogo de los 27 documentos:** pre-cargado como semilla, configurable por tenant (puede desactivar/agregar). Tras decisión #11, son 26 digitales (sin la carpeta crema).
18. **Notificaciones de vencimiento — alertas:** confirmado motor de notificaciones desde Fase 0/1, no agregado posterior.
19. **Campo `genero` agregado a la Ficha:** los 46 campos originales no lo incluían, ahora son 47.
20. **Expediente en 2 bloques con dependencia secuencial:** Bloque A (Admisibilidad, 26 docs) bloquea a Bloque B (Vinculación laboral, 4 docs: contrato, IESS, SUT, acta uniformes). B se desbloquea solo cuando A está 100% ACEPTADO. Total inicial: 30 tipos de documento configurables por tenant.
21. **Múltiples actas de entrega-recepción de uniformes:** permitidas e historial conservado completo. Un guardia puede recibir uniforme inicial, reposición por desgaste, reposición por baja, etc. Todas se guardan con su fecha.
22. **Vigencias semilla por tipo de documento:** el sistema trae valores por defecto razonables (reentrenamiento 730d, antecedentes penales 90d, exámenes médicos 365d, etc.) pero cada tenant puede sobreescribirlos en su configuración. Documentos sin vencimiento explícito (LOPDP, hoja de vida, fotos, contratos) se marcan como `tiene_vencimiento=false`.
23. **Fecha objetivo de actualización:** se calcula automáticamente por el sistema (X días antes del vencimiento, configurable) pero el revisor puede sobreescribirla manualmente caso por caso al rechazar o pedir renovación.

**Why:** Estas decisiones surgen de una conversación detallada con el usuario donde tomó posición clara en cada punto. Cambiar cualquiera de ellas requiere su autorización explícita.

**How to apply:**
- En cualquier diseño nuevo, validar primero que respeta estas 16 decisiones.
- Si una decisión técnica entra en conflicto con un requerimiento futuro, pausar y consultar al usuario antes de desviarse.
- "Vanilla PHP 8.3" no significa "código improvisado" — significa usar primitivas nativas (`password_hash`, `random_bytes`, `openssl_encrypt`, PDO con prepared statements) con disciplina y patrones, sin frameworks ni librerías de terceros para routing/dbal/auth. Sí se permiten librerías muy puntuales y bien justificadas (ej: PHPUnit para tests, dompdf/mpdf para generación de PDF, librería oficial de Google Maps).
