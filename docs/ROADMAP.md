# ROADMAP — LaraShift

> Estructura: **Fase → Sprint → Tarea** Cada tarea es una unidad medible y verificable. Ningún sprint se marca como completo si alguna de sus tareas no cumple el checklist de `ARCHITECTURE_RULES.md` (Actions, DTOs, tests, `CrossTenantLeakTest` cuando aplique).
>
> Orden de fases definido por dependencia real, no por prioridad de negocio: no se puede construir Identity sin Authorization (Platform), no se puede construir Provisioning sin Tenants (Central), y ningún módulo tenant-aware se construye antes de tener el mecanismo RLS + TenantContext funcionando y probado.

---

# FASE 0 — Fundación del proyecto

Objetivo: tener un esqueleto Laravel funcional, con convenciones, estándares y pipeline de calidad, antes de escribir el primer módulo de negocio.

## Sprint 0.1 — Setup base

- [x] Inicializar proyecto Laravel 13 con estructura de Modular Monolith (carpeta `app/Modules/` por Bounded Context, PSR-4 `Modules\\`)
- [x] Configurar PostgreSQL como único motor de base de datos soportado (default `pgsql`, sqlite como fallback local)
- [x] Configurar Redis para cache, colas, locks y sesiones (default `redis`, driver `predis`)
- [x] Configurar Laravel Horizon para monitoreo de colas (v5.47, Redis connection)
- [x] Configurar Laravel Octane (modo desarrollo, RoadRunner) para detectar temprano fugas de estado estático
- [x] Definir estructura estándar de carpetas por módulo (`Actions/`, `DTOs/`, `Models/`, `Policies/`, `Queries/`, `Jobs/`, `Http/`, `Tests/`, etc.)

## Sprint 0.2 — Calidad y estándares

- [x] Configurar Laravel Pint con reglas de estilo del proyecto (v1.29, preset laravel, paths incluye `app/Modules/`)
- [x] Configurar Rector para refactors automáticos y detección de código muerto (v2.5, `driftingly/rector-laravel`, 11 files actualizados)
- [x] Configurar Pest como framework de testing oficial (v4.7, preinstalado por Starter Kit)
- [x] Configurar pipeline CI (lint, static analysis, tests) en cada PR (workflows actualizados con Pint + Rector + Larastan + tests)
- [x] Documentar convención de nombres de Actions, DTOs y Events en `CONTRIBUTING.md`
- [x] Configurar análisis estático (Larastan/PHPStan) a nivel 7, 0 errores

## Sprint 0.3 — Esqueleto de Bounded Contexts

- [ ] Crear los tres Bounded Contexts vacíos: `Central/`, `Tenant/`, `Platform/`, con un módulo dummy de ejemplo en cada uno
- [ ] Configurar autoload de módulos (PSR-4 por módulo, service providers por módulo)
- [ ] Documentar en `ARCHITECTURE_RULES.md` cualquier ajuste real detectado al implementar la estructura (feedback loop)
- [ ] Crear plantilla (`stub`) de módulo nuevo generable vía comando Artisan propio (`make:module`)

---

# FASE 1 — Núcleo de Multi-Tenancy

Objetivo: el mecanismo de aislamiento de tenants (RLS + TenantContext + contrato para Jobs) debe existir, estar probado, y ser a prueba de fugas **antes** de que cualquier módulo de negocio persista un solo dato tenant-aware.

## Sprint 1.1 — Tenant Resolver

- [x] Instalar stancl/tenancy v3.10 con Single Database strategy (UUID, `data` JSON, `domains` table)
- [x] Implementar tabla `tenants` con columnas mínimas (id UUID, `data` JSON, timestamps)
- [x] Implementar `TenantResolver`: resolución por subdominio (via stancl `domains` table + middleware)
- [x] Implementar `TenantResolver`: resolución por dominio personalizado (via stancl `domains` table + middleware)
- [x] Cachear resolución de dominio en Redis (stancl `InvalidatesResolverCache` trait + Redis config)
- [x] Tests: 5 tests (creación con subdominio, con dominio custom, duplicados, atributos data, cache)

## Sprint 1.2 — Tenant Context y RLS

- [x] Implementar clase `TenantContext` con binding `scoped()` (nunca `singleton()`) en `Platform/Support/`
- [x] Implementar Middleware `ApplyTenantContext`: resuelve tenant via stancl `tenancy()->tenant`, setea `TenantContext`, ejecuta `SET LOCAL app.tenant_id` dentro de transacción explícita
- [x] Crear policies de RLS en PostgreSQL para las tablas tenant-aware (migración base reutilizable con `current_setting('app.tenant_id')`)
- [x] Registrar binding via `PlatformServiceProvider` + middleware en `bootstrap/app.php` (grupo web)
- [x] Tests: 7 tests (context get/set/clear, scoped binding, SET LOCAL en transacción, reseteo post-commit, aislamiento entre tenants) — 3 skip en SQLite

## Sprint 1.3 — Tenant Context en Jobs

- [x] Definir e implementar interfaz `TenantAware` (`tenantId(): string`) en `Platform/Contracts/`
- [x] Implementar Job Middleware `RehydrateTenantContext` (aplica `SET LOCAL` + hidrata `TenantContext` dentro de transacción antes de `handle()`)
- [x] Implementar excepción `MissingTenantContextException` cuando un Job accede a datos tenant-aware sin implementar `TenantAware`
- [x] Test: 5 tests (job con contexto, excepción sin interfaz, SET LOCAL en transacción, reseteo post-commit, cross-tenant leak) — 4 skip en SQLite
- [x] Documentar el contrato `TenantAware` en `CONTRIBUTING.md` con ejemplo de uso y reglas obligatorias

## Sprint 1.4 — Harness de testing de aislamiento

- [x] Crear trait reutilizable `CrossTenantLeakTest` en `Platform/Support/` (setUpCrossTenantLeakTest, assertTenantBSeesNoDataFromA, assertNoLingeringTenantContext, simulateTenantContext)
- [x] Documentar en `ARCHITECTURE_RULES.md` el patrón exacto que todo `CrossTenantLeakTest` debe seguir (con ejemplo de código)
- [x] Añadir verificación automática en CI: step que itera módulos bajo `Tenant/` y verifica existencia de `*CrossTenantLeakTest.php`
- [x] Sprint de cierre de fase: revisión cruzada del mecanismo completo completada — RLS + TenantContext (scoped) + ApplyTenantContext (middleware) + RehydrateTenantContext (Jobs) + MissingTenantContextException + CrossTenantLeakTest harness

## ✅ FASE 1 COMPLETA

---

# FASE 2 — Identidad (Central y Tenant)

Objetivo: dos sistemas de autenticación completamente separados, sin acoplamiento entre sí, y el módulo `Authorization` compartido del que ambos dependen.

## Sprint 2.1 — Authorization (Platform)

- [x] Instalar `spatie/laravel-permission` v8 con Octane reset listener habilitado
- [x] Definir contratos base: `BasePolicy` abstracta con métodos CRUD + prefijo de recurso + `admin` bypass
- [x] Implementar `AssignRoleAction`, `RevokeRoleAction`, `HasPermissionAction` (agnósticas del modelo — funcionan con cualquier model que use `HasRoles` trait)
- [x] Agregar `HasRoles` trait al modelo `User`
- [x] Tests: 5 tests (asignar rol + permisos, verificar permisos, revocar rol, HasPermissionAction, aislamiento entre usuarios)

## Sprint 2.2 — Identity (Central)

- [x] Modelo `CentralUser` + migración `Central_users` (sin `tenant_id`, tabla separada de platform)
- [x] Configurar guard `Central` + provider `Central_users` + password broker en `config/auth.php`
- [x] Implementar Actions: `RegisterCentralUserAction` (crea, hashea), `AuthenticateCentralUserAction` (guard `Central`), `LogoutCentralUserAction` (invalida sesión)
- [x] Implementar recuperación de contraseña para staff/Central via `SendCentralPasswordResetLinkAction` (broker `Central_users`)
- [x] Tests (7): register, authenticate válido, rechaza credenciales inválidas, logout, reset link, no referencia a TenantUser, guard config

## Sprint 2.3 — Users (Tenant)

