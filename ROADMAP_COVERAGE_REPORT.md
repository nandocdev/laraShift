# Reporte de Cobertura Real vs ROADMAP.md

> **Generado:** 2026-06-29  
> **Metodología:** Análisis estático del código fuente (controllers, services, models, middleware, migrations, tests, Livewire components, rutas, config) comparado contra cada ítem del ROADMAP.md.

---

## 🔍 Resumen General

| Métrica          | ROADMAP   | Real           | Diferencia     |
| ---------------- | --------- | -------------- | -------------- |
| **Total tareas** | 162       | —              | —              |
| **Completadas**  | 152 (94%) | **~115 (71%)** | **-37 tareas** |
| **Pendientes**   | 10        | **~47**        | +37            |

> El ROADMAP sobreestima significativamente el progreso real. Múltiples funcionalidades están reportadas como completadas pero no tienen implementación en el código, tienen implementación parcial, o son placeholder.

---

## 📊 Avance Real por Fase

| Fase                          | ROADMAP      | Real             | Diferencia |
| ----------------------------- | ------------ | ---------------- | ---------- |
| 🏗️ F1 — Fundaciones            | 100% (38/38) | **~82% (31/38)** | -18%       |
| 🔐 F2 — Auth & Tenancy         | 100% (30/30) | **~77% (23/30)** | -23%       |
| 💳 F3 — Billing & Provisioning | 100% (38/38) | **~76% (29/38)** | -24%       |
| 🏢 F4 — Tenant Core            | 84% (21/25)  | **~64% (16/25)** | -20%       |
| 🚀 F5 — Features Avanzados     | 95% (19/20)  | **~60% (12/20)** | -35%       |
| 🔒 F6 — Hardening & Compliance | 82% (9/11)   | **~45% (5/11)**  | -37%       |

---

## 🏗️ FASE 1 — Fundaciones

### S01 — Infraestructura Base (ROADMAP: 100% ✅ → Real: 80% ⚠️)

| #   | Tarea                                                  | ROADMAP | Real                  | Evidencia                                        |
| --- | ------------------------------------------------------ | ------- | --------------------- | ------------------------------------------------ |
| 1   | Inicializar repo con módulos (host/, shared/, tenant/) | ✅       | ✅                     | `app/Modules/Central/`, `Tenant/`, `Shared/`     |
| 2   | Configurar entornos (.env, staging, production)        | ✅       | ✅                     | `.env.example`, `config/` completo               |
| 3   | Levantar DB host con migraciones                       | ✅       | ✅                     | Migraciones centrales + RLS                      |
| 4   | Configurar Docker Compose                              | ✅       | ✅                     | `sail` script presente                           |
| 5   | Pipeline CI (lint, tests, build)                       | ✅       | ❌ **NO IMPLEMENTADO** | No hay `.github/workflows/` con pipelines reales |
| 6   | Configurar gestor de colas (Redis/Horizon)             | ✅       | ✅                     | `config/horizon.php`, `HorizonServiceProvider`   |
| 7   | Configurar storage S3                                  | ✅       | ✅                     | `config/filesystems.php`, `media-library`        |
| 8   | Configurar email transaccional                         | ✅       | ✅                     | `config/mail.php`, notificaciones                |
| 9   | ADR-001: decisiones de stack base                      | ✅       | ✅                     | `docs/`, `brain/`                                |
| 10  | ADR-002: estrategia de aislamiento                     | ✅       | ✅                     | RLS + `tenant_id` en todas las tablas            |

### S02 — Shared Layer: Contratos y Eventos (ROADMAP: 100% ✅ → Real: 67% ⚠️)

| #   | Tarea                                  | ROADMAP | Real                  | Evidencia                                                                                          |
| --- | -------------------------------------- | ------- | --------------------- | -------------------------------------------------------------------------------------------------- |
| 1   | Ports/Interfaces cross-layer           | ✅       | ✅                     | `Shared/Contracts/` (8 contratos)                                                                  |
| 2   | Value Objects base                     | ✅       | ⚠️ **PARCIAL**         | Enums (`PaymentContext`, `AuditAction`), DTOs, pero sin VOs completos (`Money`, `UUID` como clase) |
| 3   | Modelos base abstractos                | ✅       | ✅                     | Traits `HasTenant`, `BelongsToTenant`, scopes globales                                             |
| 4   | Envelope de evento con versionado      | ✅       | ✅                     | Eventos con namespace, trait `ShouldBroadcast`                                                     |
| 5   | Outbox pattern                         | ✅       | ❌ **NO IMPLEMENTADO** | No existe tabla `outbox_events` ni worker de publicación                                           |
| 6   | Dead Letter Queue                      | ✅       | ❌ **NO IMPLEMENTADO** | No hay DLQ ni tabla de reintentos configurable                                                     |
| 7   | Catálogo inicial de eventos            | ✅       | ✅                     | 19 eventos de dominio en `Shared/Events/`                                                          |
| 8   | Publisher/subscriber base              | ✅       | ❌ **NO IMPLEMENTADO** | No hay publisher abstracto; los eventos se disparan con `event()` de Laravel                       |
| 9   | Tests de contrato publisher/subscriber | ✅       | ❌ **NO IMPLEMENTADO** | No existen tests de contrato                                                                       |

### S03 — Shared Layer: Tenancy Core (ROADMAP: 100% ✅ → Real: 80% ⚠️)

| #   | Tarea                                        | ROADMAP | Real                  | Evidencia                                      |
| --- | -------------------------------------------- | ------- | --------------------- | ---------------------------------------------- |
| 1   | TenantResolver (dominio, subdominio, header) | ✅       | ✅                     | `stancl/tenancy` + `InitializeTenancyByDomain` |
| 2   | Switching dinámico de conexiones DB          | ✅       | ✅                     | RLS Bootstrapper                               |
| 3   | Scoped queries con tenant_id enforcement     | ✅       | ✅                     | `TenantScope`, `BelongsToTenant`, RLS policies |
| 4   | Cache de tenant config con TTL               | ✅       | ✅                     | Cache bootstrapper en `config/tenancy.php`     |
| 5   | Fallback a modo central                      | ✅       | ✅                     | `PreventAccessFromCentralDomains` middleware   |
| 6   | Propagación async de tenant context          | ✅       | ✅                     | `QueueTenancyBootstrapper`                     |
| 7   | Background Jobs con prioridad/retry          | ✅       | ✅                     | Horizon (5 buckets × 3 prioridades)            |
| 8   | Tests de aislamiento                         | ✅       | ✅                     | `RLSIsolationTest.php`                         |
| 9   | Tests de chaos (DB caída)                    | ✅       | ❌ **NO IMPLEMENTADO** | No hay chaos tests                             |
| 10  | ADR-003: tenant context propagation          | ✅       | ⚠️ **PARCIAL**         | Documentación en `brain/` pero sin ADR formal  |

### S04 — Shared Layer: HTTP y Observabilidad (ROADMAP: 100% ✅ → Real: 56% ⚠️)

| #   | Tarea                                          | ROADMAP | Real                  | Evidencia                                        |
| --- | ---------------------------------------------- | ------- | --------------------- | ------------------------------------------------ |
| 1   | Middleware de tenant resolution                | ✅       | ✅                     | `InitializeTenancyByDomain`                      |
| 2   | Middleware de correlation ID                   | ✅       | ❌ **NO IMPLEMENTADO** | No existe middleware explícito de correlation ID |
| 3   | Propagación W3C TraceContext / OpenTelemetry   | ✅       | ❌ **NO IMPLEMENTADO** | No hay integración OTel                          |
| 4   | Exportador de trazas (Jaeger/Tempo)            | ✅       | ❌ **NO IMPLEMENTADO** | No configurado                                   |
| 5   | Logger estructurado tenant-aware               | ✅       | ✅                     | `spatie/laravel-activitylog` con contexto        |
| 6   | Formateador unificado de respuestas (envelope) | ✅       | ❌ **NO IMPLEMENTADO** | No hay envelope `data`/`meta`/`errors`           |
| 7   | Exception handler centralizado                 | ✅       | ✅                     | Páginas de error (403, 404, 419, 429, 500, 503)  |
| 8   | HTTP client con retry y circuit breaker        | ✅       | ✅                     | `RailwayService` usa `Http` con retry            |
| 9   | Rate limiting (IP y tenant)                    | ✅       | ✅                     | `ApplyTenantRateLimits`, Fortify rate limiters   |

---

## 🔐 FASE 2 — Auth & Tenancy

### S05 — Host Auth: Super-admin (ROADMAP: 100% ✅ → Real: 80% ⚠️)

| #   | Tarea                                 | ROADMAP | Real                  | Evidencia                                                                                |
| --- | ------------------------------------- | ------- | --------------------- | ---------------------------------------------------------------------------------------- |
| 1   | Auth super-admin (email/password)     | ✅       | ✅                     | `CentralUser`, Fortify, login/logout actions                                             |
| 2   | 2FA (TOTP)                            | ✅       | ✅                     | `Central2FA`, `TwoFactorEnrollment` Livewire                                             |
| 3   | SSO/OIDC para super-admins            | ✅       | ❌ **NO IMPLEMENTADO** | No existe integración SSO/OIDC                                                           |
| 4   | Token/session binding por tenant      | ✅       | ✅                     | `ValidateCentralSession`, `CentralSession`                                               |
| 5   | Resolución post-login según tenant    | ✅       | ✅                     | Fortify redirects                                                                        |
| 6   | Logout global con invalidación        | ✅       | ✅                     | `LogoutCentralUserAction`                                                                |
| 7   | Impersonación con audit               | ✅       | ✅                     | `ImpersonateTenantAction`, `AuditImpersonationActions`                                   |
| 8   | Rate limiting, brute-force protection | ✅       | ✅                     | Fortify rate limiters, `RevokeOldestSessionAction`                                       |
| 9   | Recuperación de credenciales          | ✅       | ✅                     | `CentralResetPasswordNotification`                                                       |
| 10  | Tests de seguridad                    | ✅       | ⚠️ **PARCIAL**         | `CentralAuth2FATest`, `ConcurrentSessionTest` — faltan tests de token reuse cross-tenant |

### S06 — Tenant Identity: Usuarios y Roles (ROADMAP: 100% ✅ → Real: 90% ✅)

| #   | Tarea                                     | ROADMAP | Real                  | Evidencia                                                |
| --- | ----------------------------------------- | ------- | --------------------- | -------------------------------------------------------- |
| 1   | CRUD usuarios tenant-scoped               | ✅       | ✅                     | `User` model, Fortify scoped                             |
| 2   | RBAC roles/permissions                    | ✅       | ✅                     | Spatie, `RoleManagement` Livewire                        |
| 3   | Registro/login recuperación tenant-scoped | ✅       | ✅                     | Fortify + tenant middleware                              |
| 4   | Password policies por tenant              | ✅       | ✅                     | `PasswordValidationRules`                                |
| 5   | Invitaciones de usuarios                  | ✅       | ✅                     | `Invitation`, `SendInvitationAction`, `AcceptInvitation` |
| 6   | Impersonación por admin del tenant        | ✅       | ❌ **NO IMPLEMENTADO** | Solo super-admin puede impersonar, no admin del tenant   |
| 7   | Límites de usuarios por plan              | ✅       | ✅                     | `QuotaManager` trackea staff usage                       |
| 8   | Middleware de autorización RBAC           | ✅       | ✅                     | `EnsureUserBelongsToTenant`, `EnsureUserIsActive`        |
| 9   | Tests de aislamiento cross-tenant         | ✅       | ✅                     | `TenantIAMTest`                                          |
| 10  | Tests de RBAC                             | ✅       | ✅                     | Roles/permissions scoping tested                         |

### S07 — Tenant Identity: SSO y Sesiones (ROADMAP: 100% ✅ → Real: 40% ❌)

| #   | Tarea                               | ROADMAP | Real                  | Evidencia                                                    |
| --- | ----------------------------------- | ------- | --------------------- | ------------------------------------------------------------ |
| 1   | SSO/SAML 2.0 por tenant             | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                                    |
| 2   | OIDC por tenant                     | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                                    |
| 3   | SCIM provisioning                   | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                                    |
| 4   | Gestión sesiones concurrentes       | ✅       | ✅                     | `CentralSession`, `RevokeOldestSessionAction`                |
| 5   | Invalidación sesiones por admin     | ✅       | ⚠️ **PARCIAL**         | Central sí, tenant no                                        |
| 6   | Refresh token rotation              | ✅       | ❌ **NO IMPLEMENTADO** | No hay JWT/refresh tokens                                    |
| 7   | Audit eventos de Identity           | ✅       | ✅                     | `TenantAuthAuditSubscriber`, `TenantIdentityEventSubscriber` |
| 8   | ADR-004: estrategia de sesiones     | ✅       | ⚠️ **PARCIAL**         | `brain/` tiene docs informales                               |
| 9   | Tests SSO/SAML/OIDC                 | ✅       | ❌ **NO IMPLEMENTADO** | No existen                                                   |
| 10  | Tests límites sesiones concurrentes | ✅       | ✅                     | `ConcurrentSessionTest`                                      |

---

## 💳 FASE 3 — Billing & Provisioning

### S08 — Host Billing: Suscripciones y Planes (ROADMAP: 100% ✅ → Real: 70% ⚠️)

| #   | Tarea                                 | ROADMAP | Real                  | Evidencia                                                                           |
| --- | ------------------------------------- | ------- | --------------------- | ----------------------------------------------------------------------------------- |
| 1   | CRUD de planes                        | ✅       | ✅                     | `Plan` model, `PlanManager`, `ManagePlan`/`PlanList`                                |
| 2   | CRUD de suscripciones                 | ✅       | ✅                     | `Subscription`, `SubscriptionList` Livewire                                         |
| 3   | Máquina de estados Tenant Lifecycle   | ✅       | ✅                     | `EnsureTenantIsActive` middleware (active/suspended/archived/maintenance/read_only) |
| 4   | Cálculo de prorrateo                  | ✅       | ❌ **NO IMPLEMENTADO** | No hay lógica de prorrateo                                                          |
| 5   | Grace periods configurables           | ✅       | ✅                     | `onGracePeriod()`, `trial_ends_at`                                                  |
| 6   | Ciclo de dunning                      | ✅       | ✅                     | `SubscriptionPaymentHandler::onFailed()` — 3 attempts                               |
| 7   | Descuentos, cupones, ajustes manuales | ✅       | ❌ **NO IMPLEMENTADO** | No existe sistema de cupones                                                        |
| 8   | Facturas PDF con envío                | ✅       | ✅                     | `Invoice`, `GenerateInvoicePdfAction`, dompdf                                       |
| 9   | Conciliación financiera               | ✅       | ✅                     | `ReconcileSubscriptionsCommand`, `SyncSubscriptionAction`                           |
| 10  | Reportes MRR y churn                  | ✅       | ❌ **NO IMPLEMENTADO** | No hay reportes MRR/churn                                                           |

### S09 — Host Payments: Pasarela y Webhooks (ROADMAP: 100% ✅ → Real: 89% ✅)

| #   | Tarea                                      | ROADMAP | Real                  | Evidencia                                                   |
| --- | ------------------------------------------ | ------- | --------------------- | ----------------------------------------------------------- |
| 1   | Integración con pasarela (dLocal)          | ✅       | ✅                     | `DlocalGateway` — API completa                              |
| 2   | Payment intents / subscriptions via API    | ✅       | ✅                     | `ProcessDirectPaymentAction`, `CheckoutManager`             |
| 3   | Almacenamiento seguro métodos de pago      | ✅       | ⚠️ **PARCIAL**         | Token vía Smart Fields, `pm_type`/`pm_last_four`            |
| 4   | Webhook receiver con verificación de firma | ✅       | ✅                     | `WebhookController`, HMAC verification                      |
| 5   | Handlers idempotentes                      | ✅       | ✅                     | `updateOrCreate`, `PaymentHandlerDispatcher`                |
| 6   | Reintentos con exponential backoff         | ✅       | ✅                     | `HandlePaymentFailure`, `ReconcileSubscriptionsCommand`     |
| 7   | Reconciliación discrepancias               | ✅       | ✅                     | `SyncSubscriptionAction`                                    |
| 8   | Reembolsos manuales desde backoffice       | ✅       | ❌ **NO IMPLEMENTADO** | `Refunded` status existe pero no hay UI/action de reembolso |
| 9   | Tests de idempotencia                      | ✅       | ✅                     | `PaymentsIntegrationTest`, `BillingFlowTest`                |

### S10 — Host Provisioning: ProvisioningJob (ROADMAP: 100% ✅ → Real: 70% ⚠️)

| #   | Tarea                                  | ROADMAP | Real                  | Evidencia                                                              |
| --- | -------------------------------------- | ------- | --------------------- | ---------------------------------------------------------------------- |
| 1   | ProvisioningJob con máquina de estados | ✅       | ✅                     | `ProvisionTenantJob` con subdomain/db_schema/infrastructure/admin_user |
| 2   | Dry run / validación pre-provisioning  | ✅       | ✅                     | `CreateTenantAction` valida slug único, plan, payment                  |
| 3   | Paso DB_CREATED                        | ✅       | ✅                     | `SetupTenantCoreDataAction` + `TenantDataSeeder`                       |
| 4   | Paso MIGRATED                          | ✅       | ⚠️ **NO-OP**           | Single-DB: no hay migraciones separadas por tenant                     |
| 5   | Paso DNS_CONFIGURED                    | ✅       | ✅                     | `ReserveTenantDomainAction`                                            |
| 6   | Paso SSL_ISSUED                        | ✅       | ❌ **NO IMPLEMENTADO** | No hay emisión SSL (placeholder)                                       |
| 7   | Paso READY + evento TenantProvisioned  | ✅       | ✅                     | `TenantProvisioned` event                                              |
| 8   | Idempotencia por paso                  | ✅       | ✅                     | `ProvisioningLog` con `firstOrCreate`                                  |
| 9   | Resume desde paso de fallo             | ✅       | ⚠️ **PARCIAL**         | `$tries = 3` pero sin `retry($fromStep)` explícito                     |
| 10  | Chaos tests (20 tests)                 | ✅       | ❌ **NO IMPLEMENTADO** | No existen chaos tests para provisioning                               |

### S11 — Host Provisioning: Offboarding y Recovery (ROADMAP: 100% ✅ → Real: 67% ⚠️)

| #   | Tarea                                   | ROADMAP | Real                  | Evidencia                                                           |
| --- | --------------------------------------- | ------- | --------------------- | ------------------------------------------------------------------- |
| 1   | Suspensión de tenant                    | ✅       | ✅                     | `EnsureTenantIsActive`, `suspended` status                          |
| 2   | Archivado seguro                        | ✅       | ✅                     | `ArchiveTenantAction` (archived_at, read_only, status='archived')   |
| 3   | Eliminación definitiva con purge        | ✅       | ✅                     | `DeleteTenantAction`, `PurgeTenantJob`                              |
| 4   | Upgrade/downgrade de plan               | ✅       | ⚠️ **PARCIAL**         | `SelectPlan` Livewire, `HostedCheckout` — sin migración de recursos |
| 5   | Re-provisioning idempotente             | ✅       | ✅                     | `ProvisionTenantJob` con 3 retries                                  |
| 6   | Verificación custom domains + SSL       | ✅       | ⚠️ **PARCIAL**         | `RailwayService` para Railway API, SSL no implementado              |
| 7   | SLA RTO/RPO por tenant tier             | ✅       | ❌ **NO IMPLEMENTADO** | No hay documentación SLA                                            |
| 8   | Archivado con garantías de restauración | ✅       | ⚠️ **PARCIAL**         | Soft delete existe, sin restoration flow explícito                  |
| 9   | Tests end-to-end ciclo completo         | ✅       | ✅                     | `TenantLifecycleTest`, `ProvisioningTest`                           |

---

## 🏢 FASE 4 — Tenant Core

### S12 — Tenant Settings y White-label (ROADMAP: 100% ✅ → Real: 67% ⚠️)

| #   | Tarea                                            | ROADMAP | Real                  | Evidencia                                                   |
| --- | ------------------------------------------------ | ------- | --------------------- | ----------------------------------------------------------- |
| 1   | CRUD configuraciones locales (timezone, moneda)  | ✅       | ✅                     | `TenantSetting`, `LocalizationSettings` Livewire            |
| 2   | White-label (logo, colores)                      | ✅       | ✅                     | `BrandingSettings` Livewire, medialibrary                   |
| 3   | Custom domain con verificación                   | ✅       | ✅                     | Route `/settings/branding`                                  |
| 4   | Personalización email templates                  | ✅       | ❌ **NO IMPLEMENTADO** | No hay templates por tenant                                 |
| 5   | Resolución jerárquica (Platform → Plan → Tenant) | ✅       | ⚠️ **PARCIAL**         | `CentralBranding` + `TenantSetting`, sin servicio unificado |
| 6   | CRUD metadata dinámico / reglas de negocio       | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                                   |

### S13 — Tenant Audit (ROADMAP: 100% ✅ → Real: 67% ⚠️)

| #   | Tarea                                   | ROADMAP | Real                  | Evidencia                                       |
| --- | --------------------------------------- | ------- | --------------------- | ----------------------------------------------- |
| 1   | Registro eventos de audit               | ✅       | ✅                     | `RecordAuditLogAction`, `AuditLog` model        |
| 2   | Catálogo eventos auditables             | ✅       | ✅                     | `AuditAction` enum con 12 acciones              |
| 3   | Búsqueda y filtrado                     | ✅       | ✅                     | `AuditLogViewer` Livewire                       |
| 4   | Export audit trail (CSV/PDF)            | ✅       | ✅                     | `AuditDownloadController`, `ExportAuditLogsJob` |
| 5   | Retención configurable por plan         | ✅       | ❌ **NO IMPLEMENTADO** | No hay política de retención                    |
| 6   | Modelo de visibilidad para soporte host | ✅       | ❌ **NO IMPLEMENTADO** | No hay modelo de visibilidad                    |

### S14 — Tenant Notifications (ROADMAP: 100% ✅ → Real: 57% ⚠️)

| #   | Tarea                                     | ROADMAP | Real                  | Evidencia                                                        |
| --- | ----------------------------------------- | ------- | --------------------- | ---------------------------------------------------------------- |
| 1   | Gestión de canales (email, in-app)        | ✅       | ✅                     | In-app (`tenant_notifications`) + email                          |
| 2   | CRUD plantillas tenant-specific           | ✅       | ❌ **NO IMPLEMENTADO** | No hay plantillas por tenant                                     |
| 3   | Notificaciones in-app con bandeja         | ✅       | ✅                     | `Notification` model, `NotificationCenter` Livewire              |
| 4   | Notificaciones email con templates tenant | ✅       | ❌ **NO IMPLEMENTADO** | No implementado                                                  |
| 5   | Preferencias de notificación por usuario  | ✅       | ❌ **NO IMPLEMENTADO** | No existen                                                       |
| 6   | Servicio centralizado de Notifications    | ✅       | ⚠️ **PARCIAL**         | Notificaciones enviadas desde actions, no desde servicio central |
| 7   | Tests de aislamiento                      | ✅       | ✅                     | `TenantInvitationQuotaTest`                                      |

### S15 — Tenant Usage & Quotas (ROADMAP: 33% ⚠️ → Real: 83% ✅)

| #   | Tarea                                           | ROADMAP | Real          | Evidencia                                                              |
| --- | ----------------------------------------------- | ------- | ------------- | ---------------------------------------------------------------------- |
| 1   | Contadores distribuidos en Redis                | ✅       | ✅             | `QuotaManager` usa Cache (Redis)                                       |
| 2   | Tracking métricas de consumo en DB              | ✅       | ✅             | `quota_snapshots` table                                                |
| 3   | Enforcement hard (bloqueo) y soft (advertencia) | ✅       | ✅             | `EnsureWithinQuota` middleware                                         |
| 4   | Alertas de proximidad (80%, 90%, 100%)          | ✅       | ✅             | `QuotaManager::checkThresholds()`, `QuotaThresholdReachedNotification` |
| 5   | Dashboard de uso para admin tenant              | ✅       | ⚠️ **PARCIAL** | `UsageOverview` Livewire existe pero NO está registrada en rutas       |
| 6   | Sincronización periódica Redis → DB             | ✅       | ✅             | `SnapshotQuotasJob` (daily)                                            |

> **Nota:** El ROADMAP reporta S15 al 33% (2/6), pero el código tiene 5/6 funcionalidades implementadas (dashboard existe como componente aunque sin ruta).

---

## 🚀 FASE 5 — Features Avanzados

### S16 — Host Feature Flags (ROADMAP: 100% ✅ → Real: 80% ⚠️)

| #   | Tarea                            | ROADMAP | Real                  | Evidencia                                                   |
| --- | -------------------------------- | ------- | --------------------- | ----------------------------------------------------------- |
| 1   | CRUD feature flags               | ✅       | ✅                     | `Feature` model, `ManageFeature`/`FeatureList`              |
| 2   | Targeting por atributo de tenant | ✅       | ✅                     | `plan_features` pivot, `HasFeatures` trait                  |
| 3   | Override manual por tenant       | ✅       | ✅                     | `TenantFeatureOverride`, `ApplyTenantFeatureOverrideAction` |
| 4   | Consulta cacheada con TTL corto  | ✅       | ✅                     | `ResolveTenantFeaturesAction` en middleware                 |
| 5   | Historial de cambios por feature | ✅       | ❌ **NO IMPLEMENTADO** | No hay history tracking (activity log indirecto no cuenta)  |

### S17 — Tenant Integrations: Webhooks y API Keys (ROADMAP: 100% ✅ → Real: 40% ❌)

| #   | Tarea                           | ROADMAP | Real                  | Evidencia                                            |
| --- | ------------------------------- | ------- | --------------------- | ---------------------------------------------------- |
| 1   | Configuración webhooks outbound | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                            |
| 2   | Delivery webhooks con retry     | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                            |
| 3   | Dead-letter queue de webhooks   | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                            |
| 4   | Log de intentos de delivery     | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                            |
| 5   | CRUD API keys scoped por tenant | ✅       | ✅                     | `ApiKey` model, `ManageApiKeys` Livewire, middleware |

### S18 — Tenant Data Management (ROADMAP: 100% ✅ → Real: 25% ❌)

| #   | Tarea                                   | ROADMAP | Real                  | Evidencia                                                              |
| --- | --------------------------------------- | ------- | --------------------- | ---------------------------------------------------------------------- |
| 1   | Export completo de datos (async)        | ✅       | ✅                     | `DataExport` Livewire, `ExportTenantDataAction`, `ExportTenantDataJob` |
| 2   | Import de datos con validación          | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                                              |
| 3   | Backup on-demand con descarga segura    | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                                              |
| 4   | Políticas de retención por tipo de dato | ✅       | ❌ **NO IMPLEMENTADO** | No existen                                                             |

### S19 — Host Analytics & Reporting (ROADMAP: 100% ✅ → Real: 33% ❌)

| #   | Tarea                                              | ROADMAP | Real                  | Evidencia                                                      |
| --- | -------------------------------------------------- | ------- | --------------------- | -------------------------------------------------------------- |
| 1   | Read model event-driven cross-tenant               | ✅       | ❌ **NO IMPLEMENTADO** | No hay read model dedicado                                     |
| 2   | Dashboards de salud (MRR, churn, active/suspended) | ✅       | ⚠️ **PARCIAL**         | `Dashboard` Livewire central, listas de suscripciones/facturas |
| 3   | Export métricas agregadas (CSV/PDF)                | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                                      |

### S20 — Host Support (ROADMAP: 0% ❌ → Real: 0% ❌)

| #   | Tarea                              | ROADMAP | Real                  | Evidencia                 |
| --- | ---------------------------------- | ------- | --------------------- | ------------------------- |
| 1   | CRUD y ciclo de vida de tickets    | ❌       | ❌ **NO IMPLEMENTADO** | No hay sistema de tickets |
| 2   | Asignación, SLA, escalaciones      | ❌       | ❌ **NO IMPLEMENTADO** | No existe                 |
| 3   | Integración tickets con audit logs | ❌       | ❌ **NO IMPLEMENTADO** | No existe                 |

> **Nota:** El ROADMAP reporta S20 como 0% y es correcto. Sin embargo, existe código de soporte no contemplado en el roadmap: `SupportNote`, `SupportSession`, `BroadcastCenter` Livewire, `GlobalAnnouncements`, `SendBulkBroadcastJob`.

---

## 🔒 FASE 6 — Hardening & Compliance

### S21 — Host Security & Compliance (ROADMAP: 100% ✅ → Real: 33% ❌)

| #   | Tarea                                         | ROADMAP | Real                  | Evidencia                                                                           |
| --- | --------------------------------------------- | ------- | --------------------- | ----------------------------------------------------------------------------------- |
| 1   | Auditoría global accesos y cambios sensibles  | ✅       | ✅                     | `activity_log` (spatie/laravel-activitylog) registra provisioning, billing, support |
| 2   | Políticas de data retention y encryption keys | ✅       | ❌ **NO IMPLEMENTADO** | No existen                                                                          |
| 3   | Rotación automática de secrets y API keys     | ✅       | ❌ **NO IMPLEMENTADO** | No existe                                                                           |

### S22 — Host Monitoring & Alerting (ROADMAP: 100% ✅ → Real: 33% ❌)

| #   | Tarea                                                              | ROADMAP | Real                  | Evidencia                                       |
| --- | ------------------------------------------------------------------ | ------- | --------------------- | ----------------------------------------------- |
| 1   | Health checks centralizados                                        | ✅       | ✅                     | `HealthCheckController` (GET `/central/health`) |
| 2   | Alertas críticas (downtime, billing failures, resource exhaustion) | ✅       | ❌ **NO IMPLEMENTADO** | No hay sistema de alertas                       |
| 3   | Centralized logging aggregator                                     | ✅       | ❌ **NO IMPLEMENTADO** | `activity_log` es parcial, no hay agregador     |

### S23 — Host Landings y Marketing (ROADMAP: 100% ✅ → Real: 75% ⚠️)

| #   | Tarea                                        | ROADMAP | Real                  | Evidencia                               |
| --- | -------------------------------------------- | ------- | --------------------- | --------------------------------------- |
| 1   | Páginas públicas: landing, pricing, contacto | ✅       | ✅                     | `LandingPage` Livewire, landing builder |
| 2   | Captura de leads + CRM + onboarding          | ✅       | ✅                     | `RegisterTenant` multi-step wizard      |
| 3   | Gestión contenidos legales versionados       | ✅       | ❌ **NO IMPLEMENTADO** | No hay términos/privacidad versionados  |

### S24 — Hardening Final y Go-Live (ROADMAP: 0% ❌ → Real: 0% ❌)

| #   | Tarea                              | ROADMAP | Real                  | Evidencia |
| --- | ---------------------------------- | ------- | --------------------- | --------- |
| 1   | Checklist seguridad pre-producción | ❌       | ❌ **NO IMPLEMENTADO** | No existe |
| 2   | Runbook de go-live                 | ❌       | ❌ **NO IMPLEMENTADO** | No existe |

---

## 📋 Tabla Consolidada por Sprint

| Sprint                         | ROADMAP      | Real       | Estado Real                                      |
| ------------------------------ | ------------ | ---------- | ------------------------------------------------ |
| S01 — Infraestructura Base     | 100% (10/10) | 80% (8/10) | ⚠️ Sin CI pipeline                                |
| S02 — Contratos y Eventos      | 100% (9/9)   | 67% (6/9)  | ❌ Sin Outbox, DLQ, publisher base                |
| S03 — Tenancy Core             | 100% (10/10) | 80% (8/10) | ⚠️ Sin chaos tests, ADR-003 parcial               |
| S04 — HTTP y Observabilidad    | 100% (9/9)   | 56% (5/9)  | ❌ Sin correlation ID, OTel, envelope             |
| S05 — Host Auth Super-admin    | 100% (10/10) | 80% (8/10) | ⚠️ Sin SSO/OIDC                                   |
| S06 — Tenant Identity Usuarios | 100% (10/10) | 90% (9/10) | ✅ Sin impersonación tenant-admin                 |
| S07 — Tenant Identity SSO      | 100% (10/10) | 40% (4/10) | ❌ Sin SAML, OIDC, SCIM, refresh tokens           |
| S08 — Host Billing Planes      | 100% (10/10) | 70% (7/10) | ⚠️ Sin prorrateo, cupones, MRR                    |
| S09 — Host Payments            | 100% (9/9)   | 89% (8/9)  | ⚠️ Sin reembolsos manuales                        |
| S10 — ProvisioningJob          | 100% (10/10) | 70% (7/10) | ⚠️ Sin SSL, chaos tests, resume                   |
| S11 — Offboarding/Recovery     | 100% (9/9)   | 67% (6/9)  | ⚠️ Sin SLA docs, restoration flow                 |
| S12 — Settings/White-label     | 100% (6/6)   | 67% (4/6)  | ⚠️ Sin email templates, metadata rules            |
| S13 — Tenant Audit             | 100% (6/6)   | 67% (4/6)  | ⚠️ Sin retención, visibilidad soporte             |
| S14 — Tenant Notifications     | 100% (7/7)   | 57% (4/7)  | ⚠️ Sin plantillas, preferencias, servicio central |
| S15 — Usage & Quotas           | 33% (2/6)    | 83% (5/6)  | ✅ Dashboard existe sin ruta                      |
| S16 — Host Feature Flags       | 100% (5/5)   | 80% (4/5)  | ⚠️ Sin historial de cambios                       |
| S17 — Webhooks/API Keys        | 100% (5/5)   | 40% (2/5)  | ❌ Sin webhooks outbound                          |
| S18 — Data Management          | 100% (4/4)   | 25% (1/4)  | ❌ Solo export                                    |
| S19 — Analytics/Reporting      | 100% (3/3)   | 33% (1/3)  | ❌ Sin read model ni export                       |
| S20 — Host Support             | 0% (0/3)     | 0% (0/3)   | ❌ Sin tickets (hay broadcast/notes no roadmap)   |
| S21 — Security/Compliance      | 100% (3/3)   | 33% (1/3)  | ❌ Sin retention ni rotation                      |
| S22 — Monitoring/Alerting      | 100% (3/3)   | 33% (1/3)  | ❌ Sin alertas ni log aggregator                  |
| S23 — Landings/Marketing       | 100% (3/3)   | 67% (2/3)  | ⚠️ Sin contenidos legales                         |
| S24 — Hardening Go-Live        | 0% (0/2)     | 0% (0/2)   | ❌ No iniciado                                    |

---

## 🎯 Principales Hallazgos

### Funcionalidades reportadas como 100% que NO están implementadas

1. **Outbox Pattern** (S02) — No existe tabla `outbox_events` ni worker
2. **Dead Letter Queue** (S02) — No implementada
3. **Publisher/Subscriber base** (S02) — No existe abstracción
4. **Correlation ID Middleware** (S04) — No implementado
5. **OpenTelemetry / W3C TraceContext** (S04) — No implementado
6. **Formateador unificado de respuestas** (S04) — No implementado
7. **SSO/OIDC super-admin** (S05) — No implementado
8. **SSO/SAML 2.0 tenant** (S07) — No implementado
9. **OIDC tenant** (S07) — No implementado
10. **SCIM provisioning** (S07) — No implementado
11. **Refresh token rotation** (S07) — No implementado
12. **Cálculo de prorrateo** (S08) — No implementado
13. **Cupones/descuentos** (S08) — No implementado
14. **Reportes MRR y churn** (S08) — No implementado
15. **SSL auto-provisioning** (S10) — No implementado
16. **Chaos tests provisioning** (S10) — No implementados
17. **Documentación SLA RTO/RPO** (S11) — No implementada
18. **Email templates tenant-specific** (S12, S14) — No implementados
19. **Políticas de retención audit** (S13) — No implementadas
20. **Modelo de visibilidad soporte** (S13) — No implementado
21. **Preferencias de notificación por usuario** (S14) — No implementadas
22. **Webhooks outbound** (S17) — No implementados
23. **Data import** (S18) — No implementado
24. **Backup on-demand** (S18) — No implementado
25. **Read model cross-tenant** (S19) — No implementado
26. **Export métricas agregadas** (S19) — No implementado
27. **Sistema de tickets** (S20) — No implementado
28. **Rotación automática de secrets** (S21) — No implementada
29. **Alertas críticas** (S22) — No implementadas
30. **Centralized logging aggregator** (S22) — No implementado
31. **Contenidos legales versionados** (S23) — No implementados
32. **Checklist seguridad pre-producción** (S24) — No implementado
33. **Runbook go-live** (S24) — No implementado

### Funcionalidades implementadas NO contempladas en el ROADMAP

- **Landing Builder para tenants** (13 tipos de bloques: hero, features, pricing, testimonios, FAQ, galería, contacto)
- **Integración Clave/PagueloFacil** (Uruguay) — El roadmap solo prioriza dLocal
- **Sistema de Broadcast/Announcements** (`BroadcastCenter`, `GlobalAnnouncements`)
- **Support Notes y Sessions** (notas de soporte, aunque sin tickets)
- **Passkeys** (autenticación sin contraseña)
- **Tenant SMTP Settings** (correo propio por tenant)
- **MFA enforcement por tenant** (`EnforceTenantMfa` middleware)

### Discrepancias de reporte en el ROADMAP

- S15 se reporta al 33% pero el código tiene 5/6 items funcionando (83%)
- S20 se reporta al 0% y es correcto, pero existe funcionalidad de soporte no contemplada
- S24 se reporta al 0% y es correcto
- El resto de fases (S01-S14, S16-S19, S21-S23) están significativamente **sobre-reportadas**
