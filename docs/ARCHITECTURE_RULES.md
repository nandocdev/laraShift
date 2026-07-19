> **Objetivo**
>
> Este documento define las reglas obligatorias para implementar nuevas funcionalidades en LaraShift. Su propósito es mantener una arquitectura consistente, simple y mantenible, evitando deriva arquitectónica (Architecture Drift).

---

# Filosofía

LaraShift es un **framework/boilerplate reutilizable**, no un producto SaaS. La prioridad es:

1. Simplicidad
2. Consistencia
3. Escalabilidad
4. Mantenibilidad
5. Cero lógica de dominio de negocio en el core

Nunca se debe introducir complejidad sin una necesidad real.

---

# Principios

## Modular Monolith First

Cada funcionalidad pertenece a un único módulo.

Los módulos son independientes.

No existe lógica compartida entre módulos de negocio.

```
✔ Billing conoce Billing
✔ Auth conoce Auth
✘ Billing conoce Catalog (acceso directo a modelos)
✘ Auth conoce Access (acceso directo a modelos)
```

Toda reutilización ocurre mediante módulos Platform.

**Esta regla aplica igual entre módulos Central, no solo entre módulos Tenant.** `Billing` no accede directamente a modelos de `Catalog`, ni `Provisioning` accede directamente a modelos de `Auth`, aunque todos vivan en el mismo Bounded Context. La comunicación siempre ocurre vía Actions públicas, Contracts o Domain Events — nunca `Model::find()` cruzado entre módulos, sin importar si ambos son Central o ambos son Tenant.

---

## Scopes

Toda funcionalidad pertenece a exactamente uno de estos scopes.

### Platform

Infraestructura transversal reutilizable. Independiente de Central y Tenant.

Módulos:

- Contracts
- Data
- Events
- Foundation
- Observability
- Security
- Tenancy
- UI

Nunca contiene reglas de negocio. No depende de Central ni de Tenant.

---

### Central

Operación de la plataforma SaaS. Es infraestructura del framework, no lógica de producto.

Módulos:

- Auth
- Billing
- Catalog
- Growth
- Operations
- Provisioning
- Settings
- Support

---

### Tenant

Representa el punto de extensión sobre el cual cada producto construye su propio dominio de negocio. **El core de LaraShift solo provee scaffolding genérico aquí, nunca módulos de dominio específico.**

Módulos que pertenecen al core:

- Access (usuarios, roles, API keys)
- Compliance (auditoría, exportación)
- Experience (branding, localización, landings)
- Integrations (SMTP)
- Workspace (dashboard, equipo, notificaciones)

**Módulos que NO pertenecen al core bajo ninguna circunstancia:** CRM, Documents, Forms, Automation, Reports, o cualquier otro módulo de dominio vertical. Estos son responsabilidad exclusiva del producto que se construye sobre el framework. No se aceptan como "scaffolding de referencia" ni como ejemplo dentro del repositorio — un framework y el producto que lo consume deben poder evolucionar en ciclos de release independientes, y lógica de dominio en el core rompe esa independencia.

---

# Estructura de módulos

Cada módulo complejo debe seguir la misma estructura de sub-capas:

