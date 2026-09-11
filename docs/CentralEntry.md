

El sidebar Central debe ser un **panel de control del SaaS**, donde cada entrada tenga una responsabilidad única, sus UCs operativos y acciones explícitas; no conviene convertir cada caso de uso interno en una opción de navegación.

---

# 1. Dashboard

**Responsabilidad:** visión operacional de toda la plataforma.

### Información

```text
┌─────────────────────────────────────────────────────────────┐
│ PLATFORM OVERVIEW                                           │
├─────────────┬─────────────┬─────────────┬──────────────────┤
│ Tenants     │ Active      │ Suspended   │ Quarantined      │
│ 1,248       │ 1,197       │ 32          │ 19               │
├─────────────┴─────────────┴─────────────┴──────────────────┤
│ System Health                                              │
│ ● API       Healthy                                        │
│ ● Queue     Healthy                                        │
│ ● DB        Healthy                                        │
│ ● Billing   Degraded                                       │
├─────────────────────────────────────────────────────────────┤
│ Recent Critical Events                                     │
│ ...                                                         │
└─────────────────────────────────────────────────────────────┘
```

### Casos de uso

- **UC-C-D01:** Consultar estado general de la plataforma.
- **UC-C-D02:** Consultar métricas de tenants.
- **UC-C-D03:** Detectar servicios degradados.
- **UC-C-D04:** Consultar eventos críticos recientes.
- **UC-C-D05:** Acceder rápidamente a recursos afectados.

### Botones

```text
[View Tenants]
[View Health]
[View Critical Events]
[View Billing Issues]
```

### No debería hacer

- Configurar tenants.
- Ejecutar operaciones destructivas.
- Editar planes.
- Gestionar usuarios.

El Dashboard **observa**, no administra.

---

# 2. Tenants

**Responsabilidad:** ciclo de vida operacional de cada tenant.

Esta es probablemente la pantalla más importante del Central.

### Lista

```text
TENANTS

[Search...] [Status ▼] [Plan ▼] [Health ▼]

┌────────────┬────────────┬──────────┬──────────┬────────────┐
│ Tenant     │ Status     │ Plan     │ Health   │ Created    │
├────────────┼────────────┼──────────┼──────────┼────────────┤
│ Acme       │ Active     │ Pro      │ Healthy  │ ...        │
│ Foo Corp   │ Suspended  │ Basic    │ Warning  │ ...        │
│ Bar Inc    │ Quarantine│ Pro      │ Critical │ ...        │
└────────────┴────────────┴──────────┴──────────┴────────────┘

[Create Tenant]
```

### Casos de uso

Aquí entran directamente tus workflows centrales:

- **UC-C-01:** Onboarding y aprovisionamiento transaccional asíncrono.
- **UC-C-02:** Scoring antifraude y aislamiento en cuarentena.
- **UC-C-03:** Suspensión preventiva por infracción/impago.
- **UC-C-04:** Purga dura y derecho al olvido.
- **UC-C-05:** Consultar tenant.
- **UC-C-06:** Cambiar estado operacional.
- **UC-C-07:** Consultar salud del tenant.
- **UC-C-08:** Consultar consumo.
- **UC-C-09:** Gestionar suscripción.
- **UC-C-10:** Consultar actividad/auditoría del tenant.

### Acciones de lista

```text
[Create Tenant]

Por tenant:
[View]
[Impersonate*]
[Suspend]
[Quarantine]
[Reactivate]
```

`Impersonate` debería estar muy restringido, auditado y probablemente ni siquiera existir inicialmente.

### Detalle del tenant

```text
TENANT: Acme Corp

Status: ACTIVE
Plan: PRO
Health: HEALTHY

[Edit]
[Change Plan]
[Suspend]
[Quarantine]
[Reactivate]

Tabs:
├── Overview
├── Subscription
├── Usage
├── Health
├── Activity
└── Danger Zone
```

### Danger Zone

```text
[Force Suspension]
[Release from Quarantine]
[Initiate Purge]
```

`Purge` debe exigir confirmación fuerte y dejar un registro inmutable.

---

# 3. Health Monitor

**Responsabilidad:** detectar problemas técnicos de plataforma y tenants.

No es lo mismo que Dashboard.

Dashboard:

> "Tenemos 19 tenants con problemas."

Health Monitor:

> "Estos son los 19, qué está fallando y desde cuándo."

### Casos de uso

