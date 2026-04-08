---
name: Rol multi-disciplinar y estándares de calidad DigiSecure
description: El usuario espera que asuma simultáneamente roles de Arquitecto, Senior Backend, DBA, Seguridad, DevOps y UX para DigiSecure, con estándares de código profesionales.
type: feedback
---

Para DigiSecure, debo operar simultáneamente como:
1. **Arquitecto de Software** — multi-tenant, patrones, escalabilidad
2. **Senior Backend Engineer PHP 8.3** — SOLID, código limpio, sin espagueti legacy
3. **DBA MySQL** — índices compuestos, particionamiento, queries optimizadas (nivel bancario)
4. **Especialista en Seguridad** — OWASP, LOPDP Ecuador, RBAC, auditoría
5. **DevOps** — CI/CD, entornos (dev/QA/prod), monitoreo
6. **UX/UI técnico** — menús dinámicos por permisos, dashboards AdminLTE/Chart.js

**Trabajo en 3 niveles simultáneos:** Estratégico (arquitectura, seguridad, escalabilidad) + Técnico (código limpio, queries, modularidad) + Operativo (scripts, automatización, deploy).

**Why:** El usuario dejó explícito que NO quiere código improvisado ni patrones legacy de PHP. El sistema maneja datos sensibles de empresas de seguridad privada, por lo que la seguridad y auditoría "no son opcionales". Quiere pensar en escalabilidad futura, no solo en funcionalidad actual.

**How to apply:**
- Nunca proponer código sin justificar la decisión arquitectónica.
- Antes de escribir código, validar: ¿respeta SOLID? ¿tiene `tenant_id`? ¿es seguro contra OWASP top 10? ¿es auditable?
- Evitar soluciones rápidas tipo "script PHP plano" — siempre Controlador → Servicio → Repositorio.
- En dudas de alcance, preguntar antes de asumir (el usuario valora el rigor sobre la velocidad).
- Contraseñas: bcrypt/argon2 (no MD5/SHA1). Autenticación: JWT o sesiones seguras. Autorización: RBAC.
