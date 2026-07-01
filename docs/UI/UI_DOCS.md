# Documento de UI — Arquitectura SaaS Multitenant B2B2C
> **Stack:** Laravel + Blade (server-side rendering) | **Versión:** 1.0

---

## Convenciones del Documento

### Contextos Visuales

Este sistema tiene **tres contextos visuales** distintos con identidad y audiencia diferente:

| Contexto               | Ruta base                   | Audiencia                    | Densidad                                       |
| ---------------------- | --------------------------- | ---------------------------- | ---------------------------------------------- |
| **Host / Backoffice**  | `app.plataforma.com/host/*` | Super-admins internos        | Alta — tablas, métricas, formularios complejos |
| **Tenant App**         | `{tenant}.plataforma.com/*` | Admins y usuarios del tenant | Media — respeta white-label del tenant         |
| **Público / Landings** | `plataforma.com/*`          | Visitantes y leads           | Baja — marketing, conversión                   |

### Notación de Estados de Pantalla

Cada pantalla documenta sus estados posibles:
- **Vacío:** primer uso, sin datos
- **Cargando:** skeleton loaders o spinners
- **Con datos:** estado nominal
- **Error:** mensajes de falla accionables
- **Restringido:** usuario sin permiso para ver/editar

### Componentes Blade Globales

Los siguientes componentes se reutilizan en múltiples módulos y se documentan una sola vez aquí:

- `<x-layout.host>` — Shell del backoffice (sidebar fijo, topbar con usuario activo, breadcrumbs)
- `<x-layout.tenant>` — Shell del tenant (sidebar con logo white-label, topbar con tenant name)
- `<x-layout.public>` — Shell público (navbar de marketing, footer legal)
- `<x-table>` — Tabla genérica con paginación, búsqueda, ordenamiento y acciones por fila
- `<x-modal>` — Modal con confirmación destructiva opcional
- `<x-alert>` — Banners de éxito, error, advertencia e información
- `<x-badge>` — Estado visual codificado por color (activo, suspendido, trial, etc.)
- `<x-empty-state>` — Pantalla vacía con CTA contextual
- `<x-skeleton>` — Placeholder de carga por sección

---

## FASE 1 — Fundaciones (S01–S04)

> Los sprints de fundaciones no producen UI de usuario final. La UI relevante es la de administración de infraestructura y monitoreo del estado de la plataforma, consumida exclusivamente por el equipo técnico interno.

### S01 — Sin UI de usuario final
Las tareas de infraestructura base (repositorio, CI/CD, contenedores, proveedores) no tienen pantallas asociadas. El entregable visual es el pipeline de CI en verde y los servicios corriendo.

### S02–S04 — Sin UI de usuario final
La Shared Layer (contratos, eventos, tenancy, HTTP, observabilidad) es infraestructura de código. El entregable visual es la consola de trazas (Jaeger / Tempo) y los logs estructurados en el agregador elegido, accesibles al equipo técnico.

---

## FASE 2 — Auth & Tenancy (S05–S07)

---

### S05 — Host Auth — Super-admin

#### 1. Pantalla de Login — Host
**Ruta:** `app.plataforma.com/host/login`
**Propósito:** Punto de entrada único para super-admins. Austero y funcional — no es una pantalla pública.

**Elementos:**
- Logo de la plataforma (no white-label)
- Campo email
- Campo password con toggle de visibilidad
- Botón primario "Iniciar sesión"
- Enlace "¿Olvidaste tu contraseña?"
- Enlace "Iniciar sesión con SSO" (si está configurado)

**Estados:**
- **Nominal:** formulario limpio
- **Error de credenciales:** alerta inline bajo el formulario, sin revelar si el email existe
- **Bloqueado por intentos:** alerta de bloqueo temporal con tiempo restante visible
- **Cargando:** botón en estado disabled con spinner

**Flujo post-login:**
1. Credenciales válidas sin 2FA configurado → redirige a `/host/dashboard` con banner "Configura 2FA para mayor seguridad"
2. Credenciales válidas con 2FA activo → redirige a pantalla de verificación 2FA
3. SSO → redirige al proveedor OIDC y vuelve con callback

#### 2. Pantalla de Verificación 2FA — Host
**Ruta:** `app.plataforma.com/host/two-factor`

**Elementos:**
- Instrucción clara: "Ingresa el código de tu aplicación de autenticación"
- Input de 6 dígitos (autoenfocado, numérico)
- Botón "Verificar"
- Enlace "Usar código de recuperación"