- **UC-C-H01:** Monitorizar servicios centrales.
- **UC-C-H02:** Monitorizar infraestructura.
- **UC-C-H03:** Monitorizar colas.
- **UC-C-H04:** Monitorizar bases de datos.
- **UC-C-H05:** Monitorizar integraciones externas.
- **UC-C-H06:** Detectar tenants degradados.
- **UC-C-H07:** Consultar incidentes.
- **UC-C-H08:** Consultar health checks históricos.

### Vista

```text
HEALTH MONITOR

Platform
├── API             ● Healthy
├── Database        ● Healthy
├── Queue           ● Degraded
├── Cache           ● Healthy
└── Storage         ● Healthy

Tenants
├── Healthy         1,197
├── Warning            32
└── Critical           19

Incidents
├── Queue latency
└── Payment webhook failures
```

### Botones

```text
[Refresh]
[View Incident]
[View Logs]
[View Affected Tenants]
[Acknowledge Incident]
```

No pondría:

```text
[Restart Server]
[Clear Database]
[Flush Everything]
```

en esta interfaz salvo que exista una razón operacional real. Un dashboard administrativo no debe convertirse accidentalmente en un panel de infraestructura.

---

# 4. Audit Log

**Responsabilidad:** trazabilidad de acciones administrativas y eventos relevantes.

Esta entrada es la que añadiría al sidebar.

### Casos de uso

- **UC-C-A01:** Consultar eventos administrativos.
- **UC-C-A02:** Filtrar eventos.
- **UC-C-A03:** Consultar quién ejecutó una acción.
- **UC-C-A04:** Consultar cuándo ocurrió.
- **UC-C-A05:** Consultar entidad afectada.
- **UC-C-A06:** Consultar motivo/contexto.
- **UC-C-A07:** Auditar operaciones sensibles.

### Lista

```text
AUDIT LOG

[Search...]

Actor       Action        Resource       Result      Date
────────────────────────────────────────────────────────────
admin       tenant.suspend Acme           SUCCESS     ...
admin       plan.update    Pro             SUCCESS     ...
system      tenant.quarantine Foo         SUCCESS     ...
admin       tenant.purge   Bar             SUCCESS     ...
```

### Filtros

```text
Actor
Action
Resource
Tenant
Result
Date Range
```

### Acciones

```text
[View Event]
[Export]
```

Importante:

**no debería existir `[Delete Log]`.**

El audit log pierde su propósito si un administrador puede borrar convenientemente las pruebas.

---

# 5. Subscriptions

**Responsabilidad:** relación comercial entre tenant y plan.

### Casos de uso

- **UC-C-B01:** Consultar suscripción.
- **UC-C-B02:** Crear suscripción.
- **UC-C-B03:** Cambiar plan.
- **UC-C-B04:** Cancelar suscripción.
- **UC-C-B05:** Reactivar suscripción.
- **UC-C-B06:** Consultar estado de billing.
- **UC-C-B07:** Gestionar trial.
- **UC-C-B08:** Detectar suscripciones problemáticas.

### Lista

```text
SUBSCRIPTIONS

Tenant       Plan       Status       Renewal
──────────────────────────────────────────────
Acme         Pro        Active       Jan 12
Foo          Basic      Past Due     Jan 05
Bar          Pro        Canceled     —
```

### Botones

```text
[Create Subscription]
[View]
[Change Plan]
[Cancel]
[Reactivate]
```

### Detalle

```text
Subscription
├── Plan
├── Price
├── Billing Cycle
├── Status
├── Trial
├── Renewal
└── Billing History

[Change Plan]
[Cancel Subscription]
[Reactivate]
```

No mezclaría aquí la administración de **Plans**. Una subscription consume un plan; no lo define.

---

# 6. Plans

**Responsabilidad:** catálogo comercial que determina qué puede comprar un tenant.

### Casos de uso

- **UC-C-P01:** Crear plan.
- **UC-C-P02:** Modificar plan.
- **UC-C-P03:** Activar/desactivar plan.
- **UC-C-P04:** Configurar precio.
- **UC-C-P05:** Configurar límites.
- **UC-C-P06:** Configurar features/entitlements.
- **UC-C-P07:** Consultar tenants asociados.
- **UC-C-P08:** Versionar/cambiar condiciones comerciales.

### Lista

```text
PLANS

Name       Price       Tenants      Status
────────────────────────────────────────────
Free       $0          834          Active
Basic      $19         291          Active
Pro        $59         103          Active
Enterprise Custom       20          Active

[Create Plan]
```

### Detalle

