# ROADMAP UI — Arquitectura SaaS Multitenant
> **Stack:** Laravel + Blade + Livewire + FluxUI | **Design System:** Flux UI (Tailwind)
> **Duración por sprint:** 2 semanas | **Total estimado:** 12 sprints (~6 meses)
> **Convención:** actualizar los `[x]` completados y recalcular métricas al cierre de cada sprint.

---

## 📊 Panel de Avance del Proyecto UI

### Avance Global

| Métrica                  | Valor |
| ------------------------ | ----- |
| **Total de tareas**      | 112   |
| **Completadas**          | 45    |
| **Pendientes**           | 67    |
| **% Global**             | 40%   |
| **Última actualización** | 2026-06-30 |

### Status

- [ ] Pendiente
- [~] En progreso
- [x] Completado

### Avance por Fase

| Fase                                   | Sprints | Tareas | Completadas | %   |
| -------------------------------------- | ------- | ------ | ----------- | --- |
| 🧱 Fase UI-1 — Design System & Layouts  | U01     | 9      | 0           | 0%  |
| 🔐 Fase UI-2 — Auth (Central + Tenant)  | U02     | 10     | 9           | 90% |
| 🏠 Fase UI-3 — Shells & Navegación      | U03     | 8      | 2           | 25% |
| 💳 Fase UI-4 — Billing & Provisioning   | U04–U05 | 25     | 15          | 60% |
| 🏢 Fase UI-5 — Tenant Core              | U06–U07 | 22     | 13          | 59% |
| 🚀 Fase UI-6 — Features Avanzados       | U08–U09 | 17     | 8           | 47% |
| 📊 Fase UI-7 — Analytics, Support & Ops | U10–U11 | 13     | 7           | 54% |
| 🌐 Fase UI-8 — Público & Go-Live UI     | U12     | 8      | 5           | 63% |

### Avance por Sprint

| Sprint | Nombre                                        | Módulo(s)                                                         | Tareas | ✅   | %   | Estado        |
| ------ | --------------------------------------------- | ----------------------------------------------------------------- | ------ | --- | --- | ------------- |
| U01    | Design System & Componentes Globales          | `Shared`                                                          | 9      | 0   | 0%  | ⬜ No iniciado |
| U02    | Auth — Host & Tenant                          | `Central/Auth` · `Tenant/Identity`                                | 10     | 9   | 90% | ✅ Casi completo |
| U03    | Shells de Navegación                          | `Central` · `Tenant` · `Shared`                                   | 8      | 2   | 25% | ⬜ En progreso |
| U04    | Billing — Planes, Suscripciones & Pagos       | `Central/Billing` · `Central/Payments`                            | 14     | 11  | 79% | ✅ Casi completo |
| U05    | Provisioning — Jobs, Onboarding & Tenants     | `Central/Provisioning`                                            | 11     | 4   | 36% | ⬜ En progreso |
| U06    | Tenant Identity — Usuarios, Roles & SSO       | `Tenant/Identity`                                                 | 13     | 8   | 62% | ⬜ En progreso |
| U07    | Tenant Core — Settings, Branding & Audit      | `Tenant/Settings` · `Tenant/Audit`                                | 9      | 5   | 56% | ⬜ En progreso |
| U08    | Tenant Avanzado — Notificaciones, Uso & Datos | `Tenant/Notifications` · `Tenant/Settings` · `Tenant/DataManagement` | 9   | 5   | 56% | ⬜ En progreso |
| U09    | Tenant Integraciones & Feature Flags          | `Tenant/Integrations` · `Central/Features`                        | 8      | 8   | 100%| ✅ Completado |
| U10    | Analytics & Reporting                         | `Central/Analytics`                                               | 4      | 1   | 25% | ⬜ En progreso |
| U11    | Support, Security & Operations                | `Central/Support` · `Central/Security` · `Central/Monitoring`     | 9      | 6   | 67% | ⬜ En progreso |
| U12    | Público — Landings, Marketing & Go-Live       | `Central/Marketing` · `routes/web.php`                            | 8      | 5   | 63% | ⬜ En progreso |

---

## 🧱 FASE UI-1 — Design System & Componentes Globales

> **Objetivo:** Todos los componentes Blade globales implementados y funcionando con el Design System definido.

---

### Sprint U01 — Design System & Componentes Globales
**Módulo:** `Shared/Http` · `resources/views/components/`
**Entregable:** Los 9 componentes globales implementados, documentados y visualmente validados.

