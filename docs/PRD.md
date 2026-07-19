# LaraShift

**Versión:** 2.0 **Estado:** En desarrollo **Tipo:** Framework / Boilerplate SaaS Multitenant reutilizable **Stack:** Laravel · Livewire · FluxUI · PostgreSQL · Redis

---

# 1. Visión

LaraShift es un **framework/boilerplate reutilizable** para desarrollar aplicaciones SaaS multitenant modernas utilizando Laravel y una arquitectura de Monolito Modular con tres scopes: Platform, Central y Tenant.

Su objetivo es eliminar el trabajo repetitivo asociado a la construcción de la **infraestructura de plataforma** de un SaaS, proporcionando una base sólida, escalable y lista para producción sobre la cual se construyen productos de negocio vendibles.

LaraShift **no es un producto SaaS en sí mismo**. No contiene ni debe contener lógica de dominio de negocio (CRM, gestión documental, formularios, automatizaciones, o cualquier vertical específico). Esos módulos pertenecen exclusivamente a los productos que se construyen sobre el framework.

---

# 2. Problema

Construir un SaaS desde cero implica desarrollar repetidamente infraestructura de plataforma que no aporta valor directo al producto de negocio final:

- autenticación
- multitenancy
- billing
- gestión de usuarios
- roles y permisos
- feature flags
- aprovisionamiento
- auditoría
- configuración
- integraciones

La mayor parte del tiempo se invierte construyendo plataforma en lugar de funcionalidades de negocio propias del producto.

---

# 3. Objetivo

Reducir el tiempo necesario para construir aplicaciones SaaS proporcionando un framework listo para producción que permita a cada producto futuro enfocarse únicamente en su dominio de negocio, sin reconstruir infraestructura de plataforma en cada proyecto.

---

# 4. Público objetivo

LaraShift está dirigido a:

- Freelancers
- Startups
- Software Houses
- Equipos Laravel
- Empresas que desarrollan productos SaaS vendibles sobre una base común

No está diseñado para:

- CMS
- eCommerce
- Blogs
- Aplicaciones SPA puras
- Microservicios
- Ser usado directamente como producto final (siempre es una base sobre la que se construye)

---

# 5. Objetivos del producto

- Reducir tiempo de desarrollo de nuevos SaaS.
- Mantener una arquitectura consistente entre proyectos construidos sobre el framework.
- Facilitar mantenimiento y actualización del framework de forma independiente al producto.
- Escalar sin cambiar arquitectura.
- Favorecer reutilización total de la capa de plataforma.
- Simplificar el desarrollo con IA.
- Permitir que framework y producto evolucionen en ciclos de release independientes.

---

# 6. Principios

- Laravel First.
- Modular Monolith.
- Convention over Configuration.
- Simplicidad antes que abstracción.
- Producción antes que perfección.
- Arquitectura consistente.
- **Cero lógica de dominio de negocio en el core del framework.**

---

# 7. Alcance

LaraShift proporciona toda la infraestructura de plataforma de un SaaS.

Incluye:

- Multi-tenancy
- Autenticación (staff de plataforma y usuarios finales, como identidades separadas)
- Billing
- Gestión de usuarios y equipos
- Roles y permisos
- Feature flags
- Configuración
- Auditoría
- Notificaciones
- Aprovisionamiento
- Branding / white-labeling
- API Keys para integraciones del cliente
- Panel Central (administración)
- Panel Tenant (scaffolding)
- Landing pública

**No incluye lógica específica de negocio.** Ningún módulo de dominio (CRM, Documents, Forms, Automation, Reports, o cualquier vertical) forma parte del repositorio core. Cada producto implementa sus propios módulos de negocio sobre esta base.

---

# 8. Arquitectura

Arquitectura basada en:

- Modular Monolith
- Domain Oriented Modules
- Platform (infraestructura transversal)
- Event Driven cuando aporta valor
- PostgreSQL con Row Level Security
- Redis (cache, colas, locks, sesiones)
- Laravel Actions
- Livewire SSR
- Compatible con Laravel Octane desde el diseño (sin estado estático mutable, sin singletons con estado por request)

---

# 9. Contextos (Bounded Contexts)

## Platform

Infraestructura transversal reutilizable, sin reglas de negocio. Consumida por Central y Tenant, nunca al revés.

Responsable de:

- contratos compartidos (Contracts)
- eventos de integración (Events)
- primitivas de datos (Data)
- tenancy (contexto, RLS, middleware)
- seguridad (MFA, HMAC, API keys, rate limiting)
- UI base (layouts, componentes, navegación)
- observabilidad (audit, health)
- foundation (base controllers, helpers)

---

## Central

Administración de la plataforma SaaS. Contiene exclusivamente infraestructura de negocio del framework, no del producto final.

Responsable de:

- autenticación de staff
- billing y pagos
- catálogo de planes y features
- provisioning de tenants
- operaciones (health, colas, infraestructura)
- settings de plataforma
- soporte (impersonation, broadcasts)
- growth (landing pública, registro)

---

## Tenant

Aplicación del cliente. Contiene únicamente scaffolding genérico reutilizable por cualquier vertical.

Responsable de:

- usuarios finales, roles y API keys (Access)
- auditoría y exportación de datos (Compliance)
- branding y localización (Experience)
- integraciones SMTP (Integrations)
- dashboard, equipo y notificaciones (Workspace)

---

# 10. Clasificación oficial de módulos

