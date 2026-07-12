> **Objetivo**
>
> Este documento mapea cada vista de SaaSiFy contra su Bounded Context y Módulo, según `PROJECT_DECISIONS.md` y `ARCHITECTURE_RULES.md`. Define qué existe, dónde vive, qué Action invoca y cómo aplica `UI_RULES.md`.
>
> Ninguna vista se implementa sin aparecer aquí primero. Si una vista no está en este documento, no se construye hasta agregarla.

---

# Nota de alcance (léela antes de implementar Tenant)

`PROJECT_DECISIONS.md` clasifica el Tenant BC con módulos genéricos (CRM, Documents, Forms, Automation, Reports). Ese listado es un catálogo de ejemplo para productos que se construyan _sobre_ SaaSiFy — no es el scope del framework en sí.

El scope real y vigente del Tenant BC es intencionalmente mínimo: **Users, Teams, Branding, API Keys**. Cero lógica vertical. Este documento sigue ese scope real. Si en algún momento se decide agregar un módulo vertical al framework base (lo cual contradice la visión del PRD, sección 1: "no pretende ser un framework adicional... base sobre la cual desarrollar productos"), primero se actualiza `PROJECT_DECISIONS.md` con un ADR, luego este documento.

---

# Anotaciones de estado

Cada vista puede tener una anotación al final:
- `✅ [completado]` — implementada, testeada, funcionando
- `🔄 [en proceso]` — implementación parcial o en desarrollo
- `⏳ [pendiente]` — documentada pero no implementada

---

# Cómo leer cada entrada

```
### Nombre de la vista  ✅ [completado]

- Ruta:
- Contexto → Módulo:
- Propósito:
- Actions invocadas: Modulo::AccionInvocable (nunca acceso directo a Models de otro módulo)
- Componentes Flux:
- Notas UI_RULES:
- Estado:
```

---

# 1. Public

Contexto sin autenticación. Responsable de marketing, registro y entrada al sistema. No tiene módulos propios — orquesta Actions de Host (Identity, Provisioning).

### Landing  ✅ [completado]

- Ruta: `/`
- Contexto → Módulo: Public → vista `public.home` con layout `layouts.public`
- Propósito: marketing, propuesta de valor, CTA a registro y pricing.
- Actions invocadas: ninguna — página estática
- Componentes Flux: `flux:navbar` (público, sin sidebar), `flux:button`
- Notas UI_RULES: cero borders decorativos entre secciones — separar con whitespace vertical, no `border-t`.
- Estado: ✅ Completado en Sprint 6.3. Vista en `resources/views/public/home.blade.php`.

### Pricing  ✅ [completado]

- Ruta: `/pricing`
- Contexto → Módulo: Public → vista `public.pricing`
- Propósito: mostrar 3 tier de precios estáticos (Starter $19, Pro $49, Enterprise Custom).
- Actions invocadas: ninguna — contenido estático, no conecta al módulo real `Plans` (Host)
- Componentes Flux: `flux:card` con borde solo en no-destacados.
- Notas UI_RULES: sin gradientes.
- Estado: ✅ Completado en Sprint 6.3. Vista en `resources/views/public/pricing.blade.php`.

### Register (Wizard)  ✅ [completado]

- Ruta: `/register`
- Contexto → Módulo: Public → `TenantRegistration` Livewire en `Host/Tenants`
- Propósito: crear tenant + admin user + disparar provisioning pipeline.
- Actions invocadas:
    - `Host/Tenants::CreateTenantAction`
    - `Host/Provisioning::ProvisionTenantPipelineAction`
- Componentes Flux: `flux:input`, `flux:card`
- Notas UI_RULES: validación de subdominio ocurre en la Action, no en el componente.
- Estado: ✅ Completado en Sprint 6.3. Livewire en `Host/Tenants/Http/Livewire/TenantRegistration.php`.

### Login (Host)  ✅ [completado]

- Ruta: `/host/login`
- Contexto → Módulo: Host → Identity
- Propósito: autenticación de staff/platform (guard `host`).
- Actions invocadas: `Host/Identity::AuthenticateHostUserAction`
- Componentes Flux: `flux:input`, `flux:button` (primary, accent)
- Notas UI_RULES: layout `auth.split` del Starter Kit — formulario centrado a la derecha, quote inspiradora a la izquierda.
- Estado: ✅ Completado en Sprint 6.1. Livewire en `Host/Identity/Http/Livewire/HostLogin.php`.