- [x] Modelo `TenantUser` + migración `tenant_users` (con `tenant_id` FK → `tenants`, sujeto a RLS)
- [x] Configurar guard `tenant` + provider `tenant_users` + password broker en `config/auth.php`
- [x] Implementar Actions: `RegisterTenantUserAction`, `AuthenticateTenantUserAction`, `LogoutTenantUserAction`
- [x] Implementar recuperación de contraseña vía `SendTenantPasswordResetLinkAction` (broker `tenant_users`)
- [x] `TenantUserFactory` + `TenantFactory` (para tests)
- [x] `CrossTenantLeakTest` para Tenant/Users con escenario de fuga entre tenants
- [x] Tests (11): register, login válido, rechazo inválido, logout, reset link, CrossTenantLeak (3), no referencia a CentralUser

---

# FASE 3 — Módulos Central (plataforma)

Objetivo: infraestructura de administración de la plataforma SaaS. Todo módulo aquí es Central, y la regla de no-acceso-directo-a-modelos-de-otro-módulo aplica igual que entre Tenant.

## Sprint 3.1 — Tenants (ciclo de vida)

- [x] Actions: `CreateTenantAction` (ya existía, ahora emite `TenantCreated`), `SuspendTenantAction`, `ResumeTenantAction`, `DeleteTenantAction` (con validación de transiciones + invalidación de cache de dominio)
- [x] Domain Events: `TenantCreated`, `TenantSuspended`, `TenantResumed`, `TenantDeleted`
- [x] Listener: `LogTenantLifecycleEvent` stub preparado para `Platform/Audit` (implementación diferida a Fase 5)
- [x] Tests: 9 tests (creación, suspender activo, error si no activo, reanudar suspendido, error si no suspendido, eliminar, 3 eventos de dominio, transición inválida)

## Sprint 3.2 — Plans (catálogo)

- [x] Modelo `Plan` + migración `plans` con límites JSON configurables (max_users, max_storage_mb, etc.)
- [x] `PlanStatus` enum (active, archived)
- [x] Actions: `CreatePlanAction` (emite `PlanCreated`), `UpdatePlanAction` (rechaza archivados), `ArchivePlanAction` (emite `PlanArchived`, rechaza doble archive), `GetActivePlansWithFeaturesAction` (retorna planes activos + features para pricing público)
- [x] `PlanFactory` para tests
- [x] DTOs de salida: `PlanWithFeaturesData`, `PlanFeatureData` (readonly classes)
- [x] Tests (10): crear con límites, actualizar, archivar, error al actualizar archivado, error doble archive, valores default, unicidad de nombre, active plans + features, excluye archivados, orden por precio

## Sprint 3.3 — Features (feature flags)

- [x] Modelo `Feature` + migraciones (`features`, `feature_plan`, `feature_tenant`)
- [x] `FeatureTenant` pivot model con `enabled` booleano (override manual)
- [x] Action pública `IsFeatureEnabledForTenantAction` (precedencia: override tenant → plan → false)
- [x] `AssignFeatureToPlanAction`, `SetTenantFeatureOverrideAction`
- [x] Comunicación con `Plans` vía `belongsToMany` (relación en modelo Plan, no acceso directo a tablas)
- [x] Tests (7): feature desconocida, no asignada, asignada por plan, override enable, override disable, aislamiento entre tenants, precedencia override wins

## Sprint 3.4 — Provisioning

- [x] Diseñar flujo de Jobs reanudables: `ProvisionTenantPipelineAction` orquesta pipeline (SetupTenantDatabaseJob → ConfigureDomainDnsJob)
- [x] Implementar Jobs con contrato `TenantAware` + middleware `RehydrateTenantContext` + retry config (3 y 5 intentos)
- [x] Integración con `Platform/Integrations` via `DnsGatewayContract` + `CloudflareDnsGateway` stub
- [x] `HandleTenantCreatedProvisioning` listener que dispara pipeline al crear tenant
- [x] Migración `provisioning_logs` para persistir estado de cada paso del pipeline
- [x] Actions: `LogProvisioningStepAction` (registra cada paso), `ListProvisioningJobsAction` (consulta paginada)
- [x] Jobs actualizados: `SetupTenantDatabaseJob` + `ConfigureDomainDnsJob` loguean running/completed/failed vía Action
- [x] Livewire `ProvisioningLogs`: tabla con status badges + timestamps + mensajes de error
- [x] Ruta `/Central/provisioning` (middleware `auth:Central`)
- [x] `ProvisioningViewServiceProvider` registrado en `bootstrap/providers.php`
- [x] Tests (16): dispatch setup job, TenantAware contracts (2), unique jobs por tenant, retry config, ejecución limpia, log pending/running/completed/failed, list ordenado, tenant name, empty state, error display