## Platform

Infraestructura reutilizable, sin reglas de negocio. Consumida por Central y Tenant, nunca al revés.

| Módulo | Responsabilidad |
|---|---|
| Contracts | Interfaces compartidas (TenantContract, BillingProvider, FeatureResolver, etc.) |
| Data | Casts (MoneyCast), formatters, PlatformTenant DTO |
| Events | Eventos de integración (TenantProvisioned, SubscriptionCreated, etc.) |
| Foundation | Controller base, providers base |
| Observability | Audit Activity, HealthChecker |
| Security | HmacSigner, MfaService, ApiKeyHasher, TenantRateLimiter |
| Tenancy | BelongsToTenant trait, RLS bootstrapper, middleware tenant-aware |
| UI | Layouts, componentes Blade, DesignSystem, SidebarBuilder |

## Central

Administración de la plataforma SaaS. Ninguno de estos módulos accede directamente a modelos de otro módulo Central; la comunicación ocurre vía Actions públicas, Contracts o Events.

| Módulo | Responsabilidad |
|---|---|
| Auth | Autenticación de staff (guard `central`, modelo `CentralUser`). Nunca conoce el modelo de usuario de Tenant. |
| Billing | Facturación y pagos: planes, suscripciones, checkouts, webhooks, invoices. Payments integrado. |
| Catalog | Catálogo de planes, features, quotas y overrides. Source of truth del modelo Plan. |
| Growth | Landing pública, registro de tenants, adquisición. |
| Operations | Health checks, colas Horizon, Railway infraestructura, metrics. |
| Provisioning | Ciclo de vida del tenant: creación, suspensión, archivado, purga. |
| Settings | Configuración de plataforma: CentralSetting, CentralBranding. |
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

### 10.1 Nota de alcance — módulos de dominio excluidos

Módulos como CRM, Documents, Forms, Automation o Reports **no forman parte del repositorio core de LaraShift**, bajo ninguna circunstancia, ni siquiera como scaffolding de referencia o ejemplo. Pertenecen exclusivamente al producto que se construye sobre el framework.

Razón: si lógica de dominio vertical viviera en el core, cada actualización del framework arrastraría cambios de un negocio ajeno al producto real, acoplando los ciclos de release de framework y producto. Deben poder evolucionar de forma independiente.

---

# 11. Arquitectura UI

Tecnologías:

- Blade
- Livewire
- FluxUI
- TailwindCSS

No utiliza SPA.

Todo el rendering ocurre del lado del servidor.

---

# 12. Arquitectura Backend

Laravel organiza la aplicación mediante módulos con una estructura interna consistente:

```
Module/
├── Domain/
├── Application/
├── Infrastructure/
├── Interface/
├── Database/
└── Providers/
```

Cada módulo contiene:

- Models (en Domain)
- Actions (en Application)
- DTOs (en Application)
- Policies (en Domain)
- Queries (en Application)
- Events (en Domain)
- Jobs (en Application)
- Livewire (en Interface)
- Views (en Interface)
- Tests

Cada Action representa un caso de uso.

---

# 13. Seguridad

Toda funcionalidad Tenant debe garantizar:

- aislamiento de datos
- autorización
- autenticación
- auditoría

La seguridad es multicapa:

- Middleware
- Policies
- Scopes
- PostgreSQL Row Level Security

**Estrategia de multitenancy oficial:** Single Database + `tenant_id` + PostgreSQL Row Level Security (RLS). El contexto de tenant se propaga vía `TenantContext` (binding `scoped`, no `singleton`, para compatibilidad con Octane) y se aplica mediante `SET LOCAL` dentro de una transacción explícita. Todo Job en cola debe implementar el contrato `TenantAware` y re-hidratar su propio contexto de tenant al ejecutarse; nunca se asume herencia del contexto vía conexión reutilizada.

---

# 14. Escalabilidad

La arquitectura debe permitir:

- crecimiento de módulos
- crecimiento de tenants
- procesamiento asíncrono
- feature flags
- integraciones

Sin migrar a microservicios.

Redis es infraestructura obligatoria (no opcional) para cache, colas, locks y sesiones en producción y staging.

---

# 15. Principios de Ingeniería

El proyecto sigue estas reglas:

- SOLID cuando aporta valor.
- DRY sin sobre abstraer.
- KISS.
- YAGNI.
- Clean Code.
- Convenciones Laravel.

---

# 16. Objetivo para IA

Toda implementación realizada por un agente AI debe:

- respetar la arquitectura
- reutilizar código existente
- evitar duplicación
- mantener consistencia
- implementar pruebas
- seguir Coding Standards
- **nunca introducir lógica de dominio de negocio dentro del Tenant BC del core**

La IA actúa como un ingeniero del proyecto, no como un generador de código aislado.

---

# 17. Estado del proyecto

El desarrollo sigue un roadmap incremental dividido por sprints.

Cada sprint agrega una capacidad funcional manteniendo siempre el sistema en un estado estable y desplegable.

No se aceptan implementaciones que comprometan la arquitectura por acelerar una entrega.

---

# 18. Definición de éxito

LaraShift será exitoso cuando permita desarrollar un nuevo SaaS con autenticación, multitenancy, billing y panel administrativo en una fracción del tiempo requerido para construirlo desde cero, manteniendo una arquitectura consistente, escalable y preparada para producción — sin que el framework mismo contenga ni una línea de lógica de negocio vertical.