**Estados:**
- **Error:** código incorrecto o expirado con mensaje específico
- **Bloqueado:** demasiados intentos, cuenta pausada

#### 3. Pantalla de Recuperación de Credenciales — Host
**Ruta:** `app.plataforma.com/host/password/reset`

**Elementos:**
- Campo email
- Botón "Enviar instrucciones"
- Mensaje de confirmación genérico (no revela si el email existe)

#### 4. Pantalla de Impersonation Log — Host
**Ruta:** `app.plataforma.com/host/audit/impersonations`
**Propósito:** Registro inmutable de todas las sesiones de impersonation iniciadas por super-admins.

**Elementos:**
- Tabla: super-admin actor | tenant target | fecha/hora inicio | fecha/hora fin | IP origen | motivo registrado
- Filtros: por super-admin, por tenant, por rango de fechas
- Export CSV

**Estados:**
- **Vacío:** "No hay sesiones de impersonation registradas"
- **Con datos:** tabla paginada

---

### S06 — Tenant Identity — Usuarios y Roles

#### 5. Pantalla de Login — Tenant
**Ruta:** `{tenant}.plataforma.com/login`
**Propósito:** Login del usuario final dentro del tenant. Respeta white-label del tenant.

**Elementos:**
- Logo del tenant (cargado desde white-label settings)
- Campo email
- Campo password con toggle
- Botón "Iniciar sesión" con color primario del tenant
- Enlace "¿Olvidaste tu contraseña?"
- Enlace "Iniciar sesión con SSO" (si el tenant lo tiene configurado)
- Footer con nombre del tenant y link a Términos/Privacidad de la plataforma

**Diferencia con Host Login:** identidad visual completamente sustituida por la del tenant.

#### 6. Listado de Usuarios — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/users`
**Propósito:** El admin del tenant gestiona todos los usuarios de su organización.

**Layout:** `<x-layout.tenant>` con sidebar activo en "Usuarios"

**Elementos:**
- Header de sección: "Usuarios" + contador total + botón "Invitar usuario"
- Barra de búsqueda por nombre o email
- Filtros: por rol, por estado (activo / inactivo / invitación pendiente)
- Tabla: avatar inicial | nombre | email | rol asignado | estado `<x-badge>` | fecha de último acceso | acciones (editar, desactivar, eliminar)
- Indicador de cuota: "X de Y usuarios permitidos en tu plan" con barra de progreso

**Estados:**
- **Vacío:** "Aún no hay usuarios. Invita al primer miembro de tu equipo." + botón CTA
- **Cuota alcanzada:** banner de advertencia, botón "Invitar usuario" deshabilitado con tooltip "Límite de usuarios alcanzado. Actualiza tu plan."

#### 7. Modal de Invitación de Usuario
**Trigger:** botón "Invitar usuario" en el listado

**Elementos:**
- Campo email (puede ser múltiple, separado por comas)
- Selector de rol (dropdown con roles disponibles en el tenant)
- Mensaje opcional personalizado
- Botón "Enviar invitación"

**Estados:**
- **Email ya existe en el tenant:** error inline
- **Cuota excedida al confirmar:** error bloqueante

#### 8. Pantalla de Aceptación de Invitación
**Ruta:** `{tenant}.plataforma.com/invitation/{token}`

**Elementos:**
- Bienvenida con nombre del tenant
- Nombre del invitante
- Campo nombre completo (si es nuevo usuario)
- Campo password + confirmación (con indicador de fortaleza y reglas del tenant visibles)
- Botón "Aceptar invitación y entrar"

**Estados:**
- **Token expirado:** mensaje con opción de solicitar nueva invitación
- **Token ya usado:** redirige a login

#### 9. Pantalla de Edición de Usuario — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/users/{id}/edit`

**Elementos:**
- Datos básicos: nombre, email (no editable si es SSO)
- Selector de rol con descripción de permisos del rol seleccionado
- Toggle de estado activo/inactivo
- Sección "Sesiones activas": lista de sesiones con dispositivo, IP, última actividad y botón "Cerrar sesión"
- Historial de accesos recientes (últimos 5)
- Botón "Guardar cambios"
- Zona peligrosa: botón "Eliminar usuario" con confirmación modal

#### 10. Gestión de Roles y Permisos — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/roles`