```
Module/
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

Módulos simples pueden omitir sub-capas y usar una estructura plana. La decisión de usar sub-capas se justifica por complejidad real, no por costumbre.

---

# Actions

Las Actions son la unidad principal de negocio.

Toda lógica de negocio vive en Actions.

Nunca en:

- Controllers
- Livewire
- Commands
- Jobs

Formato obligatorio:

```php
final class CreateTenantAction
{
    public function execute(CreateTenantData $data): Result
}
```

Reglas:

- una responsabilidad
- pequeñas
- determinísticas
- reutilizables
- sin lógica HTTP

---

# DTOs

Las Actions reciben únicamente DTOs.

Nunca arrays.

Correcto:

```php
CreatePlanData
```

Incorrecto:

```php
array $data
Request $request
```

Los DTO representan datos válidos.

No contienen lógica.

---

# Controllers

Los Controllers coordinan.

No contienen reglas de negocio.

Responsabilidades:

- recibir Request
- construir DTO
- ejecutar Action
- devolver Response

Nada más.

---

# Livewire

Los componentes Livewire representan únicamente estado de interfaz.

Pueden:

- validar formularios
- abrir modales
- paginar
- ordenar
- llamar Actions

No pueden:

- crear modelos
- ejecutar consultas complejas
- contener reglas de negocio

---

# Models

Los Models representan persistencia.

No deben convertirse en modelos "inteligentes".

Evitar:

- reglas de negocio
- procesos
- integraciones

Permitir únicamente:

- relaciones
- scopes
- casts
- accessors simples

---

# Queries

Consultas complejas deben vivir en Queries (dentro de Application/Queries/).

Especialmente:

- dashboards
- analytics
- reportes
- tablas grandes

No escribir consultas de 50 líneas dentro de Livewire.

---

# Read Models

Cuando una consulta:

- une muchas tablas
- requiere agregaciones
- es utilizada frecuentemente

crear un Read Model.

No implementar CQRS completo.

Solo separar lectura pesada.

---

# Domain Services

Crear únicamente cuando varias Actions compartan reglas complejas.

No crear servicios por costumbre.

---

# Events

Los eventos representan hechos del dominio.

Ejemplo:

```
TenantCreated
SubscriptionActivated
UserInvited
```

No representan acciones.

Incorrecto:

```
SendWelcomeEmail
```

---

# Integration Events

Las integraciones externas utilizan eventos separados. Viven en Platform/Events.

Ejemplos:

```
PaymentWebhookReceived
TenantProvisioned
SubscriptionUpdated
```

Nunca mezclar con eventos del dominio.

---

# Jobs

Los Jobs ejecutan procesos asincrónicos.

No contienen lógica de negocio.

Únicamente llaman Actions.

Los Jobs deben ser:

- idempotentes
- reintentables
- pequeños
- **tenant-aware por contrato explícito** (ver sección "Multi-Tenancy y Row Level Security" más abajo)

Provisioning utiliza Jobs reanudables.

---

# Integraciones

Toda integración externa vive bajo el módulo que la necesita (Central/Billing/Infrastructure/Gateways/ para pasarelas de pago, Tenant/Integrations/ para SMTP, etc.).

Cada proveedor implementa un contrato común desde Platform/Contracts.

Nunca llamar SDKs externos directamente desde una Action sin pasar por una clase adapter.

---

# Multi-Tenancy y Row Level Security

Esta sección es de cumplimiento obligatorio para cualquier módulo Tenant o Central que toque datos aislados por tenant. No es opcional ni queda a criterio del desarrollador.

## Estrategia oficial

Single Database + `tenant_id` + PostgreSQL Row Level Security (RLS). Ningún módulo implementa mecanismos propios de aislamiento (nada de scoping manual como único mecanismo, nada de `WHERE tenant_id = ?` disperso como capa de seguridad primaria).

## Tenant Context

El contexto de tenant vive en Platform/Tenancy, registrada como binding `scoped()`, **nunca** `singleton()`.

Razón: con Octane, un binding `singleton()` sobrevive entre requests dentro del mismo worker, y puede filtrar el tenant de un request anterior al siguiente si no se limpia manualmente. `scoped()` se resetea automáticamente por request, sin depender de disciplina del desarrollador.

```php
$this->app->scoped(TenantContext::class);
```

## Propagación vía RLS

El tenant activo se aplica a nivel de conexión de base de datos mediante `SET LOCAL`, siempre dentro de una transacción explícita:

```php
DB::transaction(function () use ($tenantId, $next) {
    DB::statement('SET LOCAL app.tenant_id = ?', [$tenantId]);
    $next(...);
});
```

`SET LOCAL` (nunca `SET` a nivel de sesión) porque su valor se resetea automáticamente al hacer `COMMIT` o `ROLLBACK`. Esto es crítico bajo Octane y bajo cualquier pool de conexiones reutilizadas (PgBouncer en modo transaction/session incluido): si se usara `SET` a nivel de sesión, la siguiente unidad de trabajo que reutilice esa conexión heredaría el `tenant_id` anterior.

## Contrato obligatorio para Jobs

Todo Job en cola que acceda a datos de un tenant debe implementar el contrato `TenantAware`:

```php
interface TenantAware
{
    public function tenantId(): string;
}
```

Y debe declarar el middleware `RehydrateTenantContext` en su método `middleware()`. Un Job jamás asume el contexto de tenant heredado del request que lo despachó, ni de una conexión reutilizada. Si un Job no implementa `TenantAware`, debe fallar en tiempo de ejecución con una excepción explícita — nunca ejecutarse en silencio sin contexto de tenant.

## Test de aislamiento obligatorio

Todo módulo Tenant debe incluir un `CrossTenantLeakTest` como parte de su Definition of Done. Este test no es opcional y no se sustituye por un test genérico de "Tenant Isolation" a nivel de framework: cada módulo que persiste datos aislados por tenant debe probar explícitamente que un tenant nunca puede leer ni escribir datos de otro, incluyendo el escenario de conexión de base de datos reutilizada entre unidades de trabajo.

Una tarea que toque datos tenant-aware **no se considera terminada sin este test**, sin excepción.

---

# Identidad: Central vs Tenant

Central y Tenant tienen sistemas de autenticación completamente separados, cada uno con su propio guard de Laravel y su propio modelo:

- `Auth` (Central) autentica staff de la plataforma vía guard `central`, modelo `CentralUser`. Nunca conoce ni referencia el modelo de usuario de Tenant.
- `Access` (Tenant) autentica usuarios finales del cliente vía guard `tenant`, modelo `User`. Nunca conoce ni referencia el modelo de usuario de Central.

Si en el futuro se requiere SSO compartido entre ambos, se resuelve mediante un Contract en Platform (ej. `AuthenticatableIdentity`), nunca acoplando ambos módulos directamente ni fusionando ambos modelos en una sola tabla con un campo `type`. Esta última práctica está explícitamente prohibida: rompe el aislamiento Central/Tenant a nivel de esquema.

---

# Seguridad

Toda funcionalidad Tenant debe validar:

- tenant activo
- autorización
- aislamiento de datos

Nunca confiar únicamente en middleware.

---

# Feature Flags

Las funcionalidades se habilitan mediante Features (Central/Catalog).

No escribir condiciones dispersas como:

```php
if ($tenant->plan === 'pro')
```

Siempre utilizar el sistema de Features.

---

# Dependencias

Dependencia permitida:

```
Platform