**Nota:** El código actual usa **Flux UI** (`flux:button`, `flux:card`, `flux:modal`, etc.) directamente en las vistas, no componentes Blade personalizados. Existen layouts en `resources/views/layouts/` pero con nombres diferentes al roadmap (`central.blade.php`, `app.blade.php`, `marketing.blade.php`).

- [ ] Configurar `tailwind.config.js` con tokens del Design System (opcional — Flux UI ya tiene su propio theme)
- [x] `<x-layout.host>` — **NO existe.** En su lugar existe `layouts/central.blade.php` (misma función, nombre diferente)
- [ ] `<x-layout.tenant>` — **NO existe.** Los tenant views usan `layouts/app.blade.php`
- [x] `<x-layout.public>` — **NO existe como `x-layout.public`.** Existe `layouts/marketing.blade.php`
- [ ] `<x-table>` — **NO existe.** Las tablas se construyen con `flux:table` directamente
- [ ] `<x-modal>` — **NO existe.** Se usa `flux:modal` directamente
- [ ] `<x-alert>` — **NO existe.** Se usa `flux:text color="emerald"` para feedback
- [ ] `<x-badge>` — **NO existe.** Se usa `flux:badge` directamente
- [ ] `<x-empty-state>` y `<x-skeleton>` — **NO existen**

---

## 🔐 FASE UI-2 — Auth

> **Objetivo:** Flujos de autenticación completos para host y tenant, con estados de error, 2FA y SSO.

---

### Sprint U02 — Auth — Host & Tenant
**Módulos:** `Central/Auth` · `Tenant/Identity`
**Entregable:** Todos los flujos de login, 2FA, recuperación e invitación funcionando en Blade + Livewire.

**`Central/Auth` — Host**

- [x] `Central/Auth/UI/pages/login.blade.php` + `Login.php` Livewire — Login host (email/password, toggle visibilidad, estados: nominal / error / bloqueado / cargando)
- [x] `Central/Auth/UI/pages/challenge.blade.php` + `LoginChallenge.php` Livewire — Verificación 2FA (input 6 dígitos, código de recuperación)
- [x] `Central/Auth/UI/pages/forgot-password.blade.php` + `ForgotPassword.php` Livewire — Recuperación
- [x] `Central/Auth/UI/pages/reset-password.blade.php` + `ResetPassword.php` Livewire — Reset
- [x] `Central/Auth/UI/livewire/two-factor-enrollment.blade.php` + `TwoFactorEnrollment.php` — Enrollment 2FA
- [x] `Central/Auth/UI/pages/dashboard.blade.php` + `Dashboard.php` — Dashboard host post-login
- [ ] `Central/Auth/Livewire/ImpersonationLog.php` — **NO existe** (impersonation audit log como pantalla dedicada)

**`Tenant/Identity` — Tenant**

- [x] `Tenant/Identity/UI/livewire/login.blade.php` + `Login.php` — Login tenant (white-label)
- [x] `Tenant/Identity/UI/livewire/accept-invitation.blade.php` + `AcceptInvitation.php` — Aceptación de invitación con password policy
- [x] `Tenant/Identity/UI/livewire/login-challenge.blade.php` + `LoginChallenge.php` — 2FA tenant
- [x] `Tenant/Identity/UI/livewire/two-factor-enrollment.blade.php` + `TwoFactorEnrollment.php` — Enrollment 2FA tenant

---

## 🏠 FASE UI-3 — Shells & Navegación

> **Objetivo:** Los dos shells (host y tenant) funcionan completos con navegación real y guards activos.

---

### Sprint U03 — Shells de Navegación
**Módulos:** `Central/Providers` · `Tenant/Identity` · `Shared/Http`

- [x] ServiceProviders por módulo — Cada módulo tiene su propio provider registrando vistas y Livewire
- [x] Rutas web por módulo — `Central/Auth/Routes/web.php`, `Central/Billing/Routes/web.php`, etc.
- [ ] View Composer `BrandingComposer` — **NO existe.** Los valores de branding se pasan manualmente en cada componente
- [ ] `<x-host-nav-item>` — **NO existe.** La navegación está hardcodeada en `layouts/central/sidebar.blade.php`
- [ ] `<x-tenant-nav-item>` — **NO existe.** Ídem
- [ ] Guards `host` y `tenant` separados — **NO.** Se usa `auth:central` y `auth` (web) de Laravel/Fortify
- [ ] `ResolveTenantMiddleware` — **NO existe como middleware dedicado.** La resolución de tenant ocurre en `InitializeTenancyByDomain`
- [x] Sidebar central — Existe en `layouts/central/sidebar.blade.php`

---

## 💳 FASE UI-4 — Billing & Provisioning

> **Objetivo:** El backoffice host tiene UI completa para gestionar planes, suscripciones, pagos y el ciclo de vida de provisioning de tenants.

---

### Sprint U04 — Billing — Planes, Suscripciones & Pagos
**Módulos:** `Central/Billing` · `Central/Payments`

**`Central/Billing`**

- [x] `Billing/UI/pages/plan-list.blade.php` + `PlanList.php` — Cards de planes (nombre, precio, tenant count)
- [x] `Billing/UI/pages/manage-plan.blade.php` + `ManagePlan.php` — Formulario de plan (quotas, features, grace period)
- [x] `Billing/UI/pages/subscription-list.blade.php` + `SubscriptionList.php` — Tabla de suscripciones
- [ ] `Billing/Livewire/SubscriptionDetail.php` — **NO existe** como pantalla dedicada
- [ ] `Billing/Livewire/ReportsView.php` — **NO existe** (reportes financieros con MRR/chart)
- [x] `Billing/UI/pages/global-invoice-list.blade.php` + `GlobalInvoiceList.php` — Lista global de facturas
- [x] `Billing/UI/pages/tenant-invoice-list.blade.php` + `TenantInvoiceList.php` — Facturas por tenant
- [x] `Billing/Livewire/ManageBilling.php` — Gestión de billing del tenant

**`Central/Payments`**

- [ ] `Payments/Livewire/GatewaySettings.php` — **NO existe** (configuración de pasarela)
- [ ] `Payments/Livewire/WebhookLog.php` — **NO existe** (log de webhooks entrantes)
- [x] `Payments/Livewire/PayoutRequests.php` — Gestión de payouts
- [x] `Payments/Livewire/PayoutSettings.php` — Configuración de payouts
- [x] `Payments/Livewire/CheckoutComponent.php` — Checkout

---

### Sprint U05 — Provisioning — Jobs, Onboarding & Tenants
**Módulo:** `Central/Provisioning`

- [ ] `Provisioning/Livewire/ProvisioningPanel.php` — **NO existe** (tabs: en progreso/completados/fallidos)
- [ ] `Provisioning/Livewire/ProvisioningJobDetail.php` — **NO existe** (timeline de pasos)
- [ ] `Provisioning/Livewire/TenantWizard.php` — **NO existe** (wizard 3 pasos). Existe `CreateTenant.php` pero es simple
- [x] `Provisioning/UI/pages/tenant-list.blade.php` + `TenantList.php` — Tabla de tenants
- [x] `Provisioning/UI/pages/manage-tenant.blade.php` + `ManageTenant.php` — Detalle de tenant
- [x] `Provisioning/UI/pages/create-tenant.blade.php` + `CreateTenant.php` — Creación de tenant
- [ ] `Provisioning/Livewire/ChangePlan.php` — **NO existe**
- [ ] `Provisioning/Livewire/SuspendTenant.php` — **NO existe** como componente modal
- [ ] `Provisioning/Livewire/ArchiveTenant.php` — **NO existe** como componente modal
- [ ] `provisioning-stepper.blade.php` — **NO existe**
- [x] **Extra:** `ProvisioningTenantJob` — El job de provisioning registra pasos en `ProvisioningLog`

---

## 🏢 FASE UI-5 — Tenant Core

> **Objetivo:** Los admins de tenants tienen UI completa para gestionar su organización.

---

### Sprint U06 — Tenant Identity — Usuarios, Roles & SSO
**Módulo:** `Tenant/Identity`

- [x] `Identity/Livewire/TeamManagement.php` — **EXISTE** (cumple función de `UserTable`)
- [ ] `Identity/Livewire/UserEdit.php` — **NO existe**
- [ ] `Identity/Livewire/InviteUser.php` — **NO existe** (la invitación se hace desde TeamManagement)
- [x] `Identity/UI/livewire/role-management.blade.php` + `RoleManagement.php` — Gestión de roles (cumple función de `RoleList` + `RoleForm`)
- [ ] `Identity/Livewire/PasswordPolicy.php` — **NO existe**
- [ ] `Identity/Livewire/SsoSettings.php` — **NO existe**
- [ ] `Identity/Livewire/SessionManager.php` — **NO existe**
- [ ] `quota-bar.blade.php` — **NO existe** (la barra de cuota está inline en `UsageOverview`)
- [x] **Extra:** `Identity/Livewire/ManageApiKeys.php` — Gestión de API keys
- [x] **Extra:** `Identity/Livewire/TwoFactorEnrollment.php` — Enrollment 2FA tenant
- [x] **Extra:** `Identity/Livewire/DataExport.php` — Export de datos
- [x] **Extra:** `Identity/Livewire/NotificationCenter.php` — Centro de notificaciones (vive en Identity)

---

### Sprint U07 — Tenant Core — Settings, Branding & Audit
**Módulos:** `Tenant/Settings` · `Tenant/Audit`

- [ ] `Settings/Livewire/GeneralSettings.php` — **NO existe** como componente unificado
- [x] `Settings/Livewire/LocalizationSettings.php` — Timezone, locale, currency
- [x] `Settings/Livewire/BrandingSettings.php` — Upload logo, colores, white-label
- [x] `Settings/Livewire/SmtpSettings.php` — Configuración SMTP del tenant
- [x] `Settings/Livewire/UsageOverview.php` — Dashboard de uso y cuotas
- [ ] `CustomDomainManager` — **NO existe** como Livewire (el custom domain se maneja desde provisioning)
- [x] `Audit/Livewire/AuditLogViewer.php` — Audit trail (filtros, tabla, export CSV)
- [ ] **Extra:** `Settings/UI/livewire/branding-settings.blade.php` — White-label con preview de colores

---

## 🚀 FASE UI-6 — Features Avanzados

> **Objetivo:** Notificaciones, uso y cuotas, gestión de datos, integraciones y feature flags con UI completa.

---

### Sprint U08 — Tenant Avanzado — Notificaciones, Uso & Datos
**Módulos:** `Tenant/Notifications` · `Tenant/Settings` · `Tenant/DataManagement`

- [x] `Notifications/Livewire/ManageNotificationTemplates.php` — Gestión de plantillas
- [x] `Notifications/Livewire/NotificationPreferences.php` — Preferencias por usuario
- [ ] `Notifications/Livewire/NotificationCenter.php` — **EXISTE pero en `Tenant/Identity`** (debería migrarse a Notifications)
- [x] `Settings/Livewire/UsageOverview.php` — Dashboard de consumo (cards con progreso)
- [ ] `Usage/Livewire/UsageDashboard.php` — Versión mejorada con gráficos e historial de alertas (NO existe)
- [x] `DataManagement/Livewire/ManageDataImports.php` — Import de datos
- [x] `DataManagement/Livewire/ManageBackups.php` — Backup on-demand
- [x] `DataManagement/Livewire/RetentionSettings.php` — Políticas de retención

---

### Sprint U09 — Tenant Integraciones & Feature Flags
**Módulos:** `Tenant/Integrations` · `Central/Features`

- [x] `Integrations/Livewire/ManageWebhooks.php` — CRUD de webhooks outbound
- [x] `Integrations/Livewire/WebhookDeliveryLog.php` — Log de delivery, dead-letter queue, retry
- [x] `Identity/Livewire/ManageApiKeys.php` — API keys con scopes
- [x] `Features/Livewire/FeatureList.php` — Feature flags list
- [x] `Features/Livewire/ManageFeature.php` — Creación/edición con targeting
- [x] `Features/Livewire/TenantOverrides.php` — Overrides por tenant
- [x] `Features/Livewire/FeatureChangeHistory.php` — Historial de cambios

---

## 📊 FASE UI-7 — Analytics, Support & Operations

> **Objetivo:** El equipo host tiene visibilidad total de la salud de la plataforma.

---

### Sprint U10 — Analytics & Reporting
**Módulo:** `Central/Analytics`

- [x] `Analytics/Livewire/AnalyticsDashboard.php` — Dashboard con MRR, churn, tenant statuses
- [ ] `Analytics/Livewire/FinancialReports.php` — **NO existe** (reportes financieros detallados)
- [ ] `kpi-card.blade.php` — **NO existe** (las cards están inline en el dashboard)
- [ ] `trend-chart.blade.php` — **NO existe** (no hay wrapper de Chart.js)

---

### Sprint U11 — Support, Security & Operations
**Módulos:** `Central/Support` · `Central/Security` · `Central/Monitoring`