```text
PLAN: PRO

Pricing
├── Monthly: $59
└── Annual: $590

Limits
├── Users: 50
├── Storage: 100 GB
└── API Requests: 100k

Features
├── Reports
├── API
├── SSO
└── ...

[Edit]
[Duplicate]
[Deactivate]
[View Tenants]
```

### Regla importante

No permitiría:

```text
[Delete Plan]
```

si existen subscriptions que lo referencian.

En ese caso:

```text
[Deactivate]
```

y el plan queda disponible únicamente para los tenants existentes.

---

# 7. Broadcast Center

**Responsabilidad:** comunicación central → tenants.

### Casos de uso

- **UC-C-S01:** Crear broadcast.
- **UC-C-S02:** Seleccionar audiencia.
- **UC-C-S03:** Programar broadcast.
- **UC-C-S04:** Publicar inmediatamente.
- **UC-C-S05:** Cancelar publicación programada.
- **UC-C-S06:** Consultar estado de entrega.
- **UC-C-S07:** Consultar historial.

### Lista

```text
BROADCASTS

Title              Audience       Status       Date
─────────────────────────────────────────────────────
Maintenance        All            Sent         ...
New feature        Pro             Scheduled   ...
Billing notice     Past Due       Draft        ...

[Create Broadcast]
```

### Editor

```text
Title
Message

Audience:
○ All tenants
○ Selected tenants
○ Plan
○ Status
○ Custom filter

Delivery:
○ Now
○ Schedule

[Save Draft]
[Schedule]
[Send Now]
[Cancel]
```

### Seguridad

`Send Now` debe requerir confirmación.

Para broadcasts globales:

```text
Are you sure?

Audience: 1,248 tenants
Delivery: Immediate

[Cancel] [Send Broadcast]
```

---

# 8. Admin Users

**Responsabilidad:** personas que pueden operar el Control Plane.

### Casos de uso

- **UC-C-SC01:** Crear administrador.
- **UC-C-SC02:** Desactivar administrador.
- **UC-C-SC03:** Asignar roles.
- **UC-C-SC04:** Gestionar permisos.
- **UC-C-SC05:** Consultar actividad administrativa.
- **UC-C-SC06:** Revocar acceso.
- **UC-C-SC07:** Aplicar MFA, si el sistema lo soporta.

### Lista

```text
ADMIN USERS

Name          Role             Status
────────────────────────────────────────
John          Super Admin      Active
Maria         Support          Active
Carlos        Billing          Disabled

[Invite Admin]
```

### Acciones

```text
[View]
[Edit Roles]
[Disable]
[Revoke Sessions]
```

No debería existir un botón tipo:

```text
[Delete Admin]
```

para usuarios que tengan historial de auditoría. Desactivar/revocar acceso preserva trazabilidad.

---

# 9. Platform Branding

**Responsabilidad:** identidad visual global de Reasy.

### Casos de uso

- **UC-C-ST01:** Configurar nombre de plataforma.
- **UC-C-ST02:** Configurar logo.
- **UC-C-ST03:** Configurar favicon.
- **UC-C-ST04:** Configurar colores.
- **UC-C-ST05:** Configurar elementos visuales globales.
- **UC-C-ST06:** Previsualizar cambios.
- **UC-C-ST07:** Publicar cambios.

### Vista

```text
PLATFORM BRANDING

Logo
[ Upload ]

Application Name
[ Reasy                         ]

Primary Color
[ #........ ]

Favicon
[ Upload ]

──────────────────────────────

Preview
┌─────────────────────────────┐
│ Reasy                        │
│                             │
│ Dashboard                   │
│ Tenants                     │
└─────────────────────────────┘

[Reset] [Save Changes]
```

### Regla

Separaría:

**Platform Branding**

de

**Tenant Branding**

El primero pertenece al Central. El segundo pertenece al tenant.

---

# Estructura final

Con todo esto, el Central quedaría:

```text
Reasy

ADMINISTRATION
├── Dashboard
├── Tenants
├── Health Monitor
└── Audit Log

BILLING
└── Subscriptions

CATALOG
└── Plans

SUPPORT
└── Broadcast Center

SECURITY
└── Admin Users

SETTINGS
└── Platform Branding
```

## Mapa de responsabilidades