↑

Central

Tenant
```

Nunca:

```
Features → Auth (acceso directo)
Billing → Catalog (acceso directo a modelos)
Auth → Access (cruce Central-Tenant)
```

La comunicación entre módulos —**tanto Central-Central, Tenant-Tenant, como Central-Tenant**— ocurre siempre mediante:

- Actions públicas
- Events
- Contracts

Nunca acceso directo a Eloquent Models de otro módulo, sin importar si ambos módulos comparten Bounded Context.

---

# Testing

Toda nueva funcionalidad debe incluir pruebas.

Mínimo:

- caso exitoso
- validación
- permisos
- aislamiento tenant (`CrossTenantLeakTest` para todo módulo Tenant-aware)
- caso de error

No marcar una tarea como completada sin pruebas.

---

# Simplicidad

Antes de agregar:

- Services
- Repositories
- Interfaces
- Traits
- Abstracciones

preguntar:

> ¿Existe una necesidad real o estoy diseñando para un problema hipotético?

La respuesta normalmente será:

"No."

Esto aplica también a la separación de módulos: no crear un módulo nuevo si su única responsabilidad es envolver funcionalidad existente sin lógica propia.

---

# Checklist de implementación

Antes de considerar una tarea terminada verificar:

- [ ] Pertenece al módulo correcto
- [ ] Respeta Platform/Central/Tenant
- [ ] La lógica está en Actions
- [ ] Usa DTOs
- [ ] No duplica lógica existente
- [ ] No introduce dependencias circulares
- [ ] No accede directamente a Models de otro módulo (incluyendo módulos del mismo Bounded Context)
- [ ] Si es Tenant-aware: implementa `TenantAware` en sus Jobs y aplica RLS vía `TenantContext` scoped
- [ ] Si toca datos aislados por tenant: incluye `CrossTenantLeakTest`
- [ ] Incluye pruebas
- [ ] Respeta Coding Standards
- [ ] Documentación actualizada
- [ ] No introduce lógica de dominio de negocio en el Tenant BC del core

---

# Regla de Oro

La arquitectura debe evolucionar únicamente cuando simplifique el sistema.

Nunca agregar capas, patrones o abstracciones "por si acaso".

Cada línea de código debe justificar su existencia.