**Elementos:**
- Listado de roles existentes: nombre | cantidad de usuarios asignados | acciones (editar, eliminar)
- Botón "Crear rol"
- Vista de edición de rol: nombre + matriz de permisos agrupados por módulo (checkboxes)

**Estados:**
- **Rol con usuarios asignados al eliminar:** modal de confirmación pidiendo reasignación

#### 11. Password Policies — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/security/password-policy`

**Elementos:**
- Longitud mínima (stepper numérico)
- Toggle: requerir mayúsculas / minúsculas / números / caracteres especiales
- Expiración de contraseña: nunca / 30 / 60 / 90 días
- Historial de reutilización: no reutilizar las últimas N contraseñas (selector)
- Botón "Guardar política"
- Nota informativa: "Los cambios aplican en el próximo login de cada usuario"

---

### S07 — Tenant Identity — SSO y Sesiones

#### 12. Configuración de SSO — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/security/sso`

**Elementos:**
- Tabs: SAML 2.0 | OIDC | SCIM
- **Tab SAML:** campos Entity ID, SSO URL, certificado X.509, atributo de email mapeado; botón "Probar configuración"; estado actual `<x-badge>` (activo/inactivo)
- **Tab OIDC:** campos Client ID, Client Secret, Discovery URL, scopes; botón "Probar configuración"
- **Tab SCIM:** endpoint SCIM de la plataforma (solo lectura, para copiar), token de autorización con botón de regenerar, estado de última sincronización
- Toggle general: "Requerir SSO para todos los usuarios" (deshabilita login por password para no-admins)

#### 13. Gestión de Sesiones Activas — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/security/sessions`

**Elementos:**
- Listado: usuario | dispositivo/agente | IP | país/región | última actividad | acción "Cerrar sesión"
- Botón global "Cerrar todas las sesiones activas" con confirmación modal
- Indicador de límite de sesiones concurrentes por plan

---

## FASE 3 — Billing & Provisioning (S08–S11)

---

### S08 — Host Billing — Suscripciones y Planes

#### 14. Listado de Planes — Host
**Ruta:** `app.plataforma.com/host/billing/plans`

**Elementos:**
- Cards de planes: nombre | precio | ciclo | features incluidos | cantidad de tenants activos en este plan
- Botón "Crear plan"
- Acciones por plan: editar, archivar (no eliminar si tiene tenants)

#### 15. Formulario de Plan — Host
**Ruta:** `app.plataforma.com/host/billing/plans/create` y `/edit`

**Elementos:**
- Nombre del plan, descripción pública
- Precio base + ciclo (mensual / anual / uso)
- Sección de límites de quotas: usuarios, storage, API calls/mes (inputs numéricos con toggle "ilimitado")
- Sección de features incluidos: checklist de feature flags disponibles
- Grace period en días (con nota: "días antes de suspender tras fallo de pago")
- Toggle: visible públicamente en pricing page

#### 16. Listado de Suscripciones — Host
**Ruta:** `app.plataforma.com/host/billing/subscriptions`

**Elementos:**
- Tabla: tenant | plan | estado `<x-badge>` | fecha inicio | próximo cobro | MRR | acciones
- Filtros: por plan, por estado, por fecha de renovación
- Búsqueda por nombre de tenant
- Métricas resumen en header: MRR total | Tenants activos | En dunning | Churn del mes

#### 17. Detalle de Suscripción — Host
**Ruta:** `app.plataforma.com/host/billing/subscriptions/{id}`

**Elementos:**
- Header: nombre del tenant + estado + plan actual
- Sección Suscripción: fechas, ciclo, precio, próxima renovación
- Sección Historial de Facturas: tabla con fecha | monto | estado (pagada/pendiente/fallida) | acciones (ver PDF, reenviar)
- Sección Dunning: intentos realizados, próximo intento, días hasta suspensión
- Acciones manuales: cambiar plan (con preview de prorrateo), aplicar crédito/descuento, cancelar, forzar suspensión
- Zona de gracia period: días restantes visible con barra de progreso si está en dunning

#### 18. Pantalla de Reportes Financieros — Host
**Ruta:** `app.plataforma.com/host/billing/reports`

**Elementos:**
- Selector de período (mes actual, mes anterior, rango custom)
- KPI cards: MRR | ARR | Churn rate | LTV promedio | Nuevos tenants | Bajas
- Gráfico de MRR por mes (últimos 12 meses)
- Tabla de cohort de retención
- Botón export CSV/PDF