### Login (Tenant)  ✅ [completado]

- Ruta: `/tenant/login`
- Contexto → Módulo: Tenant → Users
- Propósito: autenticación de usuarios finales del tenant (guard `tenant`).
- Actions invocadas: `Tenant/Users::AuthenticateTenantUserAction`
- Componentes Flux: `flux:input`, `flux:button`
- Notas UI_RULES: mismo layout `auth.split` que Host login — consistencia visual.
- Estado: ✅ Completado en Sprint 6.2. Livewire en `Tenant/Users/Http/Livewire/TenantLogin.php`.

### Forgot / Reset Password  ⏳ [pendiente]

- Ruta: `/forgot-password`, `/reset-password/{token}`
- Contexto → Módulo: Public → Fortify (broker `users`)
- Propósito: recuperación de acceso para usuarios web (guard `web`).
- Actions invocadas: Fortify built-in
- Componentes Flux: `flux:input`, `flux:button`
- Notas UI_RULES: mismo tratamiento visual que Login.
- Estado: ⏳ Pendiente. Fortify provee las rutas pero no hay vistas personalizadas. Se usan las vistas default del Starter Kit o se crean nuevas.

---

# 2. Host

Panel de administración de la plataforma. Acceso restringido a staff de SaaSiFy (no tenants).

## 2.1 Identity

### Host Users (staff administrativo)  ⏳ [pendiente]

- Ruta: `/host/users`
- Contexto → Módulo: Host → Identity
- Propósito: gestión de usuarios internos de SaaSiFy (no tenants).
- Actions invocadas: `Host/Identity::ListStaffUsersAction`, `Host/Identity::InviteStaffUserAction`
- Componentes Flux: `flux:table`, `flux:modal`
- Notas UI_RULES: tabla sin border perimetral.
- Estado: ⏳ Pendiente. No implementado. El panel Host tiene login, dashboard, tenants, plans, features, billing — pero no gestión de usuarios host.

## 2.2 Tenants

### Tenants List  ✅ [completado]

- Ruta: `/host/tenants`
- Contexto → Módulo: Host → Tenants
- Propósito: listado de todos los tenants, crear, suspender, reanudar, eliminar.
- Actions invocadas: `Host/Tenants::CreateTenantAction`, `SuspendTenantAction`, `ResumeTenantAction`, `DeleteTenantAction`
- Componentes Flux: `flux:table`, `flux:badge` (estado)
- Notas UI_RULES: badges de estado: active=verde, suspended=rojo, pending=gris.
- Estado: ✅ Completado en Sprint 6.1. Livewire en `Host/Tenants/Http/Livewire/TenantsList.php`.

### Tenant Detail  ⏳ [pendiente]

- Ruta: `/host/tenants/{tenant}`
- Contexto → Módulo: Host → Tenants (lee también de Billing y Monitoring vía Actions públicas)
- Propósito: detalle de un tenant — datos, plan, billing, actividad.
- Actions invocadas: `Host/Billing::GetTenantBillingHistoryAction`, `Host/Monitoring::GetTenantUsageMetricsAction`
- Componentes Flux: `flux:tabs` (General / Billing / Actividad)
- Notas UI_RULES: comunicación cross-módulo vía Actions públicas.
- Estado: ⏳ Pendiente. No implementado.

## 2.3 Billing / Plans / Payments

### Plans Catalog (admin)  ✅ [completado]

- Ruta: `/host/plans`
- Contexto → Módulo: Host → Plans
- Propósito: CRUD de planes (crear, archivar, listar).
- Actions invocadas: `Host/Plans::CreatePlanAction`, `ArchivePlanAction`
- Componentes Flux: `flux:table`, form inline
- Notas UI_RULES: sin badges de color por plan — jerarquía por orden y tipografía.
- Estado: ✅ Completado en Sprint 6.1 + Sprint 7.x (CRUD completo). Livewire en `Host/Plans/Http/Livewire/PlansList.php`.

### Billing Dashboard  ✅ [completado]

- Ruta: `/host/billing`
- Contexto → Módulo: Host → Billing
- Propósito: listado de suscripciones activas con plan, estado, fecha.
- Actions invocadas: ninguna directa — lectura de `Subscription::with('plan')`
- Componentes Flux: `flux:table`
- Notas UI_RULES: misma tabla que el resto del panel.
- Estado: ✅ Completado en Sprint 6.1. Livewire en `Host/Billing/Http/Livewire/BillingOverview.php`.

