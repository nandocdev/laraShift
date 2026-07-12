# SaaSiFy

**Versión:** 1.1 **Estado:** En desarrollo **Tipo:** Framework / Boilerplate SaaS Multitenant reutilizable **Stack:** Laravel · Livewire · FluxUI · PostgreSQL · Redis

> **Nota de versión:** Esta revisión corrige una inconsistencia de nombre (el proyecto se referenciaba también como "LaraShift" en documentación de soporte) y reestructura el Bounded Context Tenant para eliminar módulos de dominio específico que no corresponden a un framework reutilizable. Ver sección 10.1.

---

# 1. Visión

SaaSiFy es un **framework/boilerplate reutilizable** para desarrollar aplicaciones SaaS multitenant modernas utilizando Laravel y una arquitectura de Monolito Modular.

Su objetivo es eliminar el trabajo repetitivo asociado a la construcción de la **infraestructura de plataforma** de un SaaS, proporcionando una base sólida, escalable y lista para producción sobre la cual se construyen productos de negocio vendibles.

SaaSiFy **no es un producto SaaS en sí mismo**. No contiene ni debe contener lógica de dominio de negocio (CRM, gestión documental, formularios, automatizaciones, o cualquier vertical específico). Esos módulos pertenecen exclusivamente a los productos que se construyen sobre el framework.

No pretende ser un framework adicional sobre Laravel, sino una arquitectura de referencia con componentes reutilizables y procesos bien definidos.

---

# 2. Problema

Construir un SaaS desde cero implica desarrollar repetidamente infraestructura de plataforma que no aporta valor directo al producto de negocio final:

- autenticación
- multitenancy
- billing
- gestión de usuarios
- roles y permisos
- feature flags
- onboarding
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

SaaSiFy está dirigido a:

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

SaaSiFy proporciona toda la infraestructura de plataforma de un SaaS.

Incluye:

- Multi-tenancy
- Autenticación (staff de plataforma y usuarios finales, como identidades separadas)
- Billing
- Gestión de usuarios y equipos
- Roles y permisos (contratos genéricos, vía Authorization)
- Feature Flags
- Configuración
- Auditoría
- Notificaciones
- Aprovisionamiento
- Branding / white-labeling
- API Keys para integraciones del cliente
- Panel Host
- Panel Tenant (scaffolding)
- Landing pública

**No incluye lógica específica de negocio.** Ningún módulo de dominio (CRM, Documents, Forms, Automation, Reports, o cualquier vertical) forma parte del repositorio core. Cada producto implementa sus propios módulos de negocio sobre esta base.

---

# 8. Arquitectura

Arquitectura basada en:

- Modular Monolith
- Domain Oriented Modules
- Shared Infrastructure
- Event Driven cuando aporta valor
- PostgreSQL con Row Level Security
- Redis (cache, colas, locks, sesiones)
- Laravel Actions
- Livewire SSR
- Compatible con Laravel Octane desde el diseño (sin estado estático mutable, sin singletons con estado por request)

---

# 9. Contextos (Bounded Contexts)

## Public

Landing pública.

Responsable de:

- marketing
- registro
- documentación
- precios
- contacto

---

## Host (antes "Central")

Administración de la plataforma. Contiene exclusivamente infraestructura de negocio del framework, no del producto final.

Responsable de:

- identidad de staff/plataforma
- tenants
- billing
- provisioning
- monitoring
- soporte
- analytics
- features

---

## Tenant

Aplicación del cliente. Contiene únicamente scaffolding genérico reutilizable por cualquier vertical.

Responsable de:

- usuarios finales y equipos
- branding / personalización
- API keys de integración
- el punto de extensión donde cada producto añade sus propios módulos de negocio

---

# 10. Clasificación oficial de módulos

## Shared

Infraestructura reutilizable, sin reglas de negocio. Consumida por Host y Tenant, nunca al revés.

|Módulo|Responsabilidad|
|---|---|
|Audit|Registro inmutable de eventos de dominio|
|Notifications|Envío multicanal desacoplado del proveedor|
|Integrations|Contratos comunes para proveedores externos (Stripe, PagueloFacil, Resend, Cloudflare)|
|Media|Almacenamiento y gestión de archivos, con aislamiento por tenant|
|Search|Abstracción de búsqueda, motor intercambiable|
|Settings|Configuración jerárquica (platform-level y tenant-level)|
|Authorization|Contratos de permisos/roles reutilizables (Policies base, gates genéricos)|

## Host

Administración de la plataforma SaaS. Ninguno de estos módulos accede directamente a modelos de otro módulo Host; la comunicación ocurre vía Actions públicas, Contracts o Events.

|Módulo|Responsabilidad|
|---|---|
|Identity|Autenticación y autorización de staff/Host (guard `host`, modelo `HostUser`). Nunca conoce el modelo de usuario de Tenant.|
|Tenants|Ciclo de vida del tenant: creación, suspensión, reanudación, eliminación, estado|
|Provisioning|Orquestación de creación de tenant: seed inicial, jobs reanudables, configuración de dominio|
|Plans|Catálogo de planes y límites|
|Features|Motor de feature flags por tenant/plan|
|Billing|Facturación y pagos (ciclos de facturación, invoices, métodos de pago, estado de cobro)|
|Monitoring|Telemetría de uso por tenant y salud del sistema|

## Tenant

Scaffolding genérico del producto del cliente. **No contiene módulos de dominio específico.**

|Módulo|Responsabilidad|
|---|---|
|Users|Usuarios finales del tenant (guard `tenant`, modelo `TenantUser`). Nunca conoce el modelo de usuario de Host.|
|Teams|Agrupación de usuarios dentro de un tenant (workspaces)|
|Branding|Personalización visual: logo, colores, dominio custom|
|API Keys|Credenciales que el tenant genera para integrar su propio ecosistema externo|

### 10.1 Nota de alcance — módulos de dominio excluidos

Módulos como CRM, Documents, Forms, Automation o Reports **no forman parte del repositorio core de SaaSiFy**, bajo ninguna circunstancia, ni siquiera como scaffolding de referencia o ejemplo. Pertenecen exclusivamente al producto que se construye sobre el framework.

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

Laravel organiza la aplicación mediante módulos.

Cada módulo contiene:

- Models
- Actions
- DTOs
- Policies
- Queries
- Events
- Jobs
- Livewire
- Views
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

**Estrategia de multitenancy oficial:** Single Database + `tenant_id` + PostgreSQL Row Level Security (RLS). El contexto de tenant se propaga vía `TenantContext` (binding `scoped`, no `singleton`, para compatibilidad con Octane) y se aplica mediante `SET LOCAL` dentro de una transacción explícita. Todo Job en cola debe implementar el contrato `TenantAware` y re-hidratar su propio contexto de tenant al ejecutarse; nunca se asume herencia del contexto vía conexión reutilizada. El detalle técnico completo de este mecanismo vive en el ADR correspondiente, no en este documento.

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

SaaSiFy será exitoso cuando permita desarrollar un nuevo SaaS con autenticación, multitenancy, billing y panel administrativo en una fracción del tiempo requerido para construirlo desde cero, manteniendo una arquitectura consistente, escalable y preparada para producción — sin que el framework mismo contenga ni una línea de lógica de negocio vertical.