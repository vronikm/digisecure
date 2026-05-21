# DigiSecure - Blueprint Arquitectura + Seguridad + Operación

Versión: 1.0  
Fecha: 2026-05-20  
Estado: Activo

## 1) Resumen ejecutivo

El proyecto ya tiene una base sólida para SaaS multi-tenant:

- Arquitectura por capas (Controller -> Service -> Repository).
- Aislamiento por `tenant_id` en repositorios críticos.
- RBAC, CSRF, sesión segura, auditoría y módulo LOPDP.
- Módulos principales de negocio implementados.

También tiene brechas concretas para cerrar antes de escalar:

- Incompatibilidad de stack declarado: **AdminLTE 3.x** no es compatible nativamente con **Bootstrap 5.x**.
- Falta de CI/CD formal en repositorio.
- Endurecimiento adicional de cifrado contextual (tenant/session-bound tokens para URLs internas).
- Deuda técnica puntual detectada en reportes/exportaciones (corregida en esta iteración).

## 2) Diagnóstico por rol

## 2.1 Arquitecto de software

Fortalezas:

- Multi-tenant en BD compartida con `TenantContext` y `BaseRepository`.
- Separación de capas y módulos de dominio.
- Pipeline middleware coherente para seguridad y autorización.
- CQRS de lectura en reporting/KPIs.

Brechas:

- Conviene formalizar “arquitectura de decisiones” como ADRs versionados por cambio.
- Algunas consultas de lectura de alto volumen no están encapsuladas en read-models dedicados.
- Mecanismo de URLs seguras cifradas por sesión aún no está implementado end-to-end.

## 2.2 Senior Backend PHP 8.3

Fortalezas:

- Código tipado, servicios desacoplados, repositorios claros.
- Manejo centralizado de errores y routing limpio.

Brechas:

- Puntos con SQL “manual” fuera de `BaseRepository` requieren revisión continua de aislamiento.
- Debe endurecerse política para evitar drift entre schema SQL y consultas de reportes.

## 2.3 DBA MySQL 8.0+

Fortalezas:

- Diseño tenant-aware en la mayor parte de tablas de negocio.
- Índices compuestos adecuados para filtros frecuentes.
- Uso de InnoDB y `utf8mb4`.

Brechas:

- Faltan prácticas operativas de performance sistemática:
  - `EXPLAIN ANALYZE` periódico para queries KPI.
  - baseline de latencias por endpoint.
- Particionamiento aún no aplicado en tablas candidatas de crecimiento masivo (`auditoria`, `notificaciones_log`).
- Falta de estrategia formal de archivado y purga para tablas de log.

## 2.4 Seguridad (OWASP + LOPDP Ecuador)

Fortalezas:

- Prepared statements centralizados en capa PDO.
- CSRF, control de sesión, rate limit de login, auditoría.
- Hash de contraseñas con Argon2id.
- Cifrado AES-256-GCM para datos sensibles.

Brechas:

- Faltan controles de seguridad operativa “de plataforma”:
  - CSP estricta (actualmente no definida de forma explícita).
  - HSTS habilitado en producción (aún comentado en `.htaccess`).
- URLs internas todavía exponen IDs en texto plano.
- Endurecer rotación de claves y proceso de re-cifrado por tenant.

## 2.5 DevOps / Plataforma

Fortalezas:

- Scripts CLI útiles (`migrate`, jobs de vencimientos, anonimización).

Brechas:

- No hay pipeline CI/CD versionado en repo.
- Falta checklist de release (migración, smoke tests, rollback).
- Falta observabilidad estandarizada (errores, métricas, tiempos de query, alertas de SLA).

## 2.6 UX/UI técnico

Fortalezas:

- Menú dinámico por permisos ya implementado.
- Dashboard y KPIs con Chart.js.

Brechas:

- Debe cerrarse decisión de design system:
  - mantener interfaz custom actual, o
  - migrar formalmente a AdminLTE (versión compatible con Bootstrap definido).

## 3) Hallazgos críticos priorizados

P0 - Stack inconsistente:

- Se solicitó AdminLTE 3.x + Bootstrap 5.x.
- AdminLTE 3.x está basado en Bootstrap 4.x; para Bootstrap 5.x corresponde AdminLTE 4.x.