### Payment Detail / Invoice  ⏳ [pendiente]

- Ruta: `/host/billing/invoices/{invoice}`
- Contexto → Módulo: Host → Billing
- Propósito: detalle de una transacción individual, estado, reintentos.
- Actions invocadas: `Host/Billing::GetInvoiceDetailAction`
- Componentes Flux: `flux:badge` (estado del pago)
- Notas UI_RULES: sin logos decorativos de gateway.
- Estado: ⏳ Pendiente. No implementado.

## 2.4 Provisioning

### Provisioning Queue / Logs  ⏳ [pendiente]

- Ruta: `/host/provisioning`
- Contexto → Módulo: Host → Provisioning
- Propósito: visibilidad de Jobs de aprovisionamiento en curso, fallidos, reintentables.
- Actions invocadas: `Host/Provisioning::ListProvisioningJobsAction`
- Componentes Flux: `flux:table`, `flux:badge`
- Notas UI_RULES: vista operacional, puede ser más densa.
- Estado: ⏳ Pendiente. No implementado.

## 2.5 Features

### Feature Flags Admin  ✅ [completado]

- Ruta: `/host/features`
- Contexto → Módulo: Host → Features
- Propósito: crear features, asignar/desasignar a planes, eliminar.
- Actions invocadas: `Host/Features::AssignFeatureToPlanAction`, creación/eliminación directa vía Feature model
- Componentes Flux: `flux:table`, `flux:button`, toggles por plan
- Notas UI_RULES: toggle activo usa `accent`.
- Estado: ✅ Completado en Sprint 6.1 + Sprint 8.x (CRUD completo con create, delete, assign/detach). Livewire en `Host/Features/Http/Livewire/FeaturesList.php`.

## 2.6 Monitoring

### Monitoring Dashboard  ✅ [completado]

- Ruta: `/host/dashboard`
- Contexto → Módulo: Host → Monitoring
- Propósito: salud del sistema — total tenants, suscripciones activas, total usuarios.
- Actions invocadas: `Host/Monitoring::GetSystemHealthMetricsAction`
- Componentes Flux: 3 `flux:card` con métricas numéricas
- Notas UI_RULES: sin gráficos aún (pendiente Chart.js).
- Estado: ✅ Completado en Sprint 6.1. Livewire en `Host/Monitoring/Http/Livewire/HostDashboard.php`.

---

# 3. Tenant

Panel del cliente. Scope mínimo, sin lógica vertical (ver nota de alcance al inicio del documento).

## 3.1 Dashboard

### Tenant Home  ⏳ [pendiente]

- Ruta: `/app` (actualmente `/tenant/users`)
- Contexto → Módulo: Tenant (sin módulo específico)
- Propósito: overview del workspace — accesos rápidos a Users/Teams/Branding/API Keys.
- Actions invocadas: lectura de plan vía `Host/Plans::GetTenantPlanAction` (cross-BC)
- Componentes Flux: accesos con íconos, no cards.
- Notas UI_RULES: debe sentirse idéntico al Host en tratamiento visual.
- Estado: ⏳ Pendiente. Actualmente la landing del tenant es `/tenant/users`. No hay un dashboard dedicado.

## 3.2 Users

### Team Members List  ✅ [completado]

- Ruta: `/tenant/users`
- Contexto → Módulo: Tenant → Users
- Propósito: listado de usuarios del tenant.
- Actions invocadas: lectura directa `TenantUser::where('tenant_id', ...)`
- Componentes Flux: `flux:table`
- Notas UI_RULES: el aislamiento de tenant lo provee el `TenantContext` en el Action/Modelo.
- Estado: ✅ Completado en Sprint 6.2. Livewire en `Tenant/Users/Http/Livewire/UsersList.php`.

## 3.3 Teams

### Teams List / Detail  ✅ [completado]

- Ruta: `/tenant/teams`
- Contexto → Módulo: Tenant → Teams
- Propósito: listado de equipos con conteo de miembros.
- Actions invocadas: lectura directa `Team::withCount('members')`
- Componentes Flux: `flux:table`
- Notas UI_RULES: sin color decorativo por equipo.
- Estado: ✅ Completado en Sprint 6.2. Livewire en `Tenant/Teams/Http/Livewire/TeamsList.php`.

## 3.4 Branding

### Branding Settings  ✅ [completado]

