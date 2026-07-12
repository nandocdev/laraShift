# PROJECT DECISIONS

> Este documento contiene las decisiones arquitectónicas oficiales de LaraShift.
>
> Son obligatorias para cualquier implementación. Ningún agente o desarrollador debe asumir una alternativa distinta sin actualizar previamente este documento.

---

# 1. Nombre del proyecto

Nombre oficial:

**LaraShift**

Este nombre debe utilizarse en:

- documentación
- prompts
- commits
- README
- PRD
- ROADMAP

No utilizar nombres anteriores o alternativos.

---

# 2. Arquitectura

Arquitectura oficial:

- Modular Monolith
- Domain-Oriented Modules
- Laravel First
- Convention over Configuration
- **Framework reutilizable, no producto SaaS.** El core no contiene lógica de dominio de negocio bajo ninguna circunstancia.

No utilizar:

- Microservicios
- CQRS completo
- Event Sourcing
- Arquitecturas distribuidas

---

# 3. Estrategia de Multitenancy

Modelo oficial:

**Single Database + tenant_id + PostgreSQL Row Level Security (RLS)**

Cada registro perteneciente a un tenant debe contener:

- tenant_id

El aislamiento ocurre mediante:

1. Tenant Resolver
2. Tenant Context (binding `scoped()`, nunca `singleton()`)
3. `SET LOCAL app.tenant_id` dentro de una transacción explícita por unidad de trabajo
4. PostgreSQL RLS

Ningún módulo implementa mecanismos propios de aislamiento. Global Scopes de Eloquent pueden usarse como capa adicional de defensa, pero nunca como único mecanismo de aislamiento — RLS es la garantía real, porque no depende de que cada consulta pase por Eloquent.

---

# 4. Propagación del Tenant Context

El contexto del tenant se obtiene una única vez al inicio de la petición.

Flujo:

Request ↓

Tenant Resolver ↓

Tenant Context (scoped) ↓

Middleware (`SET LOCAL` dentro de transacción)

↓

Application

↓

Actions

↓

Models

Los módulos nunca reciben manualmente el tenant mediante parámetros. Siempre utilizan el Tenant Context.

## Jobs en cola

El flujo anterior aplica solo a requests HTTP. Un Job en cola **no hereda el Tenant Context del request que lo despachó**, ni de una conexión de base de datos reutilizada por el worker. Por lo tanto:

- Todo Job que acceda a datos tenant-aware debe implementar el contrato `TenantAware` (método `tenantId(): string`).
- Todo Job `TenantAware` debe declarar el middleware `RehydrateTenantContext`, que ejecuta `SET LOCAL app.tenant_id` dentro de una transacción explícita antes de invocar `handle()`.
- Un Job que no implemente `TenantAware` y acceda a datos tenant-aware debe fallar en tiempo de ejecución con una excepción explícita. Nunca se ejecuta en silencio sin contexto.

Esta regla existe porque Octane y los pools de conexión (PgBouncer en modo transaction/session incluido) reutilizan tanto el proceso PHP como la conexión a base de datos entre unidades de trabajo. Sin este contrato, un Job puede heredar el `tenant_id` de la última operación que usó esa conexión — una fuga de datos entre tenants.

---

# 5. Identificación del Tenant

Métodos soportados:

- Subdominios
- Dominios personalizados (Custom Domains)

Ejemplos:

```
empresa.LaraShift.app

app.cliente.com
```

El Tenant Resolver debe soportar ambos mecanismos, con resolución cacheada en Redis (`tenant:domain:{host}` → `tenant_id`) para evitar una consulta a base de datos en cada request solo para resolver el tenant activo.

---

# 6. Cache

Toda clave cacheada debe estar aislada por tenant.

Formato:

```
tenant:{tenant_id}:{key}
```

Nunca compartir claves entre tenants.

---

# 7. Base de Datos

Motor oficial:

PostgreSQL