| Entrada           | Controla             | Principalmente consulta | Acciones críticas          |
| ----------------- | -------------------- | ----------------------- | -------------------------- |
| Dashboard         | Estado global        | Métricas                | —                          |
| Tenants           | Ciclo de vida tenant | Estado/uso/salud        | Suspend, Quarantine, Purge |
| Health Monitor    | Salud técnica        | Servicios/incidentes    | Acknowledge                |
| Audit Log         | Trazabilidad         | Eventos                 | Export                     |
| Subscriptions     | Relación comercial   | Billing                 | Change/Cancel              |
| Plans             | Catálogo             | Pricing/limits          | Create/Edit/Deactivate     |
| Broadcast Center  | Comunicación         | Entregas                | Send/Schedule              |
| Admin Users       | Acceso Central       | Operadores              | Disable/Revoke             |
| Platform Branding | Identidad visual     | Configuración           | Save/Publish               |

## Y la regla arquitectónica detrás

Tus **UC-C-01 → UC-C-04** deberían mapearse así:

```text
UC-C-01 Onboarding
        │
        └── Tenants
              └── Create Tenant

UC-C-02 Antifraud + Quarantine
        │
        ├── Tenants
        │     └── Quarantine
        │
        └── Audit Log

UC-C-03 Preventive Suspension
        │
        ├── Tenants
        │     └── Suspend
        │
        └── Audit Log

UC-C-04 Hard Purge / Right to be Forgotten
        │
        ├── Tenants
        │     └── Danger Zone → Purge
        │
        └── Audit Log
```

Eso evita el error de terminar con un sidebar absurdo como:

```text
Administration
├── Onboarding
├── Fraud
├── Quarantine
├── Suspensions
├── Purges
├── Provisioning
├── Tenant Lifecycle
├── ...
```

Son **workflows**, no necesariamente **módulos de navegación**.

La distinción correcta es:

> **Sidebar = dominios de administración.**
> **Pantallas = recursos que el operador administra.**
> **Botones = casos de uso/acciones.**
> **Servicios internos = implementación de esos casos de uso.**

---

# Anexo: Análisis spec vs. implementación real (2026-09-11)

## Evaluación técnica

`Componentes: [Sidebar Central, 9 entradas CentralEntry.md, Routes/Livewire Central] | Riesgo detectado: [Health Monitor enlaza a JSON; Audit Log y Admin Users inexistentes] | Rol líder: [FRONTEND]`

## Matriz comparativa

| #   | Entrada spec      | Estado              | Existe                                                                                                                                                                            | Falta generar                                                                                                                                                                                                                                                                                       |
| --- | ----------------- | ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | Dashboard         | 🟡 Parcial          | `central.dashboard` → `Auth/Livewire/Dashboard.php` + `UI/pages/dashboard.blade.php`. Stats, health, activity (`Activity`), orgs table.                                           | Reemplazar mocks (`128/4821`, chart hardcodeado, alerts fijas) por queries reales: breakdown `Active/Suspended/Quarantined/PastDue`, billing health, critical events. Agregar botones `[View Tenants][View Health][View Billing Issues]`.                                                           |
| 2   | Tenants           | 🟡 Parcial-avanzado | Rutas `index/create/edit` + `TenantList`, `CreateTenant`, `ManageTenant`. Impersonate + purge queued + `activity('provisioning')`.                                                | Lista: `[Search][Status▼][Plan▼][Health▼]` (hoy solo `paginate(10)` sin filtros). Detalle: tabs `Overview/Subscription/Usage/Health/Activity/Danger Zone`; acciones `[Change Plan][Suspend][Quarantine][Reactivate]`; `Danger Zone` con confirmación fuerte de Purge.                               |
| 3   | Health Monitor    | 🔴 Roto parcial     | Solo API JSON: `HealthCheckController` (DB/Redis/Queue suma buckets + `failed_jobs`) + `/up/central` público. Sidebar apunta a `central.health` con `target=_blank` (JSON crudo). | Nueva UI `Operations/Livewire/HealthMonitor.php` + vista: Platform, Tenants Healthy/Warning/Critical, Incidents + `[Refresh][View Incident][View Affected Tenants][Acknowledge]`. Mantener JSON en `/up/*`; cambiar sidebar a ruta UI `central.health.monitor`. Sin `[Restart][Flush]`.             |
| 4   | Audit Log         | 🔴 Inexistente      | Solo `activity()` disperso + `Tenant/Compliance/AuditLogViewer` (scope tenant). Cero UI central, cero ruta, cero sidebar.                                                         | Nuevo módulo: `Central/Operations` o `Central/Support/Livewire/CentralAuditLog.php` + ruta `central.audit.log` + sidebar `Audit Log`. Query Spatie `Activity` (Actor/Action/Resource/Result/Date) + filtros + `[View][Export]`. Prohibido `[Delete]`.                                               |
| 5   | Subscriptions     | 🟡 Solo lectura     | `central.billing.subscriptions` → `SubscriptionList.php` (`paginate(15)` + tenants).                                                                                              | Acciones: `[Create][Change Plan][Cancel][Reactivate]` vía `Billing` Actions existentes (`SubscribeTenant`, `ChangePlan`, `CancelSubscription`) + vista detalle (Price/Cycle/Status/Trial/Renewal/History) + gestión trial + flag `past_due`.                                                        |
| 6   | Plans             | 🟢 Casi total       | `central.catalog.plans` → `ManagePlans.php`: create/edit/toggle/delete, pricing centavos, quotas, `gateway_ids`, registry.                                                        | Hardening spec: vetar `delete()` si existen `subscriptions` → forzar `Deactivate`; agregar `[Duplicate]` + `[View Tenants]` (count por `plan_id`). Nada estructural.                                                                                                                                |
| 7   | Broadcast Center  | 🟡 Parcial          | `central.support.broadcasts` → `BroadcastCenter.php` + `SendBroadcastAction` + lista paginada.                                                                                    | Audiencia: falta `Selected tenants/Plan/Custom filter` (hoy solo `all/status`); falta `Schedule/Cancel` + estado entrega + confirmación modal `Send Now` global. Validar `filterValue` (`exists:plans,slug`).                                                                                       |
| 8   | Admin Users       | 🔴 Inexistente UI   | Solo base: `CentralUser` model/factory/seeder + `CreateCentralUserCommand` + login/2FA. Cero ruta, cero Livewire, cero sidebar.                                                   | Nuevo `Central/Auth/Livewire/ManageCentralUsers.php` + ruta `central.auth.users` + grupo sidebar `SECURITY`: `[Invite][Edit Roles][Disable][Revoke Sessions]`. Sin `[Delete]` con historial. Reutilizar `RevokeOldestSessionAction`, Gate por rol. UC-C-SC07 MFA ya existe (`TwoFactorEnrollment`). |
| 9   | Platform Branding | 🟢 Total-menor      | `central.settings.branding` → `PlatformBranding.php`: Gate `branding:manage` + `activity('settings')` + upload + normalización hex.                                               | Menor: agregar `favicon` + botón `Reset` + preview según spec. `logoUrl` hoy nullable-string sin validación url; endurecer si se exige.                                                                                                                                                             |