- Ruta: `/tenant/branding`
- Contexto → Módulo: Tenant → Branding
- Propósito: logo URL, primary color, secondary color.
- Actions invocadas: `Tenant/Branding::UpdateBrandingAction`, `GetBrandingConfigAction`
- Componentes Flux: `flux:input`, `flux:button`
- Notas UI_RULES: los colores del tenant no sobreescriben el theme del panel SaaSiFy.
- Estado: ✅ Completado en Sprint 6.2. Livewire en `Tenant/Branding/Http/Livewire/BrandingForm.php`.

## 3.5 API Keys

### API Keys Management  ✅ [completado]

- Ruta: `/tenant/api-keys`
- Contexto → Módulo: Tenant → API Keys
- Propósito: generar y revocar API keys.
- Actions invocadas: `Tenant/ApiKeys::GenerateApiKeyAction`, `RevokeApiKeyAction`
- Componentes Flux: `flux:table`, `flux:button`
- Notas UI_RULES: la key generada se muestra una sola vez.
- Estado: ✅ Completado en Sprint 6.2. Livewire en `Tenant/ApiKeys/Http/Livewire/ApiKeysList.php`.

## 3.6 Billing (vista tenant-facing)  ⏳ [pendiente]

- Ruta: `/app/settings/billing`
- Contexto → Módulo: Tenant, datos vía Actions de Host/Billing
- Propósito: el tenant ve su plan actual, próxima factura, historial.
- Actions invocadas: `Host/Billing::GetTenantBillingSummaryAction`
- Componentes Flux: `flux:table`, `flux:badge`
- Notas UI_RULES: mismo tratamiento visual que Host Billing.
- Estado: ⏳ Pendiente. No implementado. Requiere definir contrato formal de cómo Tenant consume Actions de Host.

---

# 4. Resumen de flags abiertos (requieren tu decisión antes de implementar)

1. ~~Register wizard: ¿provisioning síncrono o asíncrono?~~ ✅ **Resuelto:** asíncrono — el wizard termina en pantalla de éxito, la provisioning corre en Jobs.
2. ⏳ **Tenant Home dashboard:** falta una landing page dedicada para el panel tenant (hoy redirige a `/tenant/users`).
3. ⏳ **Tenant Detail (Host):** no hay vista de detalle individual de tenant.
4. ⏳ **Billing tenant-facing:** falta el contrato formal de cómo Tenant invoca Actions públicas de Host sin romper dependencias.
5. ⏳ **Host Users management:** no hay pantalla para gestionar usuarios staff (HostUser CRUD).
6. ⏳ **Provisioning logs:** no hay vista de seguimiento de Jobs de provisioning.

---

# 5. Mapa de rutas vs implementación

| Ruta | Estado | Sprint |
|---|---|---|
| `/` | ✅ | 6.3 |
| `/pricing` | ✅ | 6.3 |
| `/register` | ✅ | 6.3 |
| `/contact` | ✅ | 6.3 |
| `/host/login` | ✅ | 6.1 |
| `/host/dashboard` | ✅ | 6.1 |
| `/host/tenants` | ✅ | 6.1 |
| `/host/plans` | ✅ | 6.1 |
| `/host/features` | ✅ | 6.1 + 8.x |
| `/host/billing` | ✅ | 6.1 |
| `/host/users` | ⏳ | — |
| `/host/tenants/{id}` | ⏳ | — |
| `/host/billing/invoices/{id}` | ⏳ | — |
| `/host/provisioning` | ⏳ | — |
| `/tenant/login` | ✅ | 6.2 |
| `/tenant/users` | ✅ | 6.2 |
| `/tenant/teams` | ✅ | 6.2 |
| `/tenant/branding` | ✅ | 6.2 |
| `/tenant/api-keys` | ✅ | 6.2 |
| `/tenant/builder` | ✅ | 8.3 |
| `/app` (tenant home) | ⏳ | — |
| `/app/settings/billing` | ⏳ | — |

---

# Checklist por vista (antes de dar por terminada cualquier implementación)

- [ ] Aparece en este documento con Contexto → Módulo correcto
- [ ] Todas las Actions invocadas están nombradas explícitamente (no "TODO: conectar backend")
- [ ] Ningún acceso directo a Models de otro módulo — solo Actions públicas/Contracts
- [ ] Cumple `UI_RULES.md` (tokens, tabla de componentes, prohibiciones)
- [ ] Si cruza Bounded Context, el contrato de comunicación está documentado antes del código
- [ ] Tiene test de aislamiento de tenant si renderiza datos tenant-scoped