## Sprint 3.5 — Billing (incluye Payments)

- [x] Modelos: `Subscription` (con `tenant_id`, FK→plans), `Invoice` (FK→subscriptions), `PaymentMethod` (con `tenant_id`, details encrypted)
- [x] Enums: `SubscriptionStatus`, `InvoiceStatus`
- [x] Actions: `CreateSubscriptionAction`, `CancelSubscriptionAction` (rechaza doble cancel), `GenerateInvoiceAction`
- [x] Domain Events: `SubscriptionCreated`, `SubscriptionCancelled`, `InvoiceGenerated`
- [x] Integration Events: `PaymentWebhookReceived` (en carpeta `IntegrationEvents/`, separado de Domain Events)
- [x] Gateway contract: `PaymentGatewayContract` con DTOs en `Platform/Integrations/` + `DlocalPaymentGateway` stub + `PaymentGatewayException`
- [x] Binding: `BillingServiceProvider` bindea `PaymentGatewayContract::class → DlocalPaymentGateway::class`
- [x] Policy: `SubscriptionPolicy` con verificación de tenant context
- [x] Tests (6): create, cancel, double-cancel reject, generate invoice, gateway resolution, gateway charge

## Sprint 3.6 — Monitoring

- [x] `GetTenantUsageMetricsAction`: consume `TenantUser::count()` + plan `limits['max_users']` vía suscripción activa
- [x] `GetSystemHealthMetricsAction`: total tenants, suscripciones activas, total tenant_users
- [x] DTOs: `UsageMetricsData` (tenantId, planName, userCount, maxUsers), `SystemHealthData` (totalTenants, activeSubscriptions, totalUsers)
- [x] Dashboard UI reenviado a Sprint 6 (Paneles UI)
- [x] Tests (5): usage vacío, con usuarios, límites vs plan, health metrics, sin suscripción

---

# FASE 4 — Scaffolding Tenant

Objetivo: completar el scaffolding genérico del lado Tenant. Ningún módulo de esta fase contiene lógica de dominio vertical.

## Sprint 4.1 — Teams

- [x] Modelo `Team` (tenant_id + name) + pivot `team_user` (many-to-many con `TenantUser`)
- [x] Actions: `CreateTeamAction`, `InviteUserToTeamAction` (syncWithoutDetaching), `RemoveUserFromTeamAction` (detach)
- [x] `CrossTenantLeakTest` (2 tests): equipos de tenant A no visibles desde tenant B + reseteo de contexto
- [x] Tests (6): create, invite, remove, multiple members + CrossTenantLeak (2)

## Sprint 4.2 — Branding

- [x] Modelo `BrandingConfig` + migración `branding_configs` (tenant_id único, logo_url, primary_color, secondary_color, custom_domain, domain_verified_at)
- [x] `UpdateBrandingAction` (updateOrCreate) + `GetBrandingConfigAction` (fallback a default vacío)
- [x] Integración con `Tenants` (Central) vía `CustomDomainUpdated` event → `SyncCustomDomain` listener (agrega dominio a `domains` table)
- [x] Integración con `Settings` (Platform) diferida hasta Sprint 5.3 (Settings module)
- [x] `BrandingServiceProvider` registra listener cross-módulo
- [x] Tests (5): crear branding, upsert, fallback default, logo url, custom domain sync

## Sprint 4.3 — API Keys

- [x] Modelo `ApiKey` + migración `api_keys` (tenant_id, name, key_hash (sha256), scopes JSON, expires_at, last_used_at)
- [x] `GenerateApiKeyAction` (retorna raw key + modelo; hash almacenado, raw solo una vez)
- [x] `RevokeApiKeyAction` (soft-delete vía delete)
- [x] `AuthenticateViaApiKey` middleware (bearer token → hash → resuelve tenant → setea TenantContext + SET LOCAL)
- [x] `CrossTenantLeakTest` (2 tests): API keys de tenant A no visibles desde tenant B + reseteo de contexto
- [x] Tests (8): generar key, revocar, middleware válido, middleware inválido, expirada, expiración futura + CrossTenantLeak (2)

---

# FASE 5 — Infraestructura Platform complementaria

Objetivo: completar los módulos Platform que no eran bloqueantes para las fases anteriores.

