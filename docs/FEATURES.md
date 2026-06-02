# Diseño Propuesto

## Jerarquía

```text
Feature
    ↓
PlanFeature
    ↓
TenantPlan
    ↓
TenantFeatureOverride
```

Resolución:

```text
Tenant Feature Access =
    Plan Features
    + Tenant Overrides
```

---

# Modelo Mental

Ejemplo:

```text
Plan Pro
- users.create
- inventory.manage
- reports.basic
```

Tenant:

```text
Plan = Pro

Override:
+ reports.advanced
- inventory.manage
```

Resultado:

```text
users.create ✅
reports.basic ✅
reports.advanced ✅
inventory.manage ❌
```

---

# Dominio

Dentro de:

```text
Central/Billing
```

o

```text
Central/Features
```

Prefiero:

```text
Central/Features
```

Porque feature management termina creciendo y no es solo billing.

---

# Tablas

## 1. features

Catálogo global.

```sql
features
```

```sql
id
key
name
description
module
is_active
created_at
updated_at
```

Ejemplo:

```text
crm.contacts
crm.pipeline
reports.basic
reports.advanced
api.access
branding.custom_domain
```

---

## 2. feature_groups

Agrupación visual/lógica.

No es obligatoria pero ayuda.

```sql
feature_groups
```

```sql
id
key
name
```

Ejemplo:

```text
CRM
Reports
Branding
API
```

---

## 3. feature_group_items

M:N.

```sql
feature_group_items
```

```sql
group_id
feature_id
```

---

## 4. plans

Central.

```sql
plans
```

Ya existe conceptualmente por billing. 

---

## 5. plan_features

M:N.

Base del plan.

```sql
plan_features
```

```sql
plan_id
feature_id
```

Índice:

```sql
(plan_id, feature_id)
```

---

## 6. tenant_plans

No asumir 1:1 eterno.

Hoy:

```text
1 tenant → 1 plan
```

Mañana:

```text
scheduled upgrade
trial
historical audit
```

Entonces:

```sql
tenant_plans
```

```sql
id
tenant_id
plan_id
starts_at
ends_at
is_active
```

Índices:

Tenant-scoped:

```sql
(tenant_id, is_active)
(tenant_id, plan_id)
```

Siguiendo estrategia de índices tenant-aware. 

---

## 7. tenant_feature_overrides

La pieza clave.

Permite:

* cortesías
* ventas custom
* soporte
* beta access
* suspensión parcial

Relacionado con *Overriding de Cuotas*. 

```sql
tenant_feature_overrides
```

```sql
id
tenant_id
feature_id
type
reason
expires_at
created_by
created_at
```

type:

```text
allow
deny
```

Índices:

```sql
(tenant_id, feature_id)
```

---

# Flujo de Resolución

Resolver feature:

```php
tenant()->canUseFeature('reports.advanced');
```

Internamente:

## Paso 1

Buscar override.

```text
deny → false
allow → true
```

---

## Paso 2

Sin override:

consultar plan.

---

## Paso 3

Cache.

Redis-first.

Consistente con quotas/cache priming. 

---

# API Tipo Spatie

Esto es lo que buscas emular.

## Tenant

```php
$tenant->assignPlan($plan);

$tenant->grantFeature('reports.advanced');

$tenant->revokeFeature('inventory.manage');

$tenant->hasFeature('reports.advanced');
```

---

## Plan

```php
$plan->giveFeatureTo('crm.contacts');

$plan->revokeFeature('crm.contacts');

$plan->hasFeature('crm.contacts');
```

---

## Feature

```php
Feature::findByKey('crm.contacts');
```

---

# Cache Layer

Crítico.

No hacer joins por request.

Cache:

```text
tenant:{id}:features
```

Payload:

```json
[
  "crm.contacts",
  "crm.pipeline",
  "reports.basic"
]
```

Warmup:

Middleware tenancy.

Consistente con cache priming. 

Invalidar cuando:

* cambia plan
* cambia override
* feature desactivada
* sync billing

---

# Implementación Laravel

Trait estilo Spatie.

## HasFeatures

```php
trait HasFeatures
{
    public function hasFeature(string $feature): bool
    {
        return app(FeatureResolver::class)
            ->forTenant($this)
            ->has($feature);
    }
}
```

---

# FeatureResolver

Action/Service único.

No meter lógica en modelos.

Consistente con patrón Action.  

```php
final readonly class ResolveTenantFeatureAction
{
    public function execute(
        Tenant $tenant,
        string $feature
    ): bool {
        //
    }
}
```

---

# Edge Cases

Aquí suele romperse.

## 1. Expiración

Override temporal.

Ejemplo:

```text
+ API access
30 días
```

`expires_at`

---

## 2. Feature retirada globalmente

Feature:

```text
is_active=false
```

Debe negar.

---

## 3. Downgrade

Tenant baja de plan.

Debe:

* invalidar cache
* disparar evento
* bloquear acceso

Nunca borrar overrides históricos.

---

## 4. Queue contamination

Jobs tenant-aware.

Resolver features sin tenancy inicializada es bug.

Consistente con Queue Isolation. 

---

# Testing Obligatorio

## Plan

```text
Plan → feature
```

---

## Override allow

```text
plan no incluye
override sí
```

---

## Override deny

```text
plan incluye
override niega
```

---

## Expiración

```text
expires_at pasado
```

---

## Isolation

Tenant A:

```text
reports.advanced
```

Tenant B:

```text
no acceso
```

404 cuando corresponda. 

---

# Lo que NO recomiendo

## Reusar `spatie/permission`

Tentador:

```php
tenant->givePermissionTo(...)
```

Problemas:

* mezcla IAM con billing
* semántica incorrecta
* auditoría confusa
* permisos de usuario ≠ features SaaS

`spatie/permission` sigue siendo para:

```text
roles
permissions
users
```

Features deben ser otro bounded context. 

La inspiración es Spatie.
No la reutilización literal.