- [x] `Support/Livewire/TicketList.php` — Listado de tickets
- [x] `Support/Livewire/CreateTicket.php` — Creación de ticket
- [x] `Support/Livewire/ManageTicket.php` — Detalle de ticket con auditoría
- [x] `Support/Livewire/BroadcastCenter.php` — Broadcast/announcements
- [x] `Support/Livewire/TenantSupportBitacora.php` — Notas de soporte por tenant
- [ ] `Security/Livewire/GlobalAudit.php` — **NO existe** (el audit log es tenant-scoped)
- [x] `Security/Livewire/SecurityPolicies.php` — Políticas de encriptación y retención
- [x] `Monitoring/Livewire/MonitoringDashboard.php` — Health checks, alertas
- [x] `Monitoring/Livewire/LogViewer.php` — Log centralizado

---

## 🌐 FASE UI-8 — Público & Go-Live UI

> **Objetivo:** La superficie pública de adquisición está operativa.

---

### Sprint U12 — Público — Landings, Marketing & Go-Live
**Módulos:** `Central/Marketing` · `routes/web.php`

- [x] `Marketing/Livewire/LandingPage.php` — Landing con Hero, pricing dinámico desde BD
- [x] `Marketing/UI/pages/landing-page.blade.php` — Vista con planes, CTA, branding
- [x] `Marketing/Livewire/RegisterTenant.php` — Registro multi-step (org info → plan → payment)
- [x] `Marketing/Livewire/ManageLegalDocuments.php` — Gestión de términos/privacidad versionados
- [x] `Marketing/UI/pages/public-legal.blade.php` — Rutas públicas /terms, /privacy
- [x] **Extra:** `Landings/Livewire/LandingBuilder.php` — Builder de landing pages para tenants (13 tipos de bloques)
- [ ] Go-live checklist UI — **NO ejecutado** (descrito en `docs/GO-LIVE.md`)
- [ ] Single-File Components — **NO implementados** (opcionales)

---

## 📋 Resumen de Componentes Existentes NO Contemplados en el Roadmap Original

Estos componentes existen en el código pero no estaban en el ROADMAP_UI.md:

| Componente | Módulo | Propósito |
|---|---|---|
| `PlatformBranding` | Central/Settings | Branding global de la plataforma |
| `CheckoutComponent` | Central/Payments | Checkout de pagos |
| `PayoutRequests` | Central/Payments | Solicitudes de payout |
| `PayoutSettings` | Central/Payments | Configuración de payouts |
| `BroadcastCenter` | Central/Support | Broadcast/announcements masivos |
| `GlobalAnnouncements` | Central/Support | Anuncios globales en UI |
| `TenantSupportBitacora` | Central/Support | Notas de soporte por tenant |
| `LandingBuilder` | Central/Landings | Builder de landings (13 bloques) |
| `LogViewer` | Central/Monitoring | Visor de activity log |
| `TenantOverrides` | Central/Features | Override de features por tenant |
| `FeatureChangeHistory` | Central/Features | Historial de cambios en features |

## 🏗️ Deuda Técnica UI Detectada

1. **Dos convenciones de directorios de vistas**: Algunos módulos usan `UI/` (vía `loadViewsFrom(__DIR__.'/../UI', ...)`) y otros usan `Resources/views/` (`Central/Payments`, `Central/Landings`). Unificar bajo una sola convención.
2. **`NotificationCenter` vive en `Tenant/Identity`** pero debería estar en `Tenant/Notifications`. Migrar componente y registro.
3. **`PlanList` y `ManagePlan`** no están registrados via `Livewire::component()` (funcionan porque se usan como full-page routes directamente).
4. **Los layouts del roadmap** (`x-layout.host`, `x-layout.tenant`, `x-layout.public`) no existen como componentes Blade. Las vistas usan `#[Layout('layouts.central')]`, `#[Layout('layouts.app')]`, `#[Layout('layouts.marketing')]` directamente en los Livewire components.

---

## Apéndice — Convenciones de Implementación UI

### Estructura actual por módulo

```
Modules/{Scope}/{Módulo}/
├── Livewire/
│   ├── ComponentName.php      ← lógica del componente
│   └── ...
└── UI/                         ← o Resources/views/ en algunos módulos
    ├── livewire/
    │   └── component-name.blade.php
    └── pages/
        └── page-name.blade.php
```

### Registro en ServiceProvider (convención actual)

```php
// Vistas
$this->loadViewsFrom(__DIR__ . '/../UI', 'billing');

// Rutas web
$this->app->booted(function () {
    Route::middleware(['web', 'auth:central'])->group(function () {
        Route::get('/central/billing/plans', PlanList::class)->name(...);
    });
});

// Componentes Livewire
Livewire::component('billing-plan-list', PlanList::class);
```

---

*Documento de uso interno del equipo de ingeniería. Actualizado al 2026-06-30 reflejando el estado real del código.*