---

### S09 — Host Payments — Pasarela y Webhooks

#### 19. Configuración de Pasarela — Host
**Ruta:** `app.plataforma.com/host/payments/gateway`

**Elementos:**
- Selector de pasarela activa (Stripe / otro)
- Campos de API keys (con máscara, botón revelar)
- Estado de conexión: `<x-badge>` con último test exitoso
- Botón "Probar conexión"
- Toggle: modo sandbox / producción con advertencia visual clara

#### 20. Log de Webhooks Entrantes — Host
**Ruta:** `app.plataforma.com/host/payments/webhooks`

**Elementos:**
- Tabla: timestamp | evento | tenant asociado | estado (procesado/fallido/reintentando) | intentos
- Filtros: por tipo de evento, por estado, por fecha
- Detalle por fila: payload completo, respuesta, traza de error si falló
- Botón "Reintentar" por fila en estado fallido

---

### S10 — Host Provisioning — ProvisioningJob

#### 21. Panel de Provisioning — Host
**Ruta:** `app.plataforma.com/host/provisioning`

**Elementos:**
- Tabs: En progreso | Completados | Fallidos
- Tabla: tenant | estado actual `<x-badge>` | paso actual | inicio | duración | acciones
- Indicador de paso actual con stepper visual: `PENDING → DB_CREATED → MIGRATED → DNS_CONFIGURED → SSL_ISSUED → READY`

#### 22. Detalle de ProvisioningJob — Host
**Ruta:** `app.plataforma.com/host/provisioning/{jobId}`

**Elementos:**
- Header: nombre del tenant + estado general
- Timeline de pasos: cada paso con estado (completado ✓ / en progreso ⟳ / fallido ✗ / pendiente ○), timestamp de inicio/fin, duración
- En pasos fallidos: mensaje de error expandible, payload del error, botón "Reintentar desde este paso"
- Log de eventos del job en tiempo real (actualización via polling o Livewire)
- Resultado del dry-run: validaciones pre-provisioning con resultado (ok/fail) por ítem

#### 23. Onboarding de Nuevo Tenant — Host
**Ruta:** `app.plataforma.com/host/tenants/create`
**Propósito:** Iniciar manualmente el provisionamiento de un tenant (casos B2B directos sin flujo de self-service).

**Elementos (wizard de 3 pasos):**
- **Paso 1 — Datos del tenant:** nombre, subdominio deseado, email del admin inicial, plan asignado
- **Paso 2 — Dry run:** botón "Validar disponibilidad" → muestra resultado de cada validación (subdominio libre, cuota de infraestructura, DNS) antes de confirmar
- **Paso 3 — Confirmar:** resumen + botón "Iniciar provisioning" → redirige al detalle del ProvisioningJob creado

---

### S11 — Host Provisioning — Offboarding y Recovery

#### 24. Listado de Tenants — Host
**Ruta:** `app.plataforma.com/host/tenants`

**Elementos:**
- Tabla: nombre | subdominio | plan | estado `<x-badge>` (TRIAL / ACTIVE / PAST_DUE / SUSPENDED / ARCHIVED) | fecha de creación | MRR | acciones
- Filtros: por estado, por plan, por fecha
- Búsqueda por nombre o subdominio
- Botón "Nuevo tenant"

#### 25. Detalle de Tenant — Host
**Ruta:** `app.plataforma.com/host/tenants/{id}`

**Elementos:**
- Header: nombre del tenant + estado `<x-badge>` + plan + fecha de creación
- Tabs de sección:
  - **General:** datos básicos, subdominio, custom domain, admin principal
  - **Suscripción:** enlaza a detalle de suscripción (S08)
  - **Provisioning:** historial de ProvisioningJobs
  - **Usage:** consumo actual vs. límites del plan (barras de progreso)
  - **Audit:** últimas acciones relevantes del tenant (enlaza a S13)
  - **Soporte:** tickets asociados
- Zona de acciones críticas (visualmente separada, color de advertencia):
  - Botón "Suspender tenant" → modal con campo de motivo obligatorio
  - Botón "Archivar tenant" → modal con confirmación doble y advertencia de irreversibilidad
  - Botón "Impersonar como admin del tenant" → abre sesión auditada

#### 26. Pantalla de Upgrade/Downgrade — Host
**Ruta:** `app.plataforma.com/host/tenants/{id}/change-plan`