## Qué generar por caso (archivos)

- **Sidebar `layouts/central/sidebar.blade.php`:** agregar `Audit Log`, `Admin Users`; cambiar `Health Monitor` de `central.health` (JSON) a nueva ruta UI.
- **Dashboard:** refactor `Dashboard.php: stats(), alerts(), activityChart()` — queries reales por `status`, sin fallbacks `128/4821`.
- **Tenants:** extender `TenantList.php` (search + 3 filtros + `queryString`); extender `ManageTenant.php` a layout por tabs + `ChangePlanAction` + `Suspend/Quarantine` + `Danger Zone`.
- **Health Monitor (nuevo):** `Operations/Interface/Livewire/HealthMonitor.php` + `Routes/web.php` (`central.health.monitor`) + vista `health-monitor.blade.php`.
- **Audit Log (nuevo):** `Central/Support|Operations/Livewire/CentralAuditLog.php` + ruta `central.audit.log` + vista tabla Activity + export.
- **Subscriptions:** extender `SubscriptionList.php` + nuevo `SubscriptionDetail.php` que delega a Actions de `Billing/Application/Actions/`.
- **Plans:** parche `ManagePlans.php: delete()` con guard `Subscription::where(plan_id)` + método `duplicate()` + `tenantsCount`.
- **Broadcast:** extender `BroadcastCenter.php` (audiencia `plan/selected`, `scheduled_at`, `cancel()`) + modal confirmación.
- **Admin Users (nuevo):** `Central/Auth/Livewire/ManageCentralUsers.php` + `Routes/web.php` + vistas + Policy/Gate.
- **Branding:** parche menor `PlatformBranding.php` (favicon + reset).

## Scope check — [PO/SCOPE]

- No crear módulos nuevos salvo `Admin Users` y `Audit Log` UI (2+ UCs reales cada uno, justificado).
- Todo lo demás es extensión de Livewire/Actions existentes, sin Repository/CQRS nuevo.

## Verificación — [SEC_QA]

- `vendor/bin/pint --dirty --format agent` + `vendor/bin/phpstan analyse --error-format=raw` + `php artisan test --compact --filter=CentralRegressionTest` tras cada entrada.