P0 - Seguridad de URLs:

- Aún no existe implementación integral de tokens URL cifrados por sesión para recursos internos.

P1 - Operación:

- Sin pipeline CI/CD formal en repositorio.

P1 - Observabilidad:

- No hay esquema central de métricas de aplicación + DB para detectar degradación temprana.

P2 - Gobierno de datos:

- Falta plan operativo de particionamiento/archivado por crecimiento real.

## 4) Correcciones aplicadas en esta iteración

1. Reportes CSV alineados al esquema real de BD.
2. Rotación de token CSRF al iniciar sesión (cambio de contexto anónimo -> autenticado).

## 5) Blueprint objetivo (3 niveles simultáneos)

## 5.1 Nivel estratégico

- Mantener BD compartida con aislamiento estricto por `tenant_id`.
- Módulos críticos bajo DDD selectivo:
  - Seguridad
  - Documentación/Expediente
- CQRS para reporting con read-models optimizados y desacoplados del write-model.
- Política de seguridad “defensa en profundidad” con trazabilidad completa.

## 5.2 Nivel técnico

- Patrón estable:
  - Controller: I/O HTTP
  - Service: casos de uso
  - Repository: persistencia
- Contrato obligatorio multi-tenant:
  - Toda tabla de negocio con `tenant_id`.
  - Toda consulta de negocio validada por `tenant_id`.
- URL security:
  - Token cifrado AES-256-GCM por sesión.
  - TTL corto + nonce + binding a usuario + tenant + ruta + método.

## 5.3 Nivel operativo

- CI:
  - php lint
  - phpunit unit
  - phpstan nivel estricto
- CD:
  - deploy por ambiente (Dev/QA/Prod)
  - migraciones controladas
  - smoke tests post-deploy
- SRE básico:
  - logs estructurados
  - alertas de error rate
  - monitoreo de p95 endpoint y queries críticas

## 6) Plan por fases (recomendado)

Fase A (1-2 semanas) - Endurecimiento inmediato:

- Definir stack final UI (AdminLTE 4 + Bootstrap 5 recomendado).
- Implementar servicio de URL segura AES-256-GCM por sesión (MVP en módulos críticos).
- Activar controles de seguridad de cabecera para producción (CSP + HSTS con rollout).
- Pipeline CI inicial en repo.

Fase B (2-4 semanas) - Rendimiento y estabilidad:

- Inventario de queries pesadas + `EXPLAIN ANALYZE`.
- Índices adicionales donde aplique según carga real.
- Read-models dedicados para KPIs con queries predecibles.
- Métricas de auditoría y notificaciones.

Fase C (4-6 semanas) - Escalabilidad operativa:

- Estrategia de particionamiento en tablas de alto crecimiento.
- Política de archivado/purga (auditoría y logs de notificaciones).
- Plan de continuidad: backup, restore drill, RPO/RTO objetivo.

## 7) Decisión técnica clave de stack UI

Como de 2026-05-20:

- AdminLTE 3.x está orientado a Bootstrap 4.x.
- Bootstrap 5.x se alinea con AdminLTE 4.x.

Recomendación técnica:

- Para mantener Bootstrap 5.x, adoptar AdminLTE 4.x.
- Si se exige AdminLTE 3.x, entonces congelar en Bootstrap 4.6 y aceptar deuda de modernización.

## 8) Criterios de aceptación para la siguiente entrega

- URL segura cifrada por sesión funcionando en al menos:
  - Guardias (detalle/edición)
  - Expedientes (detalle/subida)
- Pipeline CI ejecutando tests + análisis estático.
- Documento de hardening de producción (headers, cookies, TLS, secrets).
- Matriz de permisos/menú revisada por rol y validada con QA.

## 9) Checklist de control (auditoría continua)

- [ ] Toda query de negocio filtra por tenant.
- [ ] No hay SQL dinámico con columnas no whitelist.
- [ ] No hay salida HTML sin escape.
- [ ] Todo POST/PUT/DELETE requiere CSRF válido.
- [ ] Toda acción sensible queda en auditoría.
- [ ] Toda exportación está probada contra esquema vigente.
- [ ] Jobs críticos tienen idempotencia y métricas.