**Elementos:**
- Plan actual destacado
- Selector de nuevo plan con diferencias de features y límites visibles en comparación
- Preview de prorrateo: monto a cobrar/acreditar hoy
- Fecha efectiva del cambio
- Botón "Confirmar cambio"

---

## FASE 4 — Tenant Core (S12–S15)

---

### S12 — Tenant Settings y White-label

#### 27. Configuraciones Generales — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/settings/general`

**Elementos:**
- Nombre de la organización
- Selector de timezone (búsqueda por nombre de ciudad)
- Selector de moneda por defecto
- Formato de fecha preferido (DD/MM/YYYY, MM/DD/YYYY, etc.)
- Idioma de la interfaz (si aplica)
- Botón "Guardar"

#### 28. White-label — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/settings/branding`

**Elementos:**
- Upload de logo principal (preview inmediato, recomendación de dimensiones)
- Upload de favicon
- Selector de color primario (color picker + input hex)
- Selector de color secundario
- Preview en vivo del sidebar y topbar con los valores actuales aplicados
- Sección Custom Domain:
  - Campo para ingresar dominio propio
  - Instrucciones DNS a configurar (registro CNAME copiable)
  - Estado de verificación: `<x-badge>` (pendiente / verificado / error) con botón "Verificar ahora"
- Personalización de email templates: selector de plantilla + editor de cabecera/pie personalizado

---

### S13 — Tenant Audit

#### 29. Audit Trail — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/audit`

**Elementos:**
- Filtros: por actor (usuario), por tipo de acción, por módulo, por rango de fechas
- Tabla: timestamp | actor (nombre + email) | acción | recurso afectado | IP | país
- Fila expandible: muestra diff antes/después del cambio (formato JSON coloreado)
- Botón export CSV/PDF con el filtro actual aplicado
- Indicador de política de retención activa: "Los registros se conservan por X días según tu plan"

**Estados:**
- **Vacío:** "No hay eventos registrados para los filtros seleccionados"
- **Restringido:** usuarios sin rol de admin ven solo sus propios eventos

---

### S14 — Tenant Notifications

#### 30. Centro de Notificaciones — Tenant (todos los usuarios)
**Ruta:** `{tenant}.plataforma.com/notifications`
**Acceso rápido:** ícono de campana en topbar con badge de no leídas

**Elementos:**
- Lista cronológica: ícono de tipo | título | descripción breve | timestamp relativo | estado (leída/no leída)
- Botón "Marcar todas como leídas"
- Filtro por tipo de notificación
- Paginación o scroll infinito

#### 31. Configuración de Notificaciones — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/settings/notifications`

**Elementos:**
- Tabla de tipos de notificación disponibles × canales disponibles (email / in-app) con toggles
- Sección de plantillas: selector de tipo de notificación + editor de plantilla (con variables disponibles listadas)
- Configuración por usuario: cada usuario puede sobreescribir sus preferencias en su perfil

#### 32. Preferencias de Notificación — Usuario
**Ruta:** `{tenant}.plataforma.com/profile/notifications`

**Elementos:**
- Tabla: tipo de notificación | email (toggle) | in-app (toggle) — solo los canales habilitados por el admin del tenant
- Botón "Guardar preferencias"

---

### S15 — Tenant Usage & Quotas

#### 33. Dashboard de Uso — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/usage`

**Elementos:**
- Header: plan actual + enlace "Ver opciones de upgrade"
- Cards de consumo por recurso:
  - Usuarios activos: X / Y (barra de progreso, color semáforo: verde/amarillo/rojo)
  - Storage: X GB / Y GB
  - API calls este mes: X / Y
  - Cualquier otro límite del plan
- Gráfico de consumo de API calls: últimos 30 días (barras diarias)
- Historial de alertas de quota: tabla de cuándo se cruzaron umbrales
- Banner contextual si algún recurso supera el 80%: "Estás cerca de tu límite de [recurso]. Considera actualizar tu plan."

---

## FASE 5 — Features Avanzados (S16–S20)

---

### S16 — Host Feature Flags

#### 34. Gestión de Feature Flags — Host
**Ruta:** `app.plataforma.com/host/features`

**Elementos:**
- Tabla: nombre del flag | tipo (boolean/payload) | estado global | tenants con override | última modificación
- Botón "Crear feature flag"
- Acciones por flag: editar, ver historial, desactivar globalmente

