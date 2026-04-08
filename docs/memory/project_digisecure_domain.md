---
name: DigiSecure - Dominio de Negocio (Ficha del Guardia)
description: Modelo de dominio central de DigiSecure — la ficha de ingreso de guardias de seguridad y sus secciones.
type: project
---

**Propósito del sistema:** Administración de fichas y documentación de guardias de seguridad, para empresas de seguridad privada en Ecuador. Modelo SaaS multi-tenant por suscripción.

**Agregado raíz: Ficha del Guardia** (46 campos, agrupados en 11 secciones):

1. **Cabecera:** fecha emisión, fecha revisión, tipo de ficha (INGRESO/REINGRESO/ACTUALIZACIÓN), foto.
2. **Datos Personales:** nombres, fecha y lugar de nacimiento, **cédula (validación algoritmo Ecuador)**, estatura, celular, tipo de sangre, cuenta bancaria (banco, tipo Ahorros/Corriente, número), email.
3. **Dirección Domiciliaria:** ciudad, tipo de vivienda (Propia/Arrendada/Familiar), calle, número, barrio, referencia, teléfono domicilio, contacto emergencia + teléfono.
4. **Estudios:** primaria, secundaria, superior, cursos (multi-select: NIVEL 1, NIVEL 2, REENTRENAMIENTO, SUPERVISOR, CONSOLAS), fecha último reentrenamiento.
5. **Trabajos anteriores** (1:N): empresa, dirección, teléfonos, tiempo.
6. **Referencias personales:** padre, madre, hermano/a (nombre, dirección, teléfono cada uno).
7. **Estado civil / Cargas familiares:** CASADO/UNION LIBRE/DIVORCIADO/VIUDO.
8. **Hijos menores de 18** (1:N): nombre, edad, justifica con cédula (SI/NO).
9. **Croquis del domicilio:** dibujo (canvas → imagen).
10. **Huellas dactilares:** 10 dedos (5 derecha + 5 izquierda) — dato biométrico.
11. **Firma del trabajador + Declaración de Lealtad** (texto legal con nombre de la empresa tenant, debe ser plantilla dinámica — NO hardcodear "GEMESEG").

**Datos sensibles bajo LOPDP Ecuador (máxima protección):**
- Biométricos: foto, huellas dactilares, firma
- Salud: tipo de sangre
- Financieros: cuenta bancaria
- Menores: hijos <18 años (doble consentimiento requerido)
- Ubicación exacta: croquis + dirección completa

**Why:** Este es el core del sistema. Todo el resto (usuarios, roles, suscripciones, auditoría) existe para servir este agregado. La LOPDP obliga a cifrado en reposo, consentimiento explícito, bitácora de accesos, y políticas de retención sobre todos los campos sensibles listados arriba.

**How to apply:**
- Modelar como agregado DDD con value objects: `Cedula`, `Email`, `Telefono`, `CuentaBancaria`, `FechaNacimiento`.
- Normalizar: `fichas_trabajos_anteriores`, `fichas_hijos_menores`, `fichas_cursos` (N:M con catálogo).
- Biométricos (foto, huellas, firma, croquis): NO almacenar inline en MySQL. Usar storage cifrado (filesystem con cifrado AES-256-GCM) y guardar solo referencias + hash.
- La declaración de lealtad debe leer nombre de empresa desde `tenants.razon_social`.
- Cédula: algoritmo módulo 10 con coeficientes (2,1,2,1,2,1,2,1,2), validar provincia (dígitos 1-2 entre 01-24) y tercer dígito < 6 para personas naturales.
