> **Objetivo**
>
> Este documento define las reglas obligatorias para implementar nuevas funcionalidades en SaaSiFy. Su propósito es mantener una arquitectura consistente, simple y mantenible, evitando deriva arquitectónica (Architecture Drift).
>
> **Nota de versión:** Esta revisión unifica el nombre del proyecto (SaaSiFy, sin referencias previas a "LaraShift"), renombra el Bounded Context "Central" a **Host** para evitar ambigüedad con la clasificación de módulos, actualiza la lista de módulos Tenant a scaffolding puro, y añade el mecanismo obligatorio de propagación de Tenant Context vía RLS, incluyendo su contrato para Jobs bajo Octane.

---

# Filosofía

SaaSiFy es un **framework/boilerplate reutilizable**, no un producto SaaS. La prioridad es:

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

✔ Identity conoce Identity

✘ Billing conoce Features

✘ Features conoce Identity internamente
```

Toda reutilización ocurre mediante módulos Shared.

**Esta regla aplica igual entre módulos Host (antes referidos como "Central"), no solo entre módulos Tenant.** `Billing` no accede directamente a modelos de `Plans`, ni `Features` accede directamente a modelos de `Tenants`, aunque ambos vivan en el mismo Bounded Context. La comunicación siempre ocurre vía Actions públicas, Contracts o Domain Events — nunca `Model::find()` cruzado entre módulos, sin importar si ambos son Host o ambos son Tenant.

---

## Host vs Tenant

Toda funcionalidad pertenece a exactamente uno de estos contextos.

### Host

Administra la plataforma SaaS. Es infraestructura del framework, no lógica de producto.

Módulos:

- Identity
- Tenants
- Provisioning
- Plans
- Features
- Billing
- Monitoring

---

### Tenant

Representa el punto de extensión sobre el cual cada producto construye su propio dominio de negocio. **El core de SaaSiFy solo provee scaffolding genérico aquí, nunca módulos de dominio específico.**

Módulos que sí pertenecen al core:

- Users
- Teams
- Branding
- API Keys

**Módulos que NO pertenecen al core bajo ninguna circunstancia:** CRM, Documents, Forms, Automation, Reports, o cualquier otro módulo de dominio vertical. Estos son responsabilidad exclusiva del producto que se construye sobre el framework. No se aceptan como "scaffolding de referencia" ni como ejemplo dentro del repositorio — un framework y el producto que lo consume deben poder evolucionar en ciclos de release independientes, y lógica de dominio en el core rompe esa independencia.

---

### Shared

Contiene infraestructura reutilizable.

Módulos:

- Notifications
- Media
- Search
- Audit
- Settings
- Integrations
- Authorization

Nunca contiene reglas de negocio.

---

# Estructura de módulos

Cada módulo debe seguir la misma estructura.

```
Module/

Actions/

DTOs/

Events/

Exceptions/

Http/
    Controllers/
    Livewire/
    Requests/

Jobs/

Listeners/

Models/

Policies/

Providers/

Queries/

Resources/

Routes/

Tests/
```

No agregar carpetas nuevas sin una justificación arquitectónica.

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

Consultas complejas deben vivir en Queries.

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

Las integraciones externas utilizan eventos separados.

Ejemplos:

```
PaymentWebhookReceived

EmailDelivered

CloudflareZoneCreated
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

Toda integración externa vive bajo:

```
Shared/Integrations
```

Ejemplo:

```
Stripe

PagueloFacil

Resend

Cloudflare
```

Cada proveedor implementa un contrato común.

Nunca llamar SDKs externos directamente desde una Action.

---

# Multi-Tenancy y Row Level Security

Esta sección es de cumplimiento obligatorio para cualquier módulo Tenant o Host que toque datos aislados por tenant. No es opcional ni queda a criterio del desarrollador.

## Estrategia oficial

Single Database + `tenant_id` + PostgreSQL Row Level Security (RLS). Ningún módulo implementa mecanismos propios de aislamiento (nada de scoping manual como único mecanismo, nada de `WHERE tenant_id = ?` disperso como capa de seguridad primaria).

## Tenant Context

El contexto de tenant vive en una clase `TenantContext`, registrada como binding `scoped()`, **nunca** `singleton()`.

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

### Patrón obligatorio del CrossTenantLeakTest

Todo archivo `*CrossTenantLeakTest.php` debe seguir esta estructura:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Shared\Support\CrossTenantLeakTest;

uses(CrossTenantLeakTest::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->setUpCrossTenantLeakTest();
});

it('prevents cross-tenant data leakage', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Cross-tenant RLS requires PostgreSQL');
    }

    $this->assertTenantBSeesNoDataFromA(function ($tenantA, $tenantB) {
        // Query que busca datos del tenant A estando en contexto de B
        return TenantBModel::where('tenant_id', $tenantA->id);
    });
});

it('resets tenant context between units of work', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('SET LOCAL reset requires PostgreSQL');
    }

    $this->assertNoLingeringTenantContext();
});
```

El trait `CrossTenantLeakTest` en `Shared/Support` provee:
- `setUpCrossTenantLeakTest()` — crea dos tenants (A y B) con datos de prueba
- `assertTenantBSeesNoDataFromA(callable)` — verifica que B no ve datos de A
- `assertNoLingeringTenantContext()` — verifica que no hay contexto residual (Octane)
- `simulateTenantContext(string $tenantId)` — hidrata contexto para la prueba

No se aceptan variaciones que omitan el escenario de conexión reutilizada.

---

# Identidad: Host vs Tenant

Host y Tenant tienen sistemas de autenticación completamente separados, cada uno con su propio guard de Laravel y su propio modelo:

- `Identity` (Host) autentica staff de la plataforma vía guard `host`, modelo `HostUser`. Nunca conoce ni referencia el modelo de usuario de Tenant.
- `Users` (Tenant) autentica usuarios finales del cliente vía guard `tenant`, modelo `TenantUser`. Nunca conoce ni referencia el modelo de usuario de Host.

Si en el futuro se requiere SSO compartido entre ambos, se resuelve mediante un Contract en Shared (ej. `AuthenticatableIdentity`), nunca acoplando ambos módulos directamente ni fusionando ambos modelos en una sola tabla con un campo `type`. Esta última práctica está explícitamente prohibida: rompe el aislamiento Host/Tenant a nivel de esquema.

---

# Seguridad

Toda funcionalidad Tenant debe validar:

- tenant activo
- autorización
- aislamiento de datos

Nunca confiar únicamente en middleware.

---

# Feature Flags

Las funcionalidades se habilitan mediante Features.

No escribir condiciones dispersas como:

```php
if ($tenant->plan === 'pro')
```

Siempre utilizar el sistema de Features.

---

# Dependencias

Dependencia permitida:

```
Shared

↑

Host

Tenant
```

Nunca:

```
Features → Identity

Billing → Plans (acceso directo a modelos)

Identity → Users
```

La comunicación entre módulos —**tanto Host-Host, Tenant-Tenant, como Host-Tenant**— ocurre siempre mediante:

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

Esto aplica también a la separación de módulos: no crear un módulo Host o Tenant nuevo si su única responsabilidad es envolver un módulo Shared existente sin lógica propia (ej. un módulo "Roles" en Tenant que solo delega en `Authorization` de Shared no se justifica como módulo independiente).

---

# Checklist de implementación

Antes de considerar una tarea terminada verificar:

- [ ] Pertenece al módulo correcto
- [ ] Respeta Host/Tenant/Shared
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