#### 35. Detalle/Edición de Feature Flag — Host
**Ruta:** `app.plataforma.com/host/features/{id}`

**Elementos:**
- Nombre técnico (slug) y nombre legible
- Tipo: toggle boolean o payload JSON
- Sección "Activado por plan": checkboxes por plan existente
- Sección "Targeting por atributo": reglas del tipo "plan = Enterprise AND región = LATAM" con builder visual de condiciones (add/remove reglas)
- Sección "Overrides manuales por tenant": buscador de tenant + toggle individual de activar/desactivar
- Historial de cambios: actor | acción | fecha/hora

---

### S17 — Tenant Integrations — Webhooks y API Keys

#### 36. Webhooks Outbound — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/integrations/webhooks`

**Elementos:**
- Listado de endpoints configurados: URL | eventos suscritos | estado (activo/inactivo) | último intento | acciones
- Botón "Agregar endpoint"
- Modal de creación: URL destino, selección de eventos a suscribir (checklist), secret de firma

#### 37. Detalle de Webhook Endpoint — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/integrations/webhooks/{id}`

**Elementos:**
- URL y estado
- Eventos suscritos
- Log de intentos de delivery: timestamp | evento | HTTP status de respuesta | duración | estado (exitoso/fallido/reintentando)
- Dead-letter queue: intentos fallidos definitivos con botón "Reintentar manualmente"
- Botón "Enviar evento de prueba"
- Configuración de política de reintento (dentro de límites del plan)

#### 38. API Keys — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/integrations/api-keys`

**Elementos:**
- Listado: nombre | permisos asignados | fecha de creación | fecha de expiración | último uso | acciones (revocar)
- Botón "Crear API Key"
- Modal de creación: nombre descriptivo, selección de permisos, fecha de expiración opcional
- Al crear: mostrar la key completa **una única vez** con botón copiar y advertencia clara de que no se volverá a mostrar

---

### S18 — Tenant Data Management

#### 39. Gestión de Datos — Tenant Admin
**Ruta:** `{tenant}.plataforma.com/admin/data`

**Elementos:**
- Sección Export:
  - Botón "Solicitar export completo" → dispara job async, notifica por email al completar
  - Historial de exports: fecha | estado (generando/listo/expirado) | botón descarga (link temporal)
- Sección Import:
  - Upload de archivo (CSV/JSON según formato documentado)
  - Preview de primeras filas antes de confirmar
  - Reporte de errores de validación antes de importar
- Sección Backup:
  - Botón "Generar backup on-demand"
  - Historial de backups: fecha | tamaño | estado | botón descarga (link temporal, expira en 24h)
- Sección Retención de datos:
  - Visualización de política activa por tipo de dato (según plan)
  - Fecha estimada de próximo purge automático

---

### S19 — Host Analytics & Reporting

#### 40. Dashboard de Salud de Plataforma — Host
**Ruta:** `app.plataforma.com/host/analytics`

**Elementos:**
- KPI cards (período seleccionable): Tenants totales | Tenants activos | Nuevos este mes | Churn | MRR | Provisioning failures
- Gráfico de distribución de tenants por estado (dona)
- Gráfico de nuevos tenants por semana (últimas 12 semanas)
- Tabla de tenants en riesgo: en dunning, cerca de cuota, con provisioning fallido
- Sección Cohort: tabla de retención mensual
- Botón export de cada sección (CSV/PDF)

---

### S20 — Host Support

#### 41. Listado de Tickets — Host
**Ruta:** `app.plataforma.com/host/support`

**Elementos:**
- Tabla: ID | asunto | tenant | prioridad | estado | asignado a | SLA restante `<x-badge>` con color semáforo | fecha de creación
- Filtros: por estado, por prioridad, por asignado, por tenant
- Botón "Crear ticket" (para casos iniciados internamente)

#### 42. Detalle de Ticket — Host
**Ruta:** `app.plataforma.com/host/support/{id}`

**Elementos:**
- Header: asunto + estado + prioridad + SLA
- Hilo de conversación con timestamps
- Panel lateral: datos del tenant, link directo al detalle del tenant (S25), eventos de audit del tenant relevantes (últimos 10, según modelo de visibilidad definido)
- Acciones: cambiar estado, reasignar, escalar, adjuntar archivo, agregar nota interna (no visible al tenant)

---

## FASE 6 — Hardening & Compliance (S21–S24)

---

### S21 — Host Security & Compliance

