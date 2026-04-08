---
name: DigiSecure - Proyecto SaaS Multi-tenant
description: Sistema SaaS para empresas de seguridad privada en Ecuador. Contexto general, stack y decisiones arquitectónicas.
type: project
---

**DigiSecure** es un SaaS multi-tenant dirigido a empresas de seguridad privada en Ecuador. Directorio de trabajo: `c:\wamp64\www\digisecure` (WAMP local, actualmente vacío — proyecto desde cero).

**Stack confirmado:**
- PHP 8.3
- MySQL 8.0+
- AdminLTE 3.x (última versión)
- Bootstrap 5.x
- Chart.js 4.x
- Cifrado de URLs: AES-256-GCM con tokens únicos por sesión

**Arquitectura multi-tenant:**
- Base de datos compartida con aislamiento por `tenant_id`
- Todas las tablas deben incluir `tenant_id` e índices compuestos (`tenant_id` + campos de búsqueda)

**Módulos previstos:**
- Usuarios
- Roles y permisos (RBAC)
- Empresas (tenants)
- Documentación
- Auditoría (logs quién/qué/cuándo)
- Suscripciones

**Patrones a aplicar:**
- MVC + separación por capas (Controlador → Servicio → Repositorio)
- DDD selectivo en módulos críticos (seguridad, documentos)
- CQRS para reportes/KPIs

**Why:** Sistema crítico para empresas de seguridad → datos sensibles, auditoría obligatoria, cumplimiento de Ley Orgánica de Protección de Datos Personales de Ecuador (LOPDP).

**How to apply:** Cada decisión técnica debe evaluarse contra: (1) aislamiento multi-tenant, (2) seguridad OWASP, (3) cumplimiento LOPDP Ecuador, (4) escalabilidad futura — no solo funcionalidad actual.