No existe soporte para MySQL.

Características utilizadas:

- JSONB
- UUID
- Row Level Security
- Full Text Search
- Triggers cuando sean necesarios

---

# 8. Infraestructura

Stack oficial:

- PostgreSQL
- Redis
- Laravel Queue (driver Redis)
- Redis Cache
- Redis Locks
- **Redis Sessions — default obligatorio en producción y staging.** El driver `file` o `database` solo está permitido en entorno local sin Octane. No es una opción abierta: con múltiples workers de Octane/Horizon o múltiples instancias detrás de un load balancer, cualquier driver que no sea Redis rompe el escalado horizontal sin sticky sessions.

No utilizar bases de datos como mecanismo de cache.

---

# 9. Colas

Toda tarea pesada debe ejecutarse mediante Jobs.

Los Jobs deben ser:

- idempotentes
- reintentables
- tenant-aware mediante el contrato `TenantAware` (ver sección 4)

---

# 10. Octane

Estado:

No forma parte del MVP, pero toda implementación debe ser **compatible desde el diseño**, no adaptada después.

Por lo tanto:

- no utilizar estado estático mutable
- no almacenar información en propiedades singleton
- no asumir reinicio del proceso por request
- todo binding con estado por request (como `TenantContext`) debe registrarse como `scoped()`, nunca `singleton()`

---

# 11. Clasificación de módulos

## Platform

Infraestructura transversal reutilizable. Independiente de Central y Tenant — no importa clases de estos scopes.

| Módulo | Responsabilidad |
|---|---|
| Contracts | Interfaces compartidas entre módulos (TenantContract, BillingProvider, FeatureResolver) |
| Data | Casts, DTOs base, formatters, PlatformTenant DTO |
| Events | Eventos de integración entre módulos (TenantProvisioned, SubscriptionCreated, etc.) |
| Foundation | Providers base, Controller base, helpers técnicos |
| Observability | Audit primitives, HealthChecker |
| Security | HmacSigner, MfaService, ApiKeyHasher, TenantRateLimiter |
| Tenancy | TenantContext, BelongsToTenant trait, RLS bootstrapper, middleware tenant-aware |
| UI | Layouts, componentes Blade, DesignSystem, SidebarBuilder |

## Central

Operación de la plataforma SaaS. Administración del negocio multi-tenant.

| Módulo | Responsabilidad |
|---|---|
| Auth | Autenticación de staff/plataforma (guard `central`, modelo `CentralUser`). Nunca conoce el modelo de usuario de Tenant. |
| Billing | Facturación y pagos: planes, suscripciones, checkouts, webhooks, invoices. Payments gestionado aquí, no como módulo separado. |
| Catalog | Catálogo de planes, features, quotas y overrides por tenant. Source of truth del modelo Plan. |
| Growth | Landing pública, registro de tenants, adquisición. |
| Operations | Health checks, colas Horizon, Railway infrastructure, configuración operativa. |
| Provisioning | Ciclo de vida del tenant: creación, suspensión, archivado, purga. |
| Settings | Configuración de plataforma: CentralSetting model, CentralBranding (logo, colores, nombre). |
| Support | Impersonation, broadcasts, notas de soporte. |

## Tenant

Scaffolding genérico del producto del cliente. **No contiene módulos de dominio específico.**

| Módulo | Responsabilidad |
|---|---|
| Access | Usuarios finales (guard `tenant`), roles, permisos, API keys, invitaciones, MFA. |
| Compliance | Auditoría de eventos de identidad, exportación de datos. |
| Experience | Branding, localización, landing builder. |
| Integrations | SMTP, futuras integraciones externas. |
| Workspace | Dashboard, equipo, notificaciones, usage overview. |

### Nota de alcance — módulos excluidos del core