#### 43. Audit Global — Host
**Ruta:** `app.plataforma.com/host/security/audit`

**Elementos:**
- Tabla: timestamp | actor (super-admin) | acción | recurso | tenant afectado (si aplica) | IP
- Filtros: por actor, por tipo de acción, por tenant, por fecha
- Fila expandible: detalle completo del evento
- Export CSV/PDF

#### 44. Gestión de Encryption Keys y Retención — Host
**Ruta:** `app.plataforma.com/host/security/policies`

**Elementos:**
- Tabla de políticas de retención por tipo de dato y tier de tenant
- Estado de encryption keys: fecha de creación, próxima rotación, estado
- Botón "Rotar keys ahora" (con confirmación y advertencia de impacto)
- Log de rotaciones pasadas

---

### S22 — Host Monitoring & Alerting

#### 45. Panel de Salud de Servicios — Host
**Ruta:** `app.plataforma.com/host/monitoring`

**Elementos:**
- Grid de health checks: cada servicio/tenant crítico con estado (OK / DEGRADED / DOWN) + última verificación
- Historial de incidentes: servicio | inicio | fin | duración | impacto
- Configuración de alertas: canal (email, Slack, webhook), umbral, servicio objetivo
- Log centralizado: búsqueda por `tenant_id`, `trace_id`, `correlation_id`, nivel (error/warn/info)

---

### S23 — Host Landings y Marketing

#### 46. Página Principal — Público
**Ruta:** `plataforma.com/`
**Layout:** `<x-layout.public>`

**Elementos:**
- Hero con propuesta de valor y CTA "Comienza gratis" / "Ver demo"
- Secciones de features clave
- Sección de precios: cards de planes (cargados dinámicamente desde la BD de planes)
- Testimonios / logos de clientes
- Footer con links legales

#### 47. Formulario de Inicio de Onboarding — Público
**Ruta:** `plataforma.com/register`

**Elementos:**
- Nombre de la organización
- Email del admin inicial
- Subdominio deseado con verificación de disponibilidad en tiempo real (validación JS + endpoint)
- Plan seleccionado (pre-seleccionado si viene de CTA de un plan específico)
- Botón "Crear mi cuenta" → dispara flujo de Provisioning (S10)
- Post-submit: pantalla de "Estamos configurando tu espacio" con estado del ProvisioningJob actualizado periódicamente

#### 48. Gestión de Contenidos Legales — Host
**Ruta:** `app.plataforma.com/host/settings/legal`

**Elementos:**
- Listado de documentos: Términos de Servicio | Política de Privacidad | otros
- Por documento: versión activa | fecha de publicación | link público | botón "Crear nueva versión"
- Editor de nueva versión con diff respecto a la versión anterior
- Publicación con fecha efectiva y opción de notificar a tenants activos

---

### S24 — Hardening Final y Go-Live

No produce nuevas pantallas. Las tareas de este sprint son de validación y operaciones sobre las pantallas ya construidas: smoke tests en producción, verificación de headers de seguridad, revisión de mensajes de error visibles al usuario y documentación del runbook operativo.

---

## Apéndice — Patrones de UI Globales

### Estados de Badge por Tenant Lifecycle

| Estado                 | Color       | Label visible  |
| ---------------------- | ----------- | -------------- |
| `PENDING_PROVISIONING` | Gris        | Provisionando  |
| `TRIAL`                | Azul        | Trial          |
| `ACTIVE`               | Verde       | Activo         |
| `PAST_DUE`             | Amarillo    | Pago pendiente |
| `SUSPENDED`            | Rojo        | Suspendido     |
| `ARCHIVED`             | Gris oscuro | Archivado      |
| `DELETED`              | Negro       | Eliminado      |

### Jerarquía de Acciones Destructivas

Toda acción irreversible sigue el mismo patrón de confirmación:
1. Botón con color de advertencia (rojo o naranja) en zona visualmente separada
2. Modal con descripción exacta de consecuencias
3. Campo de confirmación por texto (escribir nombre del tenant/recurso) para acciones de alto impacto
4. Log inmediato en audit al confirmar, independientemente del resultado

### Mensajes de Error Accionables

Los errores nunca dicen solo "algo salió mal". Siempre incluyen:
- Qué ocurrió (sin stack traces al usuario)
- Qué puede hacer el usuario (reintentar, contactar soporte, verificar datos)
- ID de correlación visible para reportar al soporte