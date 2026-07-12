# Auditoría ROADMAP vs Estado del Código

**Fecha:** Julio 2026
**Objetivo:** Verificar que cada tarea marcada como `[x]` (completada) en `ROADMAP.md` existe realmente en la base de código.
**Metodología:** Para cada tarea listada como completada se verificó la existencia del archivo de código correspondiente (clase, interfaz, migración, test, configuración, etc.).

---

## Resumen Ejecutivo

| Sprint    | Tareas totales | Existentes | Faltantes | % Completado real |
| --------- | -------------- | ---------- | --------- | ----------------- |
| Fase 0    | 16             | 14         | 2         | 88 %              |
| Fase 1    | 15             | 11         | 4         | 73 %              |
| Fase 2    | 15             | 10         | 5         | 67 %              |
| Fase 3    | 44             | 34         | 10        | 77 %              |
| Fase 4    | 16             | 4          | 12        | 25 %              |
| Fase 5    | 26             | 0          | 26        | 0 %               |
| Fase 6    | 25             | 18         | 7         | 72 %              |
| Fase 7    | 9              | 1          | 8         | 11 %              |
| Fase 8    | 48             | 12         | 36        | 25 %              |
| **Total** | **214**        | **104**    | **110**   | **49 %**          |

**El ROADMAP muestra un avance del 100% (todo `[x]`), pero solo el ~49% del código correspondiente existe realmente.**

---

## 🔴 FASE 1 — Núcleo de Multi-Tenancy (4 faltantes)

### Sprint 1.3 — Tenant Context en Jobs

| Tarea                           | Debería existir                                                        | Realidad      |
| ------------------------------- | ---------------------------------------------------------------------- | ------------- |
| `MissingTenantContextException` | `Platform/Tenancy/Domain/Exceptions/MissingTenantContextException.php` | ❌ No existe  |
| Tests: 5 tests                  | `tests/Feature/TenantContextJob*`                                      | ❌ No existen |

### Sprint 1.4 — Harness de testing de aislamiento

| Tarea                                       | Debería existir                            | Realidad     |
| ------------------------------------------- | ------------------------------------------ | ------------ |
| Trait `CrossTenantLeakTest`                 | `Platform/Support/CrossTenantLeakTest.php` | ❌ No existe |
| CI step verifica `*CrossTenantLeakTest.php` | `.github/workflows/*.yml`                  | ❌ No existe |

---

## 🔴 FASE 2 — Identidad (5 faltantes)

### Sprint 2.1 — Authorization (Platform)

| Tarea                       | Debería existir                      | Realidad      |
| --------------------------- | ------------------------------------ | ------------- |
| `BasePolicy` abstract class | `Platform/*/BasePolicy.php`          | ❌ No existe  |
| `AssignRoleAction`          | `Platform/*/AssignRoleAction.php`    | ❌ No existe  |
| `RevokeRoleAction`          | `Platform/*/RevokeRoleAction.php`    | ❌ No existe  |
| `HasPermissionAction`       | `Platform/*/HasPermissionAction.php` | ❌ No existe  |
| Tests: 5 tests              | `tests/Feature/*Role*`               | ❌ No existen |

### Nota

Spatie Permission está instalado y el modelo `User` usa `HasRoles`. Las funcionalidades existen pero **no están envueltas en Actions/Contracts** según describe el roadmap. El uso actual es directo vía Spatie.

---

## 🔴 FASE 3 — Módulos Central (10 faltantes)

### Sprint 3.1 — Tenants (ciclo de vida)

| Tarea                                                                       | Debería existir                  | Realidad                                                                  |
| --------------------------------------------------------------------------- | -------------------------------- | ------------------------------------------------------------------------- |
| `TenantCreated`, `TenantSuspended`, `TenantResumed`, `TenantDeleted` events | `Central/*/Events/*`             | ❌ No existen como clases de evento independientes                        |
| `LogTenantLifecycleEvent` listener                                          | `Central/*/Listeners/`           | ❌ No existe                                                              |
| Tests: 9 tests                                                              | `tests/Feature/TenantLifecycle*` | ❌ No existen (solo `TenantLifecycleTest` con maintenance/archive/delete) |

### Sprint 3.2 — Plans (catálogo)

| Tarea                                          | Debería existir                  | Realidad                                              |
| ---------------------------------------------- | -------------------------------- | ----------------------------------------------------- |
| `PlanStatus` enum                              | `Central/*/Enums/PlanStatus.php` | ❌ No existe                                          |
| `GetActivePlansWithFeaturesAction`             | `Central/*/Actions/`             | ❌ No existe                                          |
| `PlanWithFeaturesData`, `PlanFeatureData` DTOs | `Central/*/DTO/`                 | ❌ No existen                                         |
| Tests: 10 tests                                | —                                | ❌ No existen (solo `BillingTest` con planes básicos) |

### Sprint 3.4 — Provisioning

| Tarea                                                     | Debería existir                     | Realidad      |
| --------------------------------------------------------- | ----------------------------------- | ------------- |
| `SetupTenantDatabaseJob`, `ConfigureDomainDnsJob`         | `Central/*/Jobs/`                   | ❌ No existen |
| `DnsGatewayContract`, `CloudflareDnsGateway`              | `Platform/Contracts/`, `Central/*/` | ❌ No existen |
| `LogProvisioningStepAction`, `ListProvisioningJobsAction` | `Central/*/Actions/`                | ❌ No existen |
| Tests: 16 tests                                           | —                                   | ❌ No existen |

### Sprint 3.5 — Billing

| Tarea                                                | Debería existir                            | Realidad                                                                 |
| ---------------------------------------------------- | ------------------------------------------ | ------------------------------------------------------------------------ |
| `PaymentGatewayContract` en `Platform/Integrations/` | `Platform/Integrations/`                   | ❌ No existe (no hay `Platform/Integrations/`)                           |
| `DlocalPaymentGateway`, `PaymentGatewayException`    | `Central/Billing/Infrastructure/Gateways/` | ❌ Existe `DlocalGateway` y `ClaveGatewayException` — nombres diferentes |
| `SubscriptionPolicy`                                 | `Central/*/Policies/`                      | ❌ No existe                                                             |

---

## 🔴 FASE 4 — Scaffolding Tenant (12 faltantes)

### Sprint 4.1 — Teams

| Tarea                                                | Debería existir            | Realidad      |
| ---------------------------------------------------- | -------------------------- | ------------- |
| `Team` model                                         | `Tenant/*/Models/Team.php` | ❌ No existe  |
| `CreateTeamAction`                                   | `Tenant/*/Actions/`        | ❌ No existe  |
| `InviteUserToTeamAction`, `RemoveUserFromTeamAction` | `Tenant/*/Actions/`        | ❌ No existen |
| Tests: 6 tests                                       | —                          | ❌ No existen |

### Sprint 4.2 — Branding

| Tarea                     | Debería existir                      | Realidad                                                     |
| ------------------------- | ------------------------------------ | ------------------------------------------------------------ |
| `BrandingConfig` model    | `Tenant/*/Models/BrandingConfig.php` | ❌ No existe (existe `TenantSetting` genérico en Experience) |
| `UpdateBrandingAction`    | `Tenant/*/Actions/`                  | ❌ Nombre diferente: `UpdateTenantBranding`                  |
| `GetBrandingConfigAction` | `Tenant/*/Actions/`                  | ❌ No existe                                                 |
| Tests: 5 tests            | —                                    | ❌ No existen                                                |

### Sprint 4.3 — API Keys

| Tarea                              | Debería existir        | Realidad                                          |
| ---------------------------------- | ---------------------- | ------------------------------------------------- |
| `AuthenticateViaApiKey` middleware | `Tenant/*/Middleware/` | ❌ Existe `AuthenticateApiKey` — nombre diferente |
| Tests: 8 tests                     | —                      | ❌ Existe `TenantApiKeyTest` con 2 tests          |

---

## 🔴 FASE 5 — Infraestructura Platform (26 faltantes — Sprint completo ausente)

### Sprint 5.1 — Notifications

| Tarea                          | Debería existir |
| ------------------------------ | --------------- |
| `NotificationChannelContract`  | ❌              |
| `MailChannel`                  | ❌              |
| `InAppChannel`                 | ❌              |
| `SendNotificationAction`       | ❌              |
| `NotificationsServiceProvider` | ❌              |
| Tests: 5                       | ❌              |

### Sprint 5.2 — Audit

| Tarea                                        | Debería existir         | Realidad                                             |
| -------------------------------------------- | ----------------------- | ---------------------------------------------------- |
| `LogDomainEvent` listener genérico           | `Platform/*/Listeners/` | ❌ No existe                                         |
| `AuditServiceProvider` mapea eventos Central | —                       | ❌ Solo existe `ComplianceServiceProvider` en Tenant |

### Sprint 5.3 — Settings

| Tarea                                    | Debería existir |
| ---------------------------------------- | --------------- |
| `Setting` model con `tenant_id nullable` | ❌              |
| `SetSettingAction`                       | ❌              |
| `GetSettingAction`                       | ❌              |
| Tests: 7                                 | ❌              |

### Sprint 5.4 — Media

| Tarea                | Debería existir               |
| -------------------- | ----------------------------- |
| `Media` model custom | ❌ (solo existe el de spatie) |
| `UploadMediaAction`  | ❌                            |
| `DeleteMediaAction`  | ❌                            |
| Tests: 5             | ❌                            |

### Sprint 5.5 — Search

| Tarea                    | Debería existir |
| ------------------------ | --------------- |
| `SearchEngineContract`   | ❌              |
| `PostgresSearchEngine`   | ❌              |
| Migración `search_index` | ❌              |
| `SearchServiceProvider`  | ❌              |
| Tests: 6                 | ❌              |

---

## 🔴 FASE 7 — Hardening (8 faltantes)

### Sprint 7.1 — Octane

| Tarea                                | Debería existir         | Realidad      |
| ------------------------------------ | ----------------------- | ------------- |
| `config/octane.php`                  | `config/octane.php`     | ❌ No existe  |
| Scripts `octane:dev` y `octane:prod` | `composer.json` scripts | ❌ No existen |
| `OctaneCrossTenantLeakTest`          | `tests/`                | ❌ No existe  |

### Sprint 7.2 — Auditoría de seguridad

| Tarea                                  | Debería existir        | Realidad      |
| -------------------------------------- | ---------------------- | ------------- |
| Migración `add_tenant_rls_policies_v2` | `database/migrations/` | ❌ No existe  |
| Pentest isolation: 8 tests             | —                      | ❌ No existen |

---

## 🔴 FASE 8 — Page Builder (36 faltantes)

### Sprint 8.1 — Arquitectura de datos

| Tarea                                 | Realidad                                 |
| ------------------------------------- | ---------------------------------------- |
| Modelos `TenantPage`, `PageComponent` | ❌ Existen `Landing` y `LandingVersion`  |
| `RenderTenantPageAction`              | ❌ Existe `RenderLanding`                |
| Controlador `TenantLandingController` | ❌ Existe `ServeTenantLandingController` |
| Vista `tenant.page.index`             | ❌ Existe routing diferente              |
| Tests: 9                              | ❌ Existe `LandingRenderingTest`         |

### Sprint 8.3 — Builder UI

| Tarea                           | Realidad                   |
| ------------------------------- | -------------------------- |
| PageBuilder Livewire 3-columnas | ❌ Existe `LandingBuilder` |
| Tests: 17                       | ❌ No existen              |

### Sprint 8.4 — Estilos globales

| Tarea    | Realidad      |
| -------- | ------------- |
| Tests: 5 | ❌ No existen |

### Sprint 8.5 — Roles y permisos

| Tarea              | Realidad                        |
| ------------------ | ------------------------------- |
| `TenantPagePolicy` | ❌ Existe `TenantSettingPolicy` |
| Tests: 4           | ❌ No existen                   |

---

## 📋 Hallazgos por Categoría

### 🚨 Módulos Platform completos ausentes (Fase 5)

| Módulo            | Impacto                                                                                                             |
| ----------------- | ------------------------------------------------------------------------------------------------------------------- |
| **Notifications** | No hay canal de notificaciones multicanal desacoplado. Las notificaciones se envían directamente desde cada módulo. |
| **Settings**      | No hay mecanismo unificado de key-value settings con resolución tenant override > global > default.                 |
| **Media**         | spatie/laravel-medialibrary está instalado pero no hay una capa Actions/Contratos.                                  |
| **Search**        | No existe ningún mecanismo de búsqueda.                                                                             |

### 🚨 Authorization (Platform) ausente

Spatie Permission se usa directamente. No hay `BasePolicy`, `AssignRoleAction`, `RevokeRoleAction` ni `HasPermissionAction` como describe el roadmap.

### 🚨 Octane no configurado

No existe `config/octane.php`. La arquitectura se diseñó asumiendo compatibilidad (scoped bindings, sin estado estático) pero no se puede poner Octane en producción sin configuración.

### ⚠️ CrossTenantLeakTest no implementado

ARCHITECTURE_RULES.md lo exige como parte del Definition of Done. El ROADMAP lo da por implementado (trait + CI check). No existe ni el trait ni la verificación en CI.

### ⚠️ MissingTenantContextException no existe

El contrato `TenantAware` y `RehydrateTenantContext` existen (recién implementados), pero no hay una excepción que se lance cuando un Job tenant-aware no implementa `TenantAware`.

---

## Recomendaciones

| Prioridad | Acción                                                                                                                               | Sprint |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------ | ------ |
| 🔴 1      | Implementar `CrossTenantLeakTest` trait en Platform Support                                                                          | 1.4    |
| 🔴 2      | Crear `MissingTenantContextException` y su validación                                                                                | 1.3    |
| 🔴 3      | Decidir: construir módulos Platform (Notifications, Settings, Media, Search) o actualizar ROADMAP para reflejar que NO son prioridad | 5.x    |
| 🔴 4      | Decidir: construir Authorization Actions o marcar como no prioritaria                                                                | 2.1    |
| 🟡 5      | Configurar Octane (`config/octane.php`, scripts)                                                                                     | 7.1    |
| 🟡 6      | Corregir ROADMAP para reflejar el estado real del código                                                                             | —      |

---

_Documento generado por auditoría automatizada. 214 tareas evaluadas contra el código base._