**CRM, Documents, Forms, Automation, Reports** y cualquier otro módulo de dominio vertical **no forman parte del repositorio core de LaraShift**, bajo ninguna circunstancia — ni como módulos a construir, ni como scaffolding de referencia o ejemplo dentro del repo.

Razón: LaraShift es un framework reutilizable, no un producto SaaS. Cada producto que se construye sobre el framework implementa su propio dominio de negocio en su propio repositorio o capa de extensión. Si lógica vertical viviera en el core, cada actualización del framework arrastraría cambios de un negocio ajeno al producto real, acoplando los ciclos de release de framework y producto de forma permanente.

---

# 12. Comunicación entre módulos

Permitido:

- Actions públicas
- Contracts
- Domain Events
- Jobs

No permitido:

- acceder directamente a Models de otro módulo
- consultas SQL cruzadas
- dependencias circulares

**Esta regla aplica por igual entre módulos del mismo Bounded Context.** Por ejemplo, `Billing` no accede directamente a modelos de `Catalog`, ni `Provisioning` accede directamente a modelos de `Auth`, aunque todos sean módulos Central. Compartir Bounded Context no es excepción a la regla de aislamiento entre módulos.

---

# 13. UI

Stack oficial:

- Blade
- Livewire
- FluxUI
- TailwindCSS

No utilizar React, Vue o Inertia para el panel administrativo.

---

# 14. Testing

Toda funcionalidad debe incluir pruebas.

Como mínimo:

- Unit
- Feature
- Tenant Isolation — implementado obligatoriamente como `CrossTenantLeakTest` por cada módulo Tenant-aware, verificando explícitamente que un tenant no puede leer ni escribir datos de otro, incluyendo el escenario de conexión de base de datos reutilizada entre unidades de trabajo.

No marcar tareas como completas sin pruebas. Una tarea que toque datos aislados por tenant no se considera terminada sin su `CrossTenantLeakTest` correspondiente.

---

# 15. Identidad: Central vs Tenant

Central y Tenant son identidades completamente separadas, sin tabla ni modelo compartido:

- **Auth** (Central) gestiona autenticación de staff/plataforma vía guard `central`, modelo `CentralUser`. Nunca conoce ni referencia el modelo de usuario de Tenant.
- **Access** (Tenant) gestiona autenticación de usuarios finales del cliente vía guard `tenant`, modelo `User`. Nunca conoce ni referencia el modelo de usuario de Central.

Queda explícitamente prohibido unificar ambas identidades en una sola tabla `users` con un campo discriminador (`type` o similar). Si en el futuro se requiere SSO compartido entre Central y Tenant, se resuelve mediante un Contract en Platform (ej. `AuthenticatableIdentity`), nunca acoplando ambos módulos directamente.

---

# 16. Dependencias entre scopes

```
Central  ─┐
           ├──> Platform
Tenant   ─┘

Central <──contratos/eventos──> Tenant
```

- **Central → Platform**: permitido para Contracts, Data, Events, Tenancy, Security, UI, Observability.
- **Tenant → Platform**: permitido para Contracts, Data, Events, Tenancy, Security, UI, Observability.
- **Tenant → Central**: evitar dependencias directas. Usar Contracts o eventos.
- **Central → Tenant**: evitar dependencias directas. Usar Read Models, eventos o jobs.
- **Platform → Central/Tenant**: prohibido. Platform debe ser 100% independiente.

---

# 17. Inteligencia Artificial

Todo agente AI debe considerar este documento como la fuente de verdad para las decisiones arquitectónicas.

Todo agente AI debe además:

- respetar la clasificación Platform/Central/Tenant de la sección 11 sin excepción
- nunca introducir módulos de dominio de negocio dentro de Tenant
- aplicar el contrato `TenantAware` en todo Job que acceda a datos tenant-aware
- incluir `CrossTenantLeakTest` en todo módulo Tenant-aware nuevo

Si una implementación requiere modificar cualquiera de estas decisiones, primero debe proponerse un ADR antes de escribir código.