## Sprint 5.1 — Notifications

- [x] Contrato `NotificationChannelContract` (send + name) — Platform/Notifications/Contracts
- [x] `MailChannel`: envía vía Laravel Mail (compatible con Resend via config)
- [x] `InAppChannel`: stub (implementación real diferida a Sprint 6 — UI)
- [x] `SendNotificationAction`: dispatcher multicanal con fallback (primer canal exitoso gana)
- [x] `NotificationsServiceProvider` taggea canales + bindea SendNotificationAction
- [x] Tests (5): mail channel, in-app sin destinatario, envío simple, fallback entre canales, stop en primer éxito

## Sprint 5.2 — Audit

- [x] Instalar `spatie/laravel-activitylog` v5 + migración `activity_log` (event, causer, subject, properties, timestamps)
- [x] `LogDomainEvent` listener genérico que extrae propiedades vía reflection y logea al audit trail
- [x] `AuditServiceProvider` (EventServiceProvider) mapea eventos de Central (Tenant, Plan, Subscription) al listener
- [x] Tests (4): log tenant creation, log status transitions, event properties stored, + skip immutability (requiere custom Activity model)

## Sprint 5.3 — Settings

- [x] Modelo `Setting` + migración `settings` (key, value, tenant_id nullable, unique[ key, tenant_id ])
- [x] `SetSettingAction` (updateOrCreate por key+tenant_id) + `GetSettingAction` (resolución: tenant override > global > default)
- [x] `CrossTenantLeakTest` (2 tests): settings de tenant A no visibles desde tenant B
- [x] Tests (7): set/get global, default fallback, tenant override precedence, global fallback, tenant isolation + CrossTenantLeak (2)

## Sprint 5.4 — Media

- [x] Modelo `Media` + migración `media` (tenant_id, filename uuid, original_name, mime_type, size, disk)
- [x] `UploadMediaAction`: almacena archivo en `{disk}/tenant/{tenant_id}/{uuid}.{ext}`, crea registro DB
- [x] `DeleteMediaAction`: elimina archivo del disco + registro DB
- [x] Aislamiento por tenant via path + RLS en tabla media
- [x] `CrossTenantLeakTest` (2 tests): registros de tenant A no visibles desde tenant B + reseteo de contexto
- [x] Tests (5): upload, delete, path isolation + CrossTenantLeak (2)

## Sprint 5.5 — Search

- [x] `SearchEngineContract` interfaz (index, unindex, search con tenantId)
- [x] `PostgresSearchEngine`: implementación con `to_tsvector`/`plainto_tsquery` + GIN index + fallback SQLite LIKE
- [x] Migración `search_index` con `tsvector` generated column (PostgreSQL) + GIN index
- [x] `SearchServiceProvider` bindea contrato → PostgresSearchEngine (intercambiable)
- [x] Tests (6): index + search, sin resultados, unindex, filtro por tenant, binding engine, reindex

---

# FASE 6 — Paneles UI

Objetivo: interfaces Central y Tenant sobre Blade + Livewire + FluxUI, consumiendo exclusivamente Actions ya construidas.

## Sprint 6.1 — Panel Central

- [x] Layout base con Flux sidebar (Dashboard, Tenants, Plans, Features, Billing, logout) + `guard:Central`
- [x] Central login page (Livewire `CentralLogin`, guard Central, guest:Central middleware)
- [x] Central forgot/reset password pages (Livewire `CentralForgotPassword`, `CentralResetPassword`, broker `Central_users`)
- [x] Pantallas: Dashboard (health metrics), Tenants (list + create/suspend/resume/delete), Plans (list + create/archive), Features (list), Billing (subscriptions list)
- [x] Rutas Central (prefix `Central/`, middleware `auth:Central`, `guest:Central`)
- [x] Tests (11): login page, redirect, valid login, invalid login, dashboard render, tenants/plans/features/billing render, forgot/reset password flows

## Sprint 6.2 — Panel Tenant

- [x] Layout base con Flux sidebar (Users, Teams, Branding, API Keys) + guard `tenant`
- [x] Tenant login page (Livewire `TenantLogin`, guard tenant)
- [x] Tenant forgot/reset password pages (Livewire `TenantForgotPassword`, `TenantResetPassword`, broker `tenant_users`)
- [x] Pantallas: Users list, Teams list, Branding form (editable colors/logo), API Keys (generate/revoke)
- [x] Rutas tenant (prefix `tenant/`, middleware `auth:tenant`, `guest:tenant`)
- [x] Tests (10): login page, redirect, valid/invalid login, users/teams/branding/api-keys render, forgot/reset password flows

## Sprint 6.3 — Landing pública

- [x] Landing page (welcome, public layout), pricing page (dinámico con cards + features vía `GetActivePlansWithFeaturesAction`), contact page (form stub)
- [x] `TenantRegistration` Livewire: crea tenant + dispara `ProvisionTenantPipelineAction`
- [x] Public routes (home, pricing, contact, register) sin middleware de auth
- [x] Fix: `RehydrateTenantContext` y `ApplyTenantContext` skipean `SET LOCAL` en non-PostgreSQL
- [x] Fix: binding `DnsGatewayContract` → `CloudflareDnsGateway` en `PlatformServiceProvider`
- [x] Tests (7): welcome, pricing, contact, register render, validación, registro + success state

---

# FASE 7 — Hardening y release v1.0

Objetivo: llevar el framework a un estado verdaderamente production-ready, no solo funcionalmente completo.

## Sprint 7.1 — Activación de Octane en producción

- [x] Configurar Octane: `config/octane.php` con `flush` para `TenantContext`, `DisconnectFromDatabases` habilitado, `CollectGarbage`, tabla Swoole ejemplo eliminada
- [x] Scripts `octane:dev` (--watch) y `octane:prod` (--max-requests=500) en composer.json
- [x] Auditoría exhaustiva de bindings `singleton()`: 0 encontrados — todos los bindings son `scoped()` o `bind()`
- [x] `OctaneCrossTenantLeakTest` (3 tests): simula refresco de worker Octane vía `$this->refreshApplication()` y verifica que TenantContext se resetea, no hay fugas entre tenants, y scoped bindings devuelven nuevas instancias

## Sprint 7.2 — Auditoría de seguridad

- [x] RLS audit: policies existentes solo en `tenants` y `domains`. Creada migración `add_tenant_rls_policies_v2` con policies para 11 tablas tenant-aware faltantes
- [x] TenantContext bypass audit: 0 usos de queries crudas sin SET LOCAL. Únicas raw queries son PostgreSQL FTS (ya scoped por tenant_id) y test harness
- [x] Pentest isolation: 8 tests automatizados cubriendo tenant_users, teams, api_keys, branding, media, settings, subscriptions + validación de esquema (tenant_id)

## Sprint 7.3 — Documentación y release

- [x] `README.md` actualizado con instalación, panels (Central/Tenant/Public), módulos, comandos, reglas clave
- [x] `docs/EXTENDING.md` — guía completa de extensión: anatomy de módulo, paso a paso, cómo consumir core infra, reglas
- [x] `CHANGELOG.md` v1.0 — 173 tests, 257+ files, todas las fases documentadas

## ✅ FASE 7 COMPLETA — Hardening y release v1.0

---

# FASE 8 — Page Builder (Tenant Landing Personalizable)

Objetivo: cada tenant puede construir y publicar su propia landing page pública en `{slug}.LaraShift.app` mediante un compositor visual de componentes (bloques). El tenant admin (rol) puede agregar, quitar, reordenar y configurar cada bloque sin escribir código.

## Sprint 8.1 — Arquitectura de datos y núcleo

- [x] Migraciones: `tenant_pages` (tenant_id único, meta JSON, global_settings JSON, is_published) + `page_components` (tenant_page_id, type, sort_order, settings JSON, is_active)
- [x] `TenantPage` model con `activeComponents()` (scoped por is_active + ordenado) + `PageComponent` model
- [x] `RenderTenantPageAction`: ejecuta `TenantPage::where(tenant_id, is_published)` → retorna `RenderPageData` con componentes activos ordenados
- [x] Controlador `TenantLandingController` + vista `tenant.page.index` que itera `@include("tenant.page.components.{$type}")` + componente `hero` de ejemplo
- [x] Ruta pública en `routes/tenant.php` (panel y landing separados con middleware stancl)
- [x] Tests (9): creación de página, componentes con orden, null si no publicada, orden+activos, toggle active, settings globales + CrossTenantLeak (3)

## Sprint 8.2 — Componentes core

Se implementan 4-6 tipos de componente iniciales, cada uno con su Blade + defaults + form de settings.

| Componente           | Campos configurables                                                                        |
| -------------------- | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `hero`               | headline, subheadline, cta_text, cta_url, background_color, text_color, image_url           |
| `features-grid`      | title, columns (2-4), items[{icon, title, description}]                                     |
| `testimonials`       | title, items[{quote, author, role, avatar_url}], variant (carousel/list)                    |
| `cta-banner`         | headline, description, button_text, button_url, background_color                            |
| `footer-simple`      | company_name, links[{label, url}], show_social (bool)                                       |
| `rich-text`          | content (HTML sanitizado, solo tags permitidos: h1-h6, p, ul, ol, li, a, strong, em)        |
| `Gallery`            | title, items[{image (Media), caption}], variant (grid/masonry/carousel)                     | Puramente visual                                                                                                                                                                                  |
| `LogoCloud`          | title, items[{image (Media), url, alt}]                                                     | Común para "clientes que confían en nosotros"                                                                                                                                                     |
| `Pricing`            | title, items[{plan_name, price, period, features[], cta_text, cta_url, highlighted (bool)}] | Ojo: es texto libre de presentación, no se conecta al módulo real `Plans` (Central). Si alguien pide que jale los planes reales del tenant, ahí sí cruza a lógica vertical — ver sección de abajo |
| `FAQAccordion`       | title, items[{question, answer}]                                                            | Igual que `Testimonials`/`FeaturesGrid`, mismo patrón                                                                                                                                             |
| `StatsCounter`       | items[{value, label, suffix}]                                                               | Ej. "500+ clientes", "99.9% uptime" — presentación pura, sin conexión a datos reales                                                                                                              |
| `TeamGrid`           | title, items[{photo (Media), name, role, bio, social_links[]}]                              | Contenido estático curado por el tenant                                                                                                                                                           |
| `VideoEmbed`         | title, video_url (YouTube/Vimeo embed), aspect_ratio                                        | Necesita sanitización de URL (whitelist de dominios embebibles), mismo criterio que `RichText`                                                                                                    |
| `ContactInfo`        | address, phone, email, map_embed_url, hours[]                                               | Presentación de datos estáticos, no un formulario funcional                                                                                                                                       |
| `SocialLinks`        | items[{platform (enum), url}]                                                               | Enum de plataformas soportadas, no string libre                                                                                                                                                   |
| `Divider` / `Spacer` | height, color (opcional)                                                                    | Utilitario de layout puro                                                                                                                                                                         |
| `CustomHtmlEmbed`    | html (sanitizado)                                                                           | Mismo tratamiento que `RichText`, útil para embeds de terceros (ej. Calendly) siempre que pase por el mismo sanitizador con whitelist                                                             |

- [x] 17 componentes Blade: hero, features-grid, testimonials, cta-banner, footer-simple, rich-text, gallery, logo-cloud, pricing, faq-accordion, stats-counter, team-grid, video-embed, contact-info, social-links, divider, custom-html
- [x] `ComponentDefaults` con settings por defecto (17 tipos, accesible por type)
- [x] Livewire `PageComponentSettings` — implementación diferida a Sprint 8.3 (Builder UI renderiza settings inline)
- [x] Tests (12): render de cada componente core + defaults completeness + get by type

## Sprint 8.3 — Builder UI (Panel Tenant)

Layout de 3 columnas verticales con barra superior:

```
┌─────────────────────────────────────────────────────────────┐
│  [← Volver al panel]         Page Builder         [● Publicar ▼]
├──────────┬──────────────────────────────────┬────────────────┤
│ Izquierda│           Centro                 │   Derecha      │
│          │                                  │                │
│ [+ Add   │   Vista previa en tiempo real    │  Settings del  │
│  section]│   del diseño completo            │  componente    │
│          │   (iframe o render inline)       │  seleccionado  │
│ ──────── │                                  │                │
│ ○ Hero   │                                  │  ── General ── │
│   [🗑]   │                                  │  Headline:     │
│ ○ Feats  │                                  │  [___________] │
│   [🗑]   │                                  │  Subheadline:  │
│ ○ CTA    │                                  │  [___________] │
│   [🗑]   │                                  │                │
│ ○ Footer │                                  │  ── Style ───  │
│   [🗑]   │                                  │  Bg color:     │
│          │                                  │  [■ picker]    │
│          │                                  │  Text color:   │
│          │                                  │  [■ picker]    │
│          │                                  │                │
│          │                                  │  ── Media ──   │
│          │                                  │  Image:        │
│          │                                  │  [Upload]      │
└──────────┴──────────────────────────────────┴────────────────┘
```

**Columna izquierda:**

- Botón `[+ Add section]` que abre un dropdown/modal con la lista de tipos de componente disponibles (hero, features-grid, testimonials, cta-banner, footer-simple, rich-text)
- Debajo, lista de secciones agregadas con:
    - Nombre del componente (ej. "Hero", "Features")
    - Indicador visual de activo/inactivo (toggle o icono)
    - Botón `[🗑]` para eliminar (con confirmación)
    - Drag handle para reordenar (arrastrar y soltar)
    - Al hacer clic en un componente de la lista → se selecciona y abre sus settings en la columna derecha

**Columna central:**

- Vista previa en tiempo real del diseño completo
- Render inline (mismo DOM, con un `wire:key` para reactive updates) o en un `iframe` con el contenido real de la landing
- Se actualiza automáticamente al cambiar settings, reordenar, agregar o eliminar componentes
- Muestra un placeholder visual si no hay componentes

**Columna derecha:**

- Panel contextual de configuración que cambia según el tipo de componente seleccionado
- Secciones colapsables:
    - **General**: headline, subheadline, CTA text/URL, etc. (depende del tipo)
    - **Style**: color de fondo, color de texto, variant (si aplica)
    - **Media**: imagen de fondo, logo, icono, etc.
    - **Items**: para componentes con listas (features, testimonials), editor inline de items con add/remove/reorder
- Botón `[Apply]` o autosave al cambiar campos

**Barra superior:**

- Botón `[← Volver al panel]` → redirige al dashboard del tenant
- Indicador de estado: "Draft" / "Published" / "Modified since publish"
- Botón `[● Publish ▼]` con dropdown de opciones:
    - `Publish` — publica la página actual
    - `Unpublish` — despublica (la landing muestra un placeholder o 404)
    - `View live` — abre la landing pública en otra pestaña
    - `Schedule publish` — opcional: programar fecha de publicación

**Extras sugeridos:**

- [x] PageBuilder Livewire component: 3-column layout (component list + preview + settings panel)
- [x] Left panel: add component modal (17 types), remove, reorder (up/down), toggle active/inactive, select
- [x] Center: live preview rendering all active components via @include with wire:key
- [x] Right panel: dynamic settings form per type (hero, features-grid specialized + \_generic fallback)
- [x] Top bar: back link, draft/published indicator, publish/unpublish buttons
- [x] Autosave via wire:model.debounce.2000ms + save() on every mutation
- [x] DB persistence: sync components (create/update/delete) + is_published on TenantPage
- [x] Seed defaults: hero + features-grid + cta-banner + footer on first load
- [x] Route `/builder` + sidebar link
- [x] Tests (17): render, default components, add, remove, move up/down, select/deselect, toggle active, publish, unpublish, persist to DB

## Sprint 8.4 — Estilos globales y publish

- [x] Global settings UI en builder (primary_color, font_family, custom_css, meta_title, meta_description, og_image)
- [x] CSS personalizado inyectado en `<style>` del `<head>` en `tenant.page.index`
- [x] Cache vía `Cache::remember` en `TenantLandingController` (3600s) + invalidación en `PageBuilder::save()`
- [x] SEO tags: meta title, description, og:image desde `TenantPage.meta` JSON
- [x] Tests (5): global color persist, custom CSS persist, SEO persist, cache invalidation, CSS render in head

## Sprint 8.5 — Roles y permisos

- [x] `TenantPagePolicy` (view, update, publish) con verificación de tenant owner via TenantContext
- [x] `page-builder.access` gate + `can:page-builder.access,tenant` middleware en ruta `/builder`
- [x] Rol `page-editor` con permiso `page-builder.access` (creado via spatie, reutilizando AssignRoleAction)
- [x] `PageBuilderServiceProvider` registra policy + gate
- [x] Todos los tests de BuilderTest actualizados con asignación de rol page-editor
- [x] Tests (4): acceso denegado sin rol, acceso concedido con rol, permiso asignado, acceso a otras secciones

## ✅ FASE 8 COMPLETA — Page Builder
