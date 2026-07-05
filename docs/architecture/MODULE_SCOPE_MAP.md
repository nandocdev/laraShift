# Mapa modular por scope: Central, Tenant y Platform

> Documento de arquitectura objetivo para LaraShift. Describe los scopes propuestos, los módulos que los componen, sus funcionalidades, dependencias, relaciones de comunicación y arquitectura interna recomendada.

## 1. Propósito

Este documento define una reestructuración modular para LaraShift orientada a mantener el proyecto como un monolito modular, con límites claros entre plataforma, producto tenant e infraestructura transversal.

Los tres scopes objetivo son:

- **Central**: opera el negocio SaaS y la plataforma central.
- **Tenant**: contiene las capacidades que usan los clientes dentro de su workspace.
- **Platform**: concentra capacidades transversales, contratos, eventos, seguridad, tenancy, observabilidad y primitives técnicas reutilizables.

## 2. Principios de diseño

1. **Aislamiento primero**: todo recurso tenant-scoped debe protegerse por tenant context, policies, scopes y, cuando aplique, PostgreSQL RLS.
2. **Módulos con dueño claro**: cada modelo, action, job, listener, route y vista debe pertenecer a un módulo dueño.
3. **Comunicación explícita**: los módulos se comunican mediante contracts, application actions, domain events, integration events o queries explícitas.
4. **Platform no contiene negocio SaaS**: Platform provee capacidades reutilizables, pero no decide reglas comerciales de Central ni flujos de producto Tenant.
5. **Dependencias hacia adentro**: Central y Tenant pueden depender de Platform; Platform no debe depender de Central ni de Tenant.
6. **UI separada de dominio**: Livewire, controllers, requests y views viven en `Interface`; reglas del negocio viven en `Domain` y casos de uso en `Application`.

## 3. Arquitectura interna estándar de cada módulo

Cada módulo de Central y Tenant debe seguir esta estructura base cuando tenga suficiente complejidad:

```text
ModuleName/
├── Domain/
│   ├── Models/
│   ├── ValueObjects/
│   ├── Enums/
│   ├── Events/
│   ├── Exceptions/
│   ├── Policies/
│   └── Rules/
├── Application/
│   ├── Actions/
│   ├── Commands/
│   ├── Queries/
│   ├── DTO/
│   ├── Jobs/
│   ├── Listeners/
│   └── Services/
├── Infrastructure/
│   ├── Persistence/
│   ├── Clients/
│   ├── Gateways/
│   ├── Mail/
│   ├── Notifications/
│   └── Console/
├── Interface/
│   ├── Http/
│   ├── Api/
│   ├── Livewire/
│   ├── Routes/
│   └── Views/
├── Database/
│   ├── Factories/
│   ├── Seeders/
│   └── Migrations/
└── Providers/
```

### 3.1 Responsabilidad por capa

| Capa | Responsabilidad | No debe contener |
|---|---|---|
| `Domain` | Reglas puras, invariantes, modelos, value objects, eventos de dominio, policies y excepciones del negocio. | Controllers, Livewire, llamadas HTTP externas, consultas acopladas a UI. |
| `Application` | Casos de uso, orquestación, commands, queries, DTOs, jobs, listeners y servicios de aplicación. | Reglas fundamentales de dominio o detalles de proveedor externo. |
| `Infrastructure` | Adaptadores técnicos: gateways, clientes HTTP, providers externos, storage, mailers, persistence específica. | Decisiones de negocio o UI. |
| `Interface` | Entradas/salidas: HTTP, API, Livewire, routes, views, requests y resources. | Reglas de negocio complejas. |
| `Database` | Factories, seeders y migrations cuando se adopte colocación modular. | Lógica runtime. |
| `Providers` | Registro de bindings, routes, views, events, policies y config del módulo. | Casos de uso o dominio. |

## 4. Reglas de dependencia entre scopes

```text
Central  ─┐
          ├──> Platform
Tenant   ─┘

Central <──eventos/contratos──> Tenant
```

- **Central -> Platform**: permitido para tenancy, security, contracts, events, observability, data primitives y UI base.
- **Tenant -> Platform**: permitido para tenant context, security, API keys primitives, audit primitives, export primitives y UI base.
- **Tenant -> Central**: evitar dependencias directas. Si un flujo tenant requiere billing central, debe usar contracts o application services publicados por Central.
- **Central -> Tenant**: evitar dependencias directas. Si Central necesita datos tenant, debe usar read models, events, contracts o jobs tenant-aware.
- **Platform -> Central/Tenant**: prohibido. Platform debe ser independiente.

## 5. Scope Central

Central administra la operación del SaaS: adquisición, provisionamiento, facturación, catálogo comercial, soporte, operaciones internas y acceso administrativo.

### 5.1 Central/Access

#### Funcionalidades

- Autenticación de usuarios centrales.
- Gestión de sesiones administrativas.
- Enrolamiento y verificación de MFA central.
- Recuperación de contraseña para administradores.
- Revocación de sesiones antiguas.
- Validación de sesión central.
- Políticas de acceso a paneles internos.
- Auditoría básica de acciones administrativas sensibles.

#### Depende de

- `Platform/Security` para hashing, session primitives, MFA primitives y rate limiting.
- `Platform/Observability` para logs de acceso y eventos de seguridad.
- `Platform/Contracts` para contratos comunes de usuario central si son consumidos por otros módulos.

#### Es dependencia de

- `Central/Operations`, porque las operaciones internas requieren usuario central autenticado.
- `Central/Support`, porque impersonation y soporte requieren identidad administrativa.
- `Central/Billing`, para paneles administrativos de planes, suscripciones e invoices.
- `Central/Provisioning`, para acciones administrativas sobre tenants.

#### Comunicación

- Expone middleware o guards para validar usuarios centrales.
- Publica eventos como `CentralUserLoggedIn`, `CentralUserMfaEnabled`, `CentralSessionRevoked`.
- Otros módulos consumen su estado mediante guards, policies y contracts.

#### Arquitectura interna

```text
Central/Access/
├── Domain/
│   ├── Models/CentralUser.php
│   ├── Models/CentralSession.php
│   ├── Models/CentralMfa.php
│   ├── Events/
│   └── Policies/
├── Application/
│   ├── Actions/LoginCentralUser.php
│   ├── Actions/LogoutCentralUser.php
│   ├── Actions/EnrollCentralMfa.php
│   ├── Actions/RevokeOldestSession.php
│   └── DTO/LoginData.php
├── Infrastructure/
│   ├── Notifications/ResetPasswordNotification.php
│   └── Session/CentralSessionStore.php
├── Interface/
│   ├── Http/Middleware/ValidateCentralSession.php
│   ├── Livewire/Login.php
│   ├── Livewire/LoginChallenge.php
│   ├── Livewire/TwoFactorEnrollment.php
│   ├── Routes/web.php
│   └── Views/
└── Providers/AccessServiceProvider.php
```

### 5.2 Central/Billing

#### Funcionalidades

- Gestión de planes comerciales.
- Suscripciones de tenants.
- Checkout hosted.
- Cambio de plan.
- Cancelación de suscripción.
- Registro y actualización de método de pago.
- Generación de invoices y PDFs.
- Dunning y suspensión por fallos de pago.
- Reconciliación de suscripciones.
- Sincronización de invoices desde proveedores externos.
- Webhooks de billing.
- Exportación de datos financieros.
- Portal de billing visible para tenants mediante interfaz delegada.

#### Depende de

- `Central/Catalog` para planes, features, quotas y pricing.
- `Central/Provisioning` para localizar tenants y aplicar cambios de estado comercial.
- `Platform/Contracts` para interfaces de proveedor de billing y resolución de montos.
- `Platform/Events` para publicar eventos de suscripción y pago.
- `Platform/Data` para money casts, formatters y DTOs financieros.
- `Platform/Observability` para logs, métricas y trazabilidad de webhooks.

#### Es dependencia de

- `Tenant/Workspace` o `Tenant/Experience` para mostrar billing portal, estado de plan y banners de suscripción.
- `Central/Support` para soporte financiero a tenants.
- `Central/Operations` para reconciliación y monitoreo de fallos.

#### Comunicación

- Publica eventos: `SubscriptionCreated`, `SubscriptionUpdated`, `SubscriptionCancelled`, `PaymentSucceeded`, `PaymentFailed`, `TenantSuspendedByDunning`.
- Consume queries de `Central/Catalog` para calcular precio, entitlements y quotas.
- Usa contracts de `Platform/Contracts/Billing` para aislar Stripe, PagueloFacil, dLocal u otros gateways.
- Expone application actions para que Tenant consuma billing sin depender de modelos internos.

#### Arquitectura interna

```text
Central/Billing/
├── Domain/
│   ├── Models/Plan.php
│   ├── Models/Subscription.php
│   ├── Models/SubscriptionItem.php
│   ├── Models/Invoice.php
│   ├── Models/PaymentGatewayEvent.php
│   ├── ValueObjects/Money.php
│   ├── Enums/BillingStatus.php
│   ├── Events/
│   └── Policies/
├── Application/
│   ├── Actions/CreateCheckoutSession.php
│   ├── Actions/CancelSubscription.php
│   ├── Actions/ReconcileSubscription.php
│   ├── Actions/SyncInvoices.php
│   ├── Actions/GenerateInvoicePdf.php
│   ├── Jobs/SyncTenantInvoicesJob.php
│   ├── Listeners/FulfillSubscription.php
│   └── Services/BillingExportService.php
├── Infrastructure/
│   ├── Gateways/Stripe/
│   ├── Gateways/PagueloFacil/
│   ├── Gateways/Dlocal/
│   ├── Pdf/DompdfInvoiceRenderer.php
│   └── Console/ReconcileSubscriptionsCommand.php
├── Interface/
│   ├── Http/Controllers/StripeWebhookController.php
│   ├── Http/Controllers/BillingApiController.php
│   ├── Livewire/ManageBilling.php
│   ├── Livewire/SelectPlan.php
│   ├── Livewire/HostedCheckout.php
│   ├── Routes/web.php
│   ├── Routes/webhooks.php
│   └── Views/
└── Providers/BillingServiceProvider.php
```

### 5.3 Central/Catalog

#### Funcionalidades

- Definición de planes.
- Definición de features.
- Definición de quotas.
- Relación entre plan, features y límites.
- Overrides comerciales por tenant.
- Resolución de entitlements.
- Historial de cambios de catálogo.
- Exposición de read models para billing, provisioning y tenant runtime.

#### Depende de

- `Platform/Data` para DTOs, value objects y normalización.
- `Platform/Events` para publicar cambios de catálogo.
- `Platform/Tenancy` para aplicar overrides tenant-aware cuando corresponda.

#### Es dependencia de

- `Central/Billing`, que necesita planes y precios.
- `Tenant/Workspace`, que necesita saber límites y features disponibles.
- `Platform/Tenancy/Middleware`, que usa features y quotas para enforcement.
- `Central/Provisioning`, que asigna plan inicial y entitlements iniciales.

#### Comunicación

- Expone queries como `ResolveTenantFeatures`, `ResolveTenantQuotas`, `GetPlanCatalog`.
- Publica eventos como `PlanUpdated`, `FeatureEnabled`, `TenantFeatureOverrideApplied`.
- Es consumido por middleware transversal mediante contracts para evitar acoplamiento directo.

#### Arquitectura interna

```text
Central/Catalog/
├── Domain/
│   ├── Models/Plan.php
│   ├── Models/Feature.php
│   ├── Models/Quota.php
│   ├── Models/TenantFeatureOverride.php
│   ├── ValueObjects/Entitlement.php
│   └── Events/
├── Application/
│   ├── Actions/UpsertPlan.php
│   ├── Actions/DeletePlan.php
│   ├── Actions/ApplyTenantFeatureOverride.php
│   ├── Queries/ResolveTenantFeatures.php
│   └── Queries/ResolveTenantQuotas.php
├── Infrastructure/
│   └── Persistence/EntitlementReadModel.php
├── Interface/
│   ├── Livewire/PlanList.php
│   ├── Livewire/ManagePlan.php
│   ├── Livewire/FeatureList.php
│   ├── Livewire/TenantOverrides.php
│   └── Views/
└── Providers/CatalogServiceProvider.php
```

### 5.4 Central/Growth

#### Funcionalidades

- Landing pública de la plataforma.
- Registro público de tenants.
- Formularios de adquisición.
- Campañas, mensajes y contenido marketing.
- Public landings globales.
- Preselección de plan durante registro.
- Validaciones antifraude y rate limiting de registro.
- Medición de conversión.

#### Depende de

- `Central/Provisioning` para iniciar creación de tenant desde registro.
- `Central/Catalog` para mostrar planes y features.
- `Platform/Security` para throttling, captcha si aplica y protección anti-abuso.
- `Platform/Observability` para métricas de conversión.
- `Platform/UI` para layouts públicos.

#### Es dependencia de

- No debería ser dependencia de módulos core. Es un consumidor de Provisioning y Catalog.

#### Comunicación

- Invoca application actions de `Central/Provisioning` para crear tenants.
- Consulta `Central/Catalog` para renderizar planes.
- Publica eventos como `TenantRegistrationStarted` y `TenantRegistrationCompleted`.

#### Arquitectura interna

```text
Central/Growth/
├── Domain/
│   ├── Models/PublicLanding.php
│   ├── ValueObjects/RegistrationIntent.php
│   └── Events/
├── Application/
│   ├── Actions/RegisterTenantFromMarketing.php
│   ├── Queries/GetPublicPlans.php
│   └── Services/ConversionTracker.php
├── Infrastructure/
│   └── Analytics/MarketingAnalyticsAdapter.php
├── Interface/
│   ├── Livewire/LandingPage.php
│   ├── Livewire/RegisterTenant.php
│   ├── Routes/web.php
│   └── Views/
└── Providers/GrowthServiceProvider.php
```

### 5.5 Central/Provisioning

#### Funcionalidades

- Creación idempotente de tenants.
- Reserva de dominios y slugs.
- Inicialización de datos core del tenant.
- Creación de primer usuario/admin tenant.
- Asignación de plan inicial.
- Registro de logs de provisionamiento.
- Modo mantenimiento por tenant.
- Archivado, eliminación y purga de tenants.
- Hooks de infraestructura externa.
- Reintentos seguros y rollback parcial.

#### Depende de

- `Central/Catalog` para plan inicial y entitlements.
- `Central/Operations` para hooks de infraestructura externa.
- `Platform/Tenancy` para inicializar contexto tenant y filesystem/queue tenancy.
- `Platform/Events` para publicar `TenantProvisioned` y eventos relacionados.
- `Platform/Observability` para logs, métricas y trazabilidad.

#### Es dependencia de

- `Central/Growth`, que crea tenants desde registro.
- `Central/Billing`, que puede activar o suspender tenants por estado comercial.
- `Central/Support`, que requiere conocer tenants y dominios.
- `Tenant/Access`, que inicializa roles y primer admin a partir de eventos de provisionamiento.

#### Comunicación

- Publica eventos como `TenantProvisioned`, `TenantArchived`, `TenantDeleted`, `TenantMaintenanceModeChanged`.
- Consume actions de infraestructura a través de contracts para no depender directamente de Railway u otro proveedor.
- Expone commands CLI y Livewire para administración.

#### Arquitectura interna

```text
Central/Provisioning/
├── Domain/
│   ├── Models/Tenant.php
│   ├── Models/Domain.php
│   ├── Models/ProvisioningLog.php
│   ├── ValueObjects/TenantSlug.php
│   ├── Events/
│   └── Policies/
├── Application/
│   ├── Actions/CreateTenant.php
│   ├── Actions/ReserveTenantDomain.php
│   ├── Actions/SetupTenantCoreData.php
│   ├── Actions/ArchiveTenant.php
│   ├── Actions/DeleteTenant.php
│   ├── Jobs/PurgeTenantJob.php
│   └── DTO/CreateTenantData.php
├── Infrastructure/
│   ├── DomainResolver/TenantDomainResolver.php
│   └── Support/ReservedSlugs.php
├── Interface/
│   ├── Livewire/CreateTenant.php
│   ├── Livewire/ManageTenant.php
│   ├── Livewire/TenantList.php
│   ├── Console/ProvisionTenantCommand.php
│   ├── Routes/web.php
│   └── Views/
└── Providers/ProvisioningServiceProvider.php
```

### 5.6 Central/Operations

#### Funcionalidades

- Health checks.
- Gestión de queues y Horizon.
- Resolución dinámica de colas por tenant.
- Hooks de infraestructura externa.
- Railway service u otros proveedores cloud.
- Mantenimiento operativo.
- Configuración central de plataforma.
- Métricas operacionales.
- Comandos de administración técnica.

#### Depende de

- `Platform/Observability` para health, logs y metrics primitives.
- `Platform/Tenancy` para operaciones por tenant.
- `Platform/Contracts` para infrastructure providers.

#### Es dependencia de

- `Central/Provisioning`, para aprovisionar infraestructura externa.
- `Central/Billing`, para reconciliaciones y jobs recurrentes.
- `Central/Support`, para diagnóstico de tenant.

#### Comunicación

- Expone services como `TenantQueueManager`, `HorizonQueueResolver`, `InfrastructureProvisioner`.
- Publica eventos de sistema: `InfrastructureProvisioned`, `QueueResolutionFailed`, `HealthCheckFailed`.
- Consume eventos de Provisioning para crear recursos externos.

#### Arquitectura interna

```text
Central/Operations/
├── Domain/
│   ├── ValueObjects/HealthStatus.php
│   └── Events/
├── Application/
│   ├── Actions/ProvisionInfrastructure.php
│   ├── Queries/GetHealthStatus.php
│   └── Services/TenantQueueManager.php
├── Infrastructure/
│   ├── Clients/RailwayService.php
│   ├── Horizon/HorizonQueueResolver.php
│   └── Console/HorizonUpdateCommand.php
├── Interface/
│   ├── Http/Controllers/HealthCheckController.php
│   └── Routes/web.php
└── Providers/OperationsServiceProvider.php
```

### 5.7 Central/Support

#### Funcionalidades

- Impersonation segura de tenants.
- Bitácora de soporte.
- Notas internas por tenant.
- Broadcasts globales o segmentados.
- Centro de anuncios.
- Auditoría de acciones de soporte.
- Finalización de sesiones de impersonation.
- Notificaciones de soporte.

#### Depende de

- `Central/Access` para validar operador central.
- `Central/Provisioning` para identificar tenant y dominio.
- `Platform/Security` para tokens de impersonation y sesión segura.
- `Platform/Observability` para auditoría y logs.
- `Platform/Events` para broadcasts y eventos de soporte.

#### Es dependencia de

- `Tenant/Workspace`, que puede mostrar anuncios o banners enviados desde soporte.
- `Central/Operations`, para trazabilidad operacional de incidentes.

#### Comunicación

- Usa commands/actions para iniciar impersonation.
- Publica eventos como `SupportSessionStarted`, `SupportSessionEnded`, `BroadcastSent`.
- Tenant recibe broadcasts mediante notifications o read models.

#### Arquitectura interna

```text
Central/Support/
├── Domain/
│   ├── Models/SupportSession.php
│   ├── Models/SupportNote.php
│   ├── Models/Broadcast.php
│   ├── Events/
│   └── Policies/
├── Application/
│   ├── Actions/ImpersonateTenant.php
│   ├── Actions/CreateSupportNote.php
│   ├── Actions/SendBroadcast.php
│   ├── Jobs/SendBulkBroadcastJob.php
│   └── DTO/BroadcastData.php
├── Infrastructure/
│   └── Notifications/
├── Interface/
│   ├── Http/Controllers/TenantImpersonationController.php
│   ├── Http/Middleware/AuditImpersonationActions.php
│   ├── Livewire/BroadcastCenter.php
│   ├── Livewire/TenantSupportBitacora.php
│   └── Views/
└── Providers/SupportServiceProvider.php
```

## 6. Scope Tenant

Tenant contiene las funcionalidades que el cliente final usa dentro de su organización, workspace o dominio tenant.

### 6.1 Tenant/Access

#### Funcionalidades

- Login tenant.
- Logout tenant.
- Registro, si aplica.
- Recuperación y reset de contraseña.
- Verificación de email.
- MFA tenant.
- Passkeys tenant.
- Invitaciones a usuarios.
- Roles y permisos.
- API keys tenant.
- Revocación de usuarios.
- Validación de usuario activo.
- Validación de pertenencia al tenant.
- Enforcement de MFA requerido por tenant.

#### Depende de

- `Platform/Tenancy` para tenant context, scopes y middleware.
- `Platform/Security` para API key primitives, HMAC, MFA, passkeys, sessions y rate limiting.
- `Platform/Events` para eventos de identidad tenant.
- `Platform/Observability` para logs de seguridad.

#### Es dependencia de

- `Tenant/Workspace`, porque miembros y dashboard requieren usuario autenticado.
- `Tenant/Experience`, porque settings y branding requieren permisos.
- `Tenant/Compliance`, porque auditoría registra eventos de acceso.
- `Tenant/Integrations`, porque API clients y SMTP requieren usuario autorizado.

#### Comunicación

- Publica eventos: `TenantUserInvited`, `TenantUserJoined`, `TenantUserRevoked`, `TenantRoleCreated`, `TenantRoleUpdated`, `TenantApiKeyCreated`, `TenantApiKeyRevoked`, `TenantMfaRequirementChanged`.
- Expone middlewares: `EnsureUserIsActive`, `EnsureUserBelongsToTenant`, `EnforceTenantMfa`, `AuthenticateApiKey`.
- Otros módulos usan policies, permissions y guards.

#### Arquitectura interna

```text
Tenant/Access/
├── Domain/
│   ├── Models/User.php
│   ├── Models/Role.php
│   ├── Models/Permission.php
│   ├── Models/Invitation.php
│   ├── Models/ApiKey.php
│   ├── Models/UserMfa.php
│   ├── Events/
│   └── Policies/
├── Application/
│   ├── Actions/AcceptInvitation.php
│   ├── Actions/SendInvitation.php
│   ├── Actions/GenerateApiKey.php
│   ├── Actions/RevokeApiKey.php
│   ├── Actions/EnrollTenantMfa.php
│   ├── Actions/EnsureTenantRolesExist.php
│   └── DTO/
├── Infrastructure/
│   ├── Hashing/ApiKeyHasher.php
│   ├── Notifications/TenantInvitationNotification.php
│   ├── Fortify/
│   └── Passkeys/
├── Interface/
│   ├── Http/Middleware/
│   ├── Http/Controllers/Api/IdentityApiController.php
│   ├── Livewire/Login.php
│   ├── Livewire/LoginChallenge.php
│   ├── Livewire/AcceptInvitation.php
│   ├── Livewire/RoleManagement.php
│   ├── Livewire/ManageApiKeys.php
│   ├── Routes/web.php
│   ├── Routes/api.php
│   └── Views/
└── Providers/AccessServiceProvider.php
```

### 6.2 Tenant/Workspace

#### Funcionalidades

- Dashboard tenant.
- Gestión de miembros.
- Team management.
- Centro de notificaciones tenant.
- Banners operativos y de suscripción.
- Vista de usage overview.
- Estado del workspace.
- Consumo de features y quotas.
- Pantallas tenant que no pertenecen a settings, access o compliance.

#### Depende de

- `Tenant/Access` para usuario autenticado, roles y permisos.
- `Central/Catalog` mediante contracts/read models para features y quotas.
- `Central/Billing` mediante contracts/read models para estado de suscripción.
- `Platform/Tenancy` para tenant context.
- `Platform/UI` para layouts y componentes.

#### Es dependencia de

- `Tenant/Experience`, que puede renderizar datos básicos del workspace.
- `Tenant/Compliance`, que puede auditar acciones de miembros.

#### Comunicación

- Consume events de Access para actualizar notificaciones.
- Consulta `Catalog` para entitlements.
- Consulta `Billing` para subscription banner.
- Publica eventos como `WorkspaceNotificationCreated` y `MemberProfileUpdated`.

#### Arquitectura interna

```text
Tenant/Workspace/
├── Domain/
│   ├── Models/Notification.php
│   ├── ValueObjects/WorkspaceStatus.php
│   └── Events/
├── Application/
│   ├── Queries/GetDashboardSummary.php
│   ├── Queries/GetUsageOverview.php
│   ├── Actions/MarkNotificationAsRead.php
│   ├── Actions/DeleteNotification.php
│   └── Services/WorkspaceNotificationService.php
├── Infrastructure/
│   └── ReadModels/SubscriptionBannerReadModel.php
├── Interface/
│   ├── Livewire/Dashboard.php
│   ├── Livewire/TeamManagement.php
│   ├── Livewire/NotificationCenter.php
│   ├── Livewire/UsageOverview.php
│   ├── Routes/web.php
│   └── Views/
└── Providers/WorkspaceServiceProvider.php
```

### 6.3 Tenant/Experience

#### Funcionalidades

- Branding tenant.
- Logo, colores, favicon y temas.
- Localización: idioma, zona horaria, formato de fecha, moneda.
- Preferencias visuales.
- Landing builder tenant.
- Renderizado de landings tenant.
- Configuración de experiencia pública del tenant.

#### Depende de

- `Tenant/Access` para permisos sobre settings.
- `Platform/Tenancy` para guardar configuración aislada por tenant.
- `Platform/UI` para componentes, layouts y renderer base.
- `Platform/Data` para DTOs y exportación de configuración.

#### Es dependencia de

- `Tenant/Workspace`, que usa branding y localización.
- `Tenant/Integrations`, cuando emails SMTP usan branding.
- `Central/Growth` solo indirectamente si se comparten renderers base desde Platform.

#### Comunicación

- Publica eventos: `TenantSettingsUpdated`, `TenantBrandingUpdated`, `TenantLocalizationUpdated`, `TenantLandingPublished`.
- Expone queries para leer branding/localization actuales.
- Consume tenant context desde Platform.

#### Arquitectura interna

```text
Tenant/Experience/
├── Domain/
│   ├── Models/TenantSetting.php
│   ├── Models/Landing.php
│   ├── Models/LandingVersion.php
│   ├── ValueObjects/Branding.php
│   ├── ValueObjects/Localization.php
│   └── Events/
├── Application/
│   ├── Actions/UpdateTenantBranding.php
│   ├── Actions/UpdateTenantLocalization.php
│   ├── Actions/InitializeTenantLanding.php
│   ├── Actions/PublishLanding.php
│   ├── Actions/RenderLanding.php
│   └── DTO/
├── Infrastructure/
│   ├── Rendering/LandingRenderer.php
│   └── Export/SettingsExportService.php
├── Interface/
│   ├── Livewire/BrandingSettings.php
│   ├── Livewire/LocalizationSettings.php
│   ├── Livewire/LandingBuilder.php
│   ├── Http/Controllers/ServeTenantLandingController.php
│   ├── Routes/web.php
│   └── Views/
└── Providers/ExperienceServiceProvider.php
```

### 6.4 Tenant/Compliance

#### Funcionalidades

- Audit logs tenant.
- Registro de acciones de autenticación, roles, miembros, API keys y settings.
- Exportación de audit logs.
- Exportación de datos del tenant.
- Notificación de exportación completada.
- Security events.
- Retention policies futuras.
- Evidencia de compliance.

#### Depende de

- `Tenant/Access` para identificar actor, roles y eventos de identidad.
- `Tenant/Experience` para incluir settings en exportaciones.
- `Tenant/Workspace` para eventos de miembros y notificaciones.
- `Platform/Observability` para audit primitives.
- `Platform/Data` para exports y serialization.
- `Platform/Tenancy` para tenant context.

#### Es dependencia de

- Todos los módulos Tenant que necesiten registrar acciones auditables.
- `Central/Support`, para consultar trazabilidad durante soporte si está autorizado.

#### Comunicación

- Consume domain events de Access, Workspace, Experience e Integrations.
- Publica eventos como `AuditLogRecorded`, `TenantDataExportRequested`, `TenantDataExportCompleted`.
- Expone queries y downloads protegidos por permisos.

#### Arquitectura interna

```text
Tenant/Compliance/
├── Domain/
│   ├── Models/AuditLog.php
│   ├── Enums/AuditAction.php
│   ├── ValueObjects/AuditActor.php
│   └── Events/
├── Application/
│   ├── Actions/RecordAuditLog.php
│   ├── Actions/ExportTenantData.php
│   ├── Jobs/ExportAuditLogsJob.php
│   ├── Jobs/ExportTenantDataJob.php
│   ├── Listeners/TenantAuthAuditSubscriber.php
│   └── DTO/AuditLogData.php
├── Infrastructure/
│   ├── Export/IdentityExportService.php
│   ├── Export/SettingsExportService.php
│   └── Notifications/
├── Interface/
│   ├── Livewire/AuditLogViewer.php
│   ├── Livewire/DataExport.php
│   ├── Http/Controllers/AuditDownloadController.php
│   ├── Routes/web.php
│   └── Views/
└── Providers/ComplianceServiceProvider.php
```

### 6.5 Tenant/Integrations

#### Funcionalidades

- Configuración SMTP tenant.
- Validación y prueba de SMTP.
- Webhooks outbound futuros.
- API clients externos.
- Credenciales externas tenant-scoped.
- Configuración de servicios externos por tenant.
- Eventos de integración.

#### Depende de

- `Tenant/Access` para permisos.
- `Tenant/Experience` para branding en emails.
- `Platform/Security` para encryption y secrets management.
- `Platform/Tenancy` para aislamiento de configuraciones.
- `Platform/Events` para eventos outbound.

#### Es dependencia de

- `Tenant/Workspace`, si necesita notificaciones por email.
- `Tenant/Compliance`, que audita cambios de integración.
- Módulos futuros de producto que requieran conectores externos.

#### Comunicación

- Publica eventos: `TenantSmtpConfigured`, `IntegrationCredentialRotated`, `OutboundWebhookDelivered`, `OutboundWebhookFailed`.
- Expone services como `TenantMailerService`.
- Consume Platform Security para cifrado de secretos.

#### Arquitectura interna

```text
Tenant/Integrations/
├── Domain/
│   ├── Models/IntegrationCredential.php
│   ├── ValueObjects/SmtpConfig.php
│   ├── Events/
│   └── Policies/
├── Application/
│   ├── Actions/UpdateTenantSmtp.php
│   ├── Actions/TestTenantSmtp.php
│   ├── Actions/RegisterOutboundWebhook.php
│   └── Services/TenantMailerService.php
├── Infrastructure/
│   ├── Mail/TenantMailTransportFactory.php
│   ├── Webhooks/OutboundWebhookClient.php
│   └── Secrets/EncryptedCredentialStore.php
├── Interface/
│   ├── Livewire/SmtpSettings.php
│   ├── Livewire/WebhookSettings.php
│   ├── Routes/web.php
│   └── Views/
└── Providers/IntegrationsServiceProvider.php
```

## 7. Scope Platform

Platform es la capa transversal. Debe ser estable, reutilizable e independiente de Central y Tenant.

### 7.1 Platform/Foundation

#### Funcionalidades

- Providers base.
- Helpers estrictamente técnicos.
- Configuración compartida.
- Base controllers.
- Base service provider utilities.
- Registro de macros, casts globales y convenciones.
- Integración con Laravel framework.

#### Depende de

- Laravel framework.
- Paquetes de infraestructura comunes.

#### Es dependencia de

- Todos los módulos que usen foundation primitives.

#### Comunicación

- No publica eventos de negocio.
- Expone clases base, traits técnicos y helpers controlados.

#### Arquitectura interna

```text
Platform/Foundation/
├── Laravel/
├── Providers/
├── Http/
├── Config/
├── Support/
└── Testing/
```

### 7.2 Platform/Contracts

#### Funcionalidades

- Contratos compartidos entre módulos.
- Interfaces para billing providers.
- Interfaces para tenant resolver.
- Interfaces para exportables.
- Interfaces para payment amount resolver.
- Interfaces para domain services transversales.

#### Depende de

- No debe depender de Central ni Tenant.
- Puede depender de PHP interfaces, Laravel contracts mínimos o DTOs Platform.

#### Es dependencia de

- Central y Tenant cuando necesiten comunicarse sin acoplamiento concreto.
- Platform submódulos que requieran contratos internos.

#### Comunicación

- Define interfaces, no ejecuta comunicación.
- Se implementa en módulos concretos y se resuelve vía container.

#### Arquitectura interna

```text
Platform/Contracts/
├── Billing/
├── Tenancy/
├── Payments/
├── Export/
├── Notifications/
└── Security/
```

### 7.3 Platform/Events

#### Funcionalidades

- Eventos de dominio compartidos.
- Eventos de integración entre módulos.
- Eventos de sistema.
- Event envelopes.
- Convenciones de naming y metadata.

#### Depende de

- `Platform/Data` para event payload DTOs si aplica.

#### Es dependencia de

- Central, Tenant y Platform submódulos.

#### Comunicación

- Publicación mediante event bus de Laravel.
- Subscribers por módulo.
- Eventos no deben conocer listeners concretos.

#### Arquitectura interna

```text
Platform/Events/
├── Domain/
├── Integration/
├── System/
├── Metadata/
└── Subscribers/
```

### 7.4 Platform/Tenancy

#### Funcionalidades

- Tenant context.
- Inicialización de tenancy.
- Middleware tenant-aware.
- PostgreSQL RLS bootstrapper.
- Eloquent tenant scopes.
- Rate limits tenant-aware.
- Feature and quota enforcement middleware.
- Commands de RLS.
- Tenant filesystem/queue context integration.

#### Depende de

- `Platform/Contracts` para resolver tenants o entitlement providers.
- `Platform/Observability` para logs críticos de aislamiento.
- Stancl Tenancy y Laravel database.

#### Es dependencia de

- Todos los módulos Tenant.
- Central/Provisioning.
- Central/Billing y Central/Catalog cuando aplican lógica por tenant.

#### Comunicación

- Expone `TenantContext`, middleware y bootstrappers.
- Publica eventos de sistema cuando tenancy se inicializa o falla.
- Consume providers concretos mediante contracts.

#### Arquitectura interna

```text
Platform/Tenancy/
├── Context/
│   ├── TenantContext.php
│   └── CurrentTenant.php
├── Bootstrappers/
│   └── PostgresRlsBootstrapper.php
├── Middleware/
│   ├── ApplyTenantRateLimits.php
│   ├── EnsureTenantIsActive.php
│   ├── EnsureHasFeature.php
│   └── EnsureWithinQuota.php
├── Scopes/
│   ├── BelongsToTenant.php
│   └── TenantScope.php
├── RLS/
│   ├── Commands/EnableRlsCommand.php
│   └── Policies/
└── Providers/TenancyPlatformServiceProvider.php
```

### 7.5 Platform/Security

#### Funcionalidades

- HMAC helpers.
- API key hashing primitives.
- Encryption helpers.
- Session security primitives.
- MFA primitives reutilizables.
- Rate limiting base.
- Secret management.
- Secure token generation.
- Password policy primitives.

#### Depende de

- Laravel encryption, hashing, cache y rate limiter.
- `Platform/Observability` para seguridad y alertas.

#### Es dependencia de

- `Central/Access`.
- `Tenant/Access`.
- `Tenant/Integrations`.
- `Central/Support` para impersonation tokens.

#### Comunicación

- No debe publicar eventos de negocio por defecto.
- Puede publicar eventos de sistema/security como `SecurityTokenGenerated`, `SuspiciousRequestDetected` si se requiere.

#### Arquitectura interna

```text
Platform/Security/
├── Hmac/
├── ApiKeys/
├── Encryption/
├── Mfa/
├── RateLimiting/
├── Sessions/
├── Tokens/
└── Passwords/
```

### 7.6 Platform/Observability

#### Funcionalidades

- Logging estructurado.
- Métricas.
- Health check primitives.
- Audit primitives reutilizables.
- Alertas.
- Correlation IDs.
- Trazabilidad de jobs.
- Registro de fallos críticos de tenancy y seguridad.

#### Depende de

- Laravel logging, queue y events.
- Drivers externos opcionales de observabilidad.

#### Es dependencia de

- Todos los módulos con workflows críticos.
- `Central/Operations`, `Central/Provisioning`, `Central/Billing`, `Tenant/Compliance`, `Platform/Tenancy`.

#### Comunicación

- Recibe eventos de dominio/sistema.
- Expone services para metrics y logs.
- Publica alertas técnicas si aplica.

#### Arquitectura interna

```text
Platform/Observability/
├── Logging/
├── Metrics/
├── Health/
├── Audit/
├── Alerts/
├── Correlation/
└── QueueTracing/
```

### 7.7 Platform/Data

#### Funcionalidades

- Casts reutilizables.
- Money primitives.
- DTO base.
- Serialization helpers.
- Export primitives.
- Formatters comunes.
- Data validation primitives.

#### Depende de

- Spatie Data si aplica.
- Laravel casts y validation.

#### Es dependencia de

- Central/Billing.
- Central/Catalog.
- Tenant/Compliance.
- Tenant/Experience.
- Tenant/Integrations.

#### Comunicación

- No se comunica por eventos.
- Provee objetos, casts y serializers.

#### Arquitectura interna

```text
Platform/Data/
├── Casts/
├── DTO/
├── Money/
├── Export/
├── Serialization/
├── Formatting/
└── Validation/
```

### 7.8 Platform/UI

#### Funcionalidades

- Layouts base.
- Componentes compartidos.
- Navegación común.
- Vistas de error.
- Design system.
- Partials reutilizables.
- Base Livewire components.

#### Depende de

- Livewire, Flux, Blade, Tailwind y Vite.

#### Es dependencia de

- Interfaces de Central y Tenant.

#### Comunicación

- No comunica negocio.
- Expone componentes y layouts.

#### Arquitectura interna

```text
Platform/UI/
├── Layouts/
├── Components/
├── Navigation/
├── Errors/
├── Livewire/
├── Assets/
└── DesignSystem/
```

## 8. Comunicación entre módulos

### 8.1 Patrones permitidos

| Patrón | Uso recomendado | Ejemplo |
|---|---|---|
| Application Action | Flujo síncrono dentro del mismo bounded context o llamado explícito autorizado. | `CreateTenant`, `CancelSubscription`. |
| Query | Lectura sin side effects. | `ResolveTenantFeatures`, `GetDashboardSummary`. |
| Contract | Comunicación entre scopes sin acoplar implementación. | `BillingProvider`, `TenantDomainResolver`. |
| Domain Event | Hecho de negocio dentro del dominio. | `TenantProvisioned`. |
| Integration Event | Comunicación entre módulos. | `SubscriptionUpdated`. |
| Job | Trabajo async, reintentos, procesos largos. | `SyncTenantInvoicesJob`. |
| Read Model | Lecturas optimizadas entre módulos. | `SubscriptionBannerReadModel`. |

### 8.2 Patrones a evitar

- Importar modelos de otro scope para escribir directamente su estado.
- Consultar tablas de otro módulo desde controllers.
- Usar helpers globales para reglas de negocio.
- Meter lógica de dominio en Livewire components.
- Crear carpetas `Support` genéricas sin dueño conceptual.
- Hacer que Platform dependa de Central o Tenant.

## 9. Matriz de dependencia recomendada

| Módulo | Depende de | Es dependencia de |
|---|---|---|
| Central/Access | Platform/Security, Platform/Observability, Platform/Contracts | Central/Operations, Central/Support, Central/Billing, Central/Provisioning |
| Central/Billing | Central/Catalog, Central/Provisioning, Platform/Contracts, Platform/Data, Platform/Events | Tenant/Workspace, Central/Support, Central/Operations |
| Central/Catalog | Platform/Data, Platform/Events, Platform/Tenancy | Central/Billing, Tenant/Workspace, Platform/Tenancy, Central/Provisioning |
| Central/Growth | Central/Provisioning, Central/Catalog, Platform/Security, Platform/UI | Ningún módulo core debería depender de Growth |
| Central/Provisioning | Central/Catalog, Central/Operations, Platform/Tenancy, Platform/Events | Central/Growth, Central/Billing, Central/Support, Tenant/Access |
| Central/Operations | Platform/Observability, Platform/Tenancy, Platform/Contracts | Central/Provisioning, Central/Billing, Central/Support |
| Central/Support | Central/Access, Central/Provisioning, Platform/Security, Platform/Observability | Tenant/Workspace, Central/Operations |
| Tenant/Access | Platform/Tenancy, Platform/Security, Platform/Events, Platform/Observability | Tenant/Workspace, Tenant/Experience, Tenant/Compliance, Tenant/Integrations |
| Tenant/Workspace | Tenant/Access, Central/Catalog contracts, Central/Billing contracts, Platform/Tenancy, Platform/UI | Tenant/Experience, Tenant/Compliance |
| Tenant/Experience | Tenant/Access, Platform/Tenancy, Platform/UI, Platform/Data | Tenant/Workspace, Tenant/Integrations |
| Tenant/Compliance | Tenant/Access, Tenant/Experience, Tenant/Workspace, Platform/Observability, Platform/Data | Todos los módulos Tenant auditables, Central/Support autorizado |
| Tenant/Integrations | Tenant/Access, Tenant/Experience, Platform/Security, Platform/Tenancy, Platform/Events | Tenant/Workspace, Tenant/Compliance, módulos futuros |
| Platform/Foundation | Laravel/framework primitives | Todos los módulos |
| Platform/Contracts | Ninguno de Central/Tenant | Central, Tenant, Platform submódulos |
| Platform/Events | Platform/Data opcional | Central, Tenant, Platform submódulos |
| Platform/Tenancy | Platform/Contracts, Platform/Observability, Stancl Tenancy | Todos los módulos tenant-aware |
| Platform/Security | Laravel security primitives, Platform/Observability opcional | Access, Support, Integrations |
| Platform/Observability | Laravel logging/events/queue | Todos los workflows críticos |
| Platform/Data | Spatie Data/Laravel primitives | Billing, Catalog, Compliance, Experience |
| Platform/UI | Livewire/Blade/Tailwind/Flux | Interfaces Central y Tenant |

## 10. Plan de adopción recomendado

### Fase 1: Documentar y congelar convenciones

- Adoptar oficialmente los scopes `Central`, `Tenant` y `Platform`.
- Definir la estructura interna estándar.
- Prohibir nuevos módulos bajo `Shared`; crear `Platform` para nuevos desarrollos.

### Fase 2: Normalizar módulos existentes sin cambiar comportamiento

- Reorganizar internamente `Central/Billing`.
- Reorganizar internamente `Tenant/Identity` hacia `Tenant/Access`.
- Reorganizar internamente `Tenant/Settings` hacia `Tenant/Experience` e `Tenant/Integrations`.
- Mantener compatibilidad con class aliases o namespaces temporales si es necesario.

### Fase 3: Extraer Platform

- Mover tenancy primitives a `Platform/Tenancy`.
- Mover contracts a `Platform/Contracts`.
- Mover events compartidos a `Platform/Events`.
- Mover casts, money y export primitives a `Platform/Data`.
- Mover middleware y primitives de seguridad a `Platform/Security`.

### Fase 4: Reducir rutas raíz

- Convertir `routes/tenant.php` en bootstrapper de grupos tenant.
- Mover rutas concretas a `Interface/Routes` de cada módulo.
- Hacer lo mismo con rutas centrales y webhooks.

### Fase 5: Resolver solapamientos

- Fusionar `Central/Payments` dentro de `Central/Billing/Infrastructure/Gateways` si solo sirve a billing SaaS.
- Mover landing builder tenant a `Tenant/Experience/Landings`.
- Consolidar plans, features y quotas en `Central/Catalog`.
- Mover settings centrales operacionales a `Central/Operations/PlatformSettings`.

## 11. Resultado esperado

La reestructuración propuesta reduce acoplamiento, hace explícitos los límites de dominio y optimiza la mantenibilidad del monolito modular. El resultado esperado es:

- Menos archivos raíz con demasiada responsabilidad.
- Módulos con estructura interna consistente.
- Dependencias explícitas y auditables.
- Platform transversal sin lógica de negocio.
- Tenant más orientado a capacidades de producto.
- Central más orientado a operación SaaS.
- Mejor base para escalar funcionalidades sin degradar la arquitectura.
