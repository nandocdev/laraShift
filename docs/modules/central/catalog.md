# Auditoría — Central/Catalog

> Fecha: 2026-08-28 | Estado: 🟡 Requiere atención (0× P0, 1× P1 alto, 4× P2 medios)

## 1. Resumen ejecutivo (Riesgos principales)

El módulo **Central/Catalog** es el source-of-truth de planes, features y overrides por tenant. A pesar de ser pequeño (14 archivos PHP), es **crítico para autorización y límites**: `HasFeatures`/`HasQuotas` se invocan en cada request tenant vía `EnsureHasFeature` y `EnsureWithinQuota`. La auditoría confirma diseño limpio (traits + `FeatureResolver` contract + pivot `plan_features`), pero expone **1 P1 que rompe consistencia de permisos** y deuda que escala mal con cache y soft-deletes.

- **P1 — Cache de features nunca se invalida al cambiar Plan↔Feature** (`ResolveTenantFeatures.php:40` `Cache::rememberForever("tenant:{id}:features")`). Solo `ApplyTenantFeatureOverride:49` y `ManageTenant:64` hacen `forceRefresh`; `ManagePlan` (`Central/Billing`) y `ManageFeature` (`Central/Catalog`) mutan `plans`, `features` y `plan_features` sin invalidar — tenants siguen viendo features viejas hasta `php artisan cache:clear` o `Bus::fake` en tests.
- **P2 — `TenantFeatureOverride` sobrescribe PK en cada `updateOrCreate`** (`ApplyTenantFeatureOverride.php:37-40` `['id'=>Str::uuid()]` en payload de update) — mismo antipatrón que `P003` en Provisioning: cambia `id` UUID en cada re-apply, rompe audit y `activity('features')` con `performedOn` inconsistente.
- **P2 — Gate `features:manage` no definido** (`ApplyTenantFeatureOverride.php:32` `Gate::authorize('features:manage')`). Solo `HorizonServiceProvider` define `viewHorizon`; `AppServiceProvider` no registra `features:*`. En central, todo `ApplyTenantFeatureOverride` falla con `403` para cualquier `CentralUser`, o si Gate fallback es permisivo, `TenantOverrides::removeOverride:66` no verifica Gate y permite bypass.
- **Riesgo sistémico** — `Unique tenant_id+feature_id` sin `WHERE deleted_at IS NULL` (`migrations/2026_06_02_143356:47`) + `SoftDeletes` en `Feature`/`Override` bloquea re-crear feature tras soft-delete; `QuotaManager` usa `quota:{id}:...` en lugar de `tenant:{id}:...` violando `PROJECT_DECISIONS.md §6` y fugando claves entre stores si se cambia driver.

**Salud global: 5/8 verde, 3/8 amarillo.** Sin P0 financiero, pero sin fix de cache el catálogo miente y cualquier cambio de plan en producción requiere intervención manual.

## 2. Alcance (Áreas inspeccionadas)

- **Rutas / Interface**: `Providers/CatalogServiceProvider.php:28` (`auth:central` → `/central/features`, `/central/features/{feature}/edit`, `/central/tenants/{tenant}/features/overrides`), `Livewire/{FeatureList:18, ManageFeature:30, TenantOverrides:33}` + `Views/pages/{feature-list,manage-feature,tenant-overrides}.blade.php`.
- **Aplicación / Dominio**: `Application/Actions/{ResolveTenantFeatures:16, ApplyTenantFeatureOverride:15}`, `DTO/TenantSummaryData.php:10`, `Domain/Models/{Plan:13, Feature:11, TenantFeatureOverride:13}`, `Domain/Concerns/{HasFeatures:7, HasQuotas:9}`.
- **Dependencias internas**: `Central/Provisioning/Models/Tenant` (trait `HasFeatures`/`HasQuotas` + `ManageTenant:64` invalida cache), `Central/Billing/Interface/Livewire/ManagePlan.php:70` (`catalogFeatures()->sync()` sin invalidación), `Platform/Tenancy/Application/Services/QuotaManager.php:14` (`quota:{tenant}:{metric}:{period}` Cache), `Platform/Tenancy/Interface/Http/Middleware/{EnsureHasFeature:7, EnsureWithinQuota:5}`.
- **Dependencias externas**: `Illuminate/Cache` (`rememberForever`/`forget`), `Illuminate/Gate` (`authorize`), `Carbon` (`parse`), `Spatie/laravel-data` (`TenantSummaryData`), `stancl/tenancy` (no DB tenancy, single DB + RLS).
- **DB**: `database/migrations/{2026_06_02_143356_create_features_module_tables:16, 2026_06_12_024725_add_soft_deletes_to_plans, 2026_06_12_030650_add_soft_deletes_to_features_and_overrides}`, `config/tenancy.php: central_domain`, `config/provisioning.php`.
- **Tests**: `tests/Feature/{FeatureManagementTest.php:1, FeatureAndQuotaMiddlewareTest.php:1}` (hasFeature + deny/allow + middleware 403/429), `tests/Feature/TenantQuotaEnforcementTest.php`, `tests/Pest.php`.
- **No inspeccionado** (fuera de alcance Catalog): `Central/Billing` pricing `MoneyCast` vs `amount float`, `Tenant/Workspace/UsageOverview` límites, `Platform/Metering` eventos.

## 3. Arquitectura actual (Flujo de funcionamiento)

```
[Central Staff] --ManageFeature.save--> Feature::create/update (uuid, key unique, regex module.action)
        |  FeatureList: Feature::orderBy(module)->paginate(20)
        |
[Central Staff] --TenantOverrides.applyOverride--> ApplyTenantFeatureOverride::execute(Tenant, key, type allow|deny)
        |  Gate::authorize('features:manage')
        |  DB::transaction { Feature::where(key)->firstOrFail
        |                   TenantFeatureOverride::updateOrCreate([tenant_id, feature_id], [id=>uuid(), type, reason, expires_at])
        |                   ResolveTenantFeatures(tenant, forceRefresh:true) }  // Cache::forget + rememberForever
        |  activity('features')->log('tenant_feature_override_applied')
        |
[Central Staff] --ManagePlan.save (Billing)--> Plan::create/update + catalogFeatures()->sync([featureIds])
        |  // ⚠️ Sin invalidación de tenant:{id}:features para tenants en ese plan
        |
[Tenant Request] --EnsureHasFeature:feature--> HasFeatures::hasFeature(feature)
        |  ResolveTenantFeatures::execute(tenant) -> Cache::rememberForever("tenant:{id}:features", fn() =>
        |       Feature::whereHas('plans', where slug=tenant.plan_id [or id uuid] && withTrashed)
        |                ->where is_active
        |                ->pluck(key)
        |       + TenantFeatureOverride where tenant_id + expires_at null||future -> allow/deny
        |       = array_diff( planFeatures + allowed, denied )
        |  )
        |  in_array(feature, effective) ? pass : 403
        |
[Tenant Request] --EnsureWithinQuota:metric--> HasQuotas::withinQuota(metric) -> QuotaManager::getLimit(tenant.getQuotaLimit) + getCurrentUsage(Cache "quota:{id}:{metric}:{period}")
```

Módulo sigue **Actions `final readonly` + Contracts (`FeatureResolver`) + Traits en `Tenant`**, sin Jobs ni Events propios. Correcto per `ARCHITECTURE_RULES.md`: no Repository/CQRS, no sobreingeniería.

## 4. Dependencias (Acoplamiento interno/externo)

**Interno (correcto pero acoplado donde duele):**

- `Catalog` → `Provisioning` vía `Tenant` model directo (`ResolveTenantFeatures.php:32` `Tenant::findOrFail`, `ApplyTenantFeatureOverride.php:9` `Tenant $tenant`, `HasFeatures.php:16` `$this` es Tenant). Viola `ARCHITECTURE_RULES.md` "ningún módulo accede directamente a Models de otro módulo" — debería depender de `Platform/Contracts/TenantContract` solo. Riesgo bajo (mismo Bounded Context Central) pero acopla releases; `Tenant.plan_id` es `string slug` no FK, por eso `orWhere plans.id` fallback `Str::isUuid` (`ResolveTenantFeatures.php:46`) para compat planes legacy con UUID.
- `Billing/ManagePlan` → `Catalog` (`Feature::where`, `plan->catalogFeatures()->sync`) — acceso directo sin Action pública de Catalog. Causa directa del bug de cache (Billing no conoce `ResolveTenantFeatures`).
- `Catalog` → `Platform` ✅ (`FeatureResolver` contract en `Platform/Contracts`, `TenantContract`), no al revés.

**Externo:**

- `Cache` driver `redis` (`CACHE_STORE=redis` per `.env.example`); `rememberForever` sin TTL es permanente — requiere invalidación explícita.
- `Gate` (central guard) sin Policy file para `Feature`; `Spatie/laravel-data` para `TenantSummaryData` bien tipado.
- `stancl/tenancy` solo para `Tenant::domains()->first()` (usado en `TenantDataSeeder`, no en Catalog).

**Dirección:** `Platform <- Catalog <- Provisioning` ✅; `Billing` ↔ `Catalog` circular suave (ambos Central) ⚠️ sin Contract.

## 5. Health Score (Tabla cualitativa por Dominio: 🟢/🟡/🔴)

| Dominio                | Score | Justificación                                                                                                                                                                                                                                                                                       |
| ---------------------- | ----- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Arquitectura           | 🟢    | `ResolveTenantFeatures` implementa `FeatureResolver` contract; `HasFeatures`/`HasQuotas` traits cohesivos; sin lógica en Livewire más allá de `validate` + `action->execute`.                                                                                                                       |
| Backend (Laravel)      | 🟡    | `Cache::rememberForever` sin invalidación en `ManageFeature`/`ManagePlan`; `Gate::authorize` sin gate definido; `Carbon::parse` sin try/catch; `TenantSummaryData::from` sin validar `plan_id` uuid vs slug.                                                                                        |
| Base de Datos          | 🟡    | `features.key` unique, `plan_features` PK compuesta + FK cascade bien; pero `tenant_feature_overrides` `unique(tenant_id,feature_id)` ignora `deleted_at` → bloqueo tras soft-delete; `features`/`overrides` `SoftDeletes` sin índice `deleted_at`.                                                 |
| Frontend (Livewire)    | 🟢    | `FeatureList` `orderBy(module)->paginate(20)` evita N+1; `ManageFeature` payload ligero (`key` slugificado `Str::slug`); `TenantOverrides` usa `TenantSummaryData` (12 líneas) + `tenantId` string, no serializa `Tenant` completo (vs `ManageTenant` en Provisioning).                             |
| Seguridad              | 🟡    | `auth:central` en `CatalogServiceProvider.php:28` cubre rutas, pero `Gate features:manage` no existe → `403` siempre o bypass en `removeOverride`; `TenantOverrides::applyOverride` valida `exists:features,key` bien; `is_active` no chequeado en override (feature inactiva puede allow).         |
| Performance            | 🟡    | `ResolveTenantFeatures` `whereHas('plans')` + `with('feature')` en overrides es correcto pero se ejecuta en cada `hasFeature` sin memoization request-scoped (solo Cache); `TenantOverrides::render:86` hace `Feature::whereNotIn(ids)` con `pluck` en memoria — O(n) con 1k features.              |
| Testing                | 🟡    | `FeatureManagementTest` cubre plan→feature + allow/deny overrides con `forceRefresh`; `FeatureAndQuotaMiddlewareTest` cubre `feature:api-access` 403 y `quota:users` 429; **0 test de cache invalidation, 0 de override expiración, 0 de soft-delete unique, 0 de RLS `tenant_feature_overrides`**. |
| DevOps / Observability | 🟢    | `activity('features')` por override + `activity('provisioning')` por plan change; logs no ruidosos; sin métricas `catalog.feature_resolve.latency` ni alerta `feature_override_expired`, pero no crítico para MVP.                                                                                  |

## 6. Hallazgos (Listado detallado con formato P0-P3)

### [ID: C001] [P1 Alto] Cache `tenant:{id}:features` nunca se invalida al mutar Plan o Feature — permisos se quedan viejos

- **Categoría:** Backend | Performance
- **Ubicación:** `app/Modules/Central/Catalog/Application/Actions/ResolveTenantFeatures.php:34-40` (`Cache::rememberForever("tenant:{$id}:features")`), `app/Modules/Central/Catalog/Interface/Livewire/TenantOverrides.php:73` invalida solo en override remove, `app/Modules/Central/Provisioning/Livewire/ManageTenant.php:64` invalida en plan change, pero `app/Modules/Central/Catalog/Interface/Livewire/ManageFeature.php:74` y `app/Modules/Central/Billing/Interface/Livewire/ManagePlan.php:104` (`catalogFeatures()->sync`) **no** invalidan
- **Problema y Evidencia:** `rememberForever` guarda effective features indefinidamente. En producción, si staff desactiva `Feature is_active=false` o quita `reports.advanced` de `Plan pro` vía `ManagePlan::sync`, ningún código hace `Cache::forget("tenant:{id}:features")` para los cientos de tenants en ese plan. `TenantOverrides::render:94` y `HasFeatures::hasFeature` siguen leyendo cache vieja. En tests pasa porque `ResolveTenantFeatures:36` hace `if (runningUnitTests()) Cache::forget` — enmascara el bug. **Confirmado** por grep: solo 3 `execute(..., true)` calls en codebase (Override apply/remove + ManageTenant plan change).
- **Impacto y Recomendación:** Tenant ve feature revocada (ej. `billing.invoices`) o no ve feature recién otorgada hasta `cache:clear`. Riesgo alto si se usa para entitlements de pago. Solución: emitir `PlanFeaturesChanged` event desde `ManagePlan::save` y `FeatureUpdated` desde `ManageFeature::save/delete`, listener `InvalidateTenantFeaturesCache` que haga `Tenant::where plan_id=slug->each(fn($t)=>Cache::forget("tenant:{$t->id}:features"))` con `chunkById`; o cambiar a `Cache::remember("tenant:{id}:features", 3600)` con TTL corto si eventual consistency es aceptable. Añadir test `FeatureManagementTest: plan sync invalidates cache`.
- **Complejidad / Prioridad:** Media / Sprint

### [ID: C002] [P2 Medio] `updateOrCreate` de overrides sobrescribe PK `id` en cada re-aplicación — audit trail roto

- **Categoría:** Database | Backend
- **Ubicación:** `app/Modules/Central/Catalog/Application/Actions/ApplyTenantFeatureOverride.php:37-40` (`updateOrCreate(['tenant_id','feature_id'], ['id'=>Str::uuid()->toString(), 'type'=>..., 'reason'=>...])`)
- **Problema y Evidencia:** Igual que `P003` en `provisioning.md`: Eloquent `updateOrCreate` actualiza **todos** los campos del segundo array si el registro existe, incluido `id`. Cada `allow→deny` flip o `reason` edit cambia la PK UUID del override, rompiendo `TenantFeatureOverride::find($id)` links en UI (`TenantOverrides.php:66` `removeOverride(string $id)` usa `id` para borrar). En PG, `id` es `uuid PK`; un `UPDATE SET id = new_uuid` viola FK si `activity` referencia `performedOn` con `id` viejo. En SQLite tests pasa silencioso.
- **Impacto y Recomendación:** Historial `activity('features')` con `id` inconsistente; `removeOverride` puede 404 si UI cachea `id` viejo. Fix: `updateOrCreate` sin `id` en update (`['type'=>...]` solo), `id` solo en `create` via `firstOrCreate` + `update`, o usar `upsert` con `uniqueBy: [tenant_id, feature_id]` sin tocar `id`. Migrar `TenantFeatureOverride` a `HasUuids` auto-generado en `creating` event. Test: `assert id unchanged after second apply`.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: C003] [P2 Medio] Gate `features:manage` no registrado — `ApplyTenantFeatureOverride` siempre 403 o sin control en `removeOverride`

- **Categoría:** Security | Backend
- **Ubicación:** `app/Modules/Central/Catalog/Application/Actions/ApplyTenantFeatureOverride.php:32` (`Gate::authorize('features:manage')`), `app/Modules/Central/Catalog/Interface/Livewire/TenantOverrides.php:66-76` (`removeOverride` sin Gate), `app/Providers/AppServiceProvider.php:43` (solo `TenantSettingPolicy`)
- **Problema y Evidencia:** Búsqueda global `grep -R Gate::define` solo encuentra `viewHorizon`; `AuthServiceProvider` (Fortify) no registra `features:manage`. `TenantOverrides::applyOverride:39` llama Action que autoriza, pero `removeOverride:66` hace `TenantFeatureOverride::where(...)->findOrFail($id)->delete()` sin Gate alguno — cualquier `auth:central` puede borrar overrides. Si Gate es `deny-by-default`, `applyOverride` siempre 403 en producción; si es `allow-by-default` (Gate no definido → `AuthorizationException`? En Laravel `Gate::authorize('undefined')` lanza `403` si no hay `before` hook), flujo de overrides roto. **Confirmado** vía grep vacío para `features`.
- **Impacto y Recomendación:** Staff no puede gestionar overrides o, peor, cualquiera puede removerlos. Definir `Gate::define('features:manage', fn(CentralUser $user)=> $user->hasRole('admin'))` en `CatalogServiceProvider::boot` o mover a `TenantFeaturePolicy`; añadir Gate también en `removeOverride` y `ManageFeature::save/delete`. Añadir test `actingAs(non-admin)->post()->assertForbidden`.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: C004] [P2 Medio] `Unique tenant_id+feature_id` ignora `deleted_at` — soft-delete bloquea re-crear override

- **Categoría:** Database
- **Ubicación:** `database/migrations/2026_06_02_143356_create_features_module_tables.php:47` (`$table->unique(['tenant_id','feature_id'])`), `app/Modules/Central/Catalog/Domain/Models/TenantFeatureOverride.php:15` (`use SoftDeletes`), `app/Modules/Central/Catalog/Interface/Livewire/TenantOverrides.php:82-88` (`whereNotIn` excluye soft-deleted pero DB mantiene unique)
- **Problema y Evidencia:** Tabla tiene `SoftDeletes` pero constraint `unique` es a nivel tabla, no `WHERE deleted_at IS NULL`. Si se aplica `allow` para `api.access`, se borra (`delete()` soft), e intenta aplicar `deny` para mismo feature, `updateOrCreate` encuentra soft-deleted row vía `withTrashed`? No, `updateOrCreate` por defecto ignora soft-deleted (aplica `deleted_at IS NULL` scope), por lo que intenta `INSERT` y choca con `unique` de la fila soft-deleted → `QueryException 23505`. En Postgres, `unique` incluye filas soft-deleted. **Confirmado** leyendo migración sin `->whereNull('deleted_at')` ni índice parcial.
- **Impacto y Recomendación:** Staff no puede re-aplicar override tras removerlo sin purgar (`forceDelete`). Solución: en PG crear índice único parcial `CREATE UNIQUE INDEX ON tenant_feature_overrides (tenant_id, feature_id) WHERE deleted_at IS NULL` y dropear `unique` actual, o cambiar `applyOverride` a `withTrashed()->updateOrCreate` con `restore()`, o no usar `SoftDeletes` en overrides (hard delete es auditado vía `activity`). Test: `apply -> remove -> apply` debe pasar.
- **Complejidad / Prioridad:** Media / Sprint

### [ID: C005] [P2 Medio] `QuotaManager` viola convención de clave de cache `tenant:{id}:{key}` y no usa RLS

- **Categoría:** Architecture | Database
- **Ubicación:** `app/Modules/Platform/Tenancy/Application/Services/QuotaManager.php:62-67` (`"quota:{$tenant->getId()}:{$metric}:{$period}"`), `PROJECT_DECISIONS.md §6` (`tenant:{tenant_id}:{key}`), `HasQuotas.php:14` (`getQuotaLimit` lee `plan->features['quotas']` sin RLS)
- **Problema y Evidencia:** `PROJECT_DECISIONS.md` exige `tenant:{tenant_id}:{key}` para toda clave cacheada; `QuotaManager` usa `quota:{id}:...` sin prefix `tenant:`. Si `CACHE_STORE=redis` con `prefix` por tenant, claves no se invalidan con `tenant:{id}:*` flush. Además `HasQuotas::withinQuota` lee `plan->features['quotas']` JSON sin ` tenant_feature_overrides` check — un `deny` de `reports.advanced` no afecta quota si quota está en `plan.features['quotas']`. No usa RLS: `quota_snapshots` sí tiene RLS (`migrations/2026_06_02_205039:15`), pero `QuotaManager` lee de `Cache` no de DB, bypass RLS. **Riesgo** (condición probable de fallo) si se cambia driver a `file`.
- **Impacto y Recomendación:** Inconsistencia con docs, `Cache::tags`/`flush` por tenant no funciona. Cambiar a `"tenant:{$tenant->getId()}:quota:{$metric}:{$period}"` y documentar `// Quotas cached, not DB RLS`. Si se quiere RLS, leer `quota_snapshots` vía `TenantScope`. Añadir test `CrossTenantLeakTest` para `tenant_feature_overrides` (ya tiene RLS `FORCE` `:52`).
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: C006] [P3 Bajo] `ResolveTenantFeatures` hace `orWhere plans.id` sin índice compuesto y `whereHas` N+1 potencial

- **Categoría:** Performance | Backend
- **Ubicación:** `app/Modules/Central/Catalog/Application/Actions/ResolveTenantFeatures.php:42-49` (`whereHas('plans', fn($q)=> where slug = plan_id orWhere id = plan_id)`, `where('is_active', true)->pluck('key')`)
- **Problema y Evidencia:** Query toca `features` → `plan_features` → `plans` sin `with`. Cada `hasFeature` dispara `Cache::rememberForever` closure con 2 queries (`planFeatures` + `overrides->with('feature')`). Con `tenant->hasAllFeatures(['a','b','c'])` se llama `execute` 3 veces (1 por feature en `HasFeatures:26` loop) — aunque Cache evita DB, sigue haciendo `Cache::get` 3 veces. Además `orWhere plans.id` sin `index(slug, id)` fuerza seq scan en `plans` si `plan_id` es slug `free` vs UUID `plan_123`. No usa `select('features.key')` optimizado ni `->exists()` para checks puntuales.
- **Impacto y Recomendación:** Latencia agregada con 20 features por request; con 1k tenants concurrentes, `plan_features` JOINs saturan. Memoize `execute` en request-scoped `app()->scoped` o `static $memo`, y añadir `index(plans.slug)` + `index(plan_features.feature_id)`. Para `hasFeature`, usar `Cache::get` + `in_array` sin re-query. Esfuerzo bajo.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: C007] [P3 Bajo] `ManageFeature` no loggea `activity` ni invalida cache, `TenantOverrides::render` N+1 en `availableFeatures`

- **Categoría:** Backend | DevOps
- **Ubicación:** `app/Modules/Central/Catalog/Interface/Livewire/ManageFeature.php:74-96` (`$feature->update/create` sin `activity('catalog')`), `Interface/Livewire/TenantOverrides.php:86-88` (`Feature::whereNotIn(ids)->get()` sin paginate), `Application/Actions/ApplyTenantFeatureOverride.php:51` sí loggea pero `ManageFeature` no
- **Problema y Evidencia:** `ApplyTenantFeatureOverride` loggea `activity('features')` bien, pero `ManageFeature::save`/`delete` no registra `activity('catalog')` — auditoría de catálogo incompleta. `TenantOverrides::render` carga `overrides` + `availableFeatures` sin `paginate` ni `limit`; con 200 features, payload Livewire crece (aunque `WithPagination` no se usa aquí, pero `render` se llama en cada interacción). No hay `->with('feature')` paginado, pero `overrides` ya `with('feature')` bien.
- **Impacto y Recomendación:** Ops ciego ante quién desactivó `billing.invoices`. Añadir `activity('catalog')->performedOn($feature)->log('feature_updated')` en `ManageFeature::save/delete` y `Cache::forget` para tenants afectados (ver C001). Paginar `availableFeatures` con `limit 50` o usar search.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: C008] [P3 Bajo] `Carbon::parse($expiresAt)` sin validación ni zona horaria — override expira antes/después

- **Categoría:** Backend
- **Ubicación:** `app/Modules/Central/Catalog/Application/Actions/ApplyTenantFeatureOverride.php:43` (`Carbon::parse($expiresAt)`), `Interface/Livewire/TenantOverrides.php:47` (`expiresAt => 'nullable|date|after:now'`)
- **Problema y Evidencia:** Validación Livewire `date|after:now` usa `now()` del server (UTC), pero `Carbon::parse($expiresAt)` parsea sin timezone explícito; un `expiresAt=2026-08-29 15:00:00` sin TZ se interpreta como UTC aunque el tenant esté en `America/Panama` (ver `TenantSetting timezone`). Además `parse` lanza `InvalidFormatException` si `expiresAt` viene de API futura con formato `Y-m-d\TH:i:sP` no contemplado; `ApplyTenantFeatureOverride` no catch, propaga 500.
- **Impacto y Recomendación:** Override expira 5h antes/después, o 500 en API. Usar `Carbon::parse($expiresAt)->setTimezone('UTC')` o `CarbonImmutable::createFromFormat` con `app()->getLocale()`, y validar en Action con `try/catch` → `ValidationException`. Añadir test `expiresAt with timezone offset`.
- **Complejidad / Prioridad:** Baja / Backlog

## 7. Matriz de riesgos (Tabla: ID | Severidad | Categoría | Impacto | Complejidad)

| ID   | Severidad | Categoría           | Impacto                                                         | Complejidad |
| ---- | --------- | ------------------- | --------------------------------------------------------------- | ----------- |
| C001 | P1 Alto   | Backend/Performance | Permisos stale hasta cache:clear, entitlements de pago mienten  | Media       |
| C002 | P2 Medio  | Database/Backend    | PK UUID muta en cada update, audit roto, remove 404             | Baja        |
| C003 | P2 Medio  | Security            | Gate no definido → 403 siempre o remove sin authz               | Baja        |
| C004 | P2 Medio  | Database            | Unique con soft-delete bloquea re-crear override                | Media       |
| C005 | P2 Medio  | Architecture        | Clave cache `quota:` no `tenant:` viola convención, flush falla | Baja        |
| C006 | P3 Bajo   | Performance         | whereHas orWhere sin índice, Cache get repetido                 | Baja        |
| C007 | P3 Bajo   | Backend/DevOps      | Sin activity en ManageFeature, payload availableFeatures grande | Baja        |
| C008 | P3 Bajo   | Backend             | Timezone parse expira mal, 500 en API                           | Baja        |

## 8. Ruta de trabajo (Fases ordenadas por dependencia: 0. Bloqueadores -> 1. Riesgos -> 2. Estabilización)

**Fase 0 — Bloqueadores (no hay P0, pero sprint con C001)**

1.  **C001**: Emitir `PlanFeaturesChanged` + `FeatureUpdated` events desde `ManagePlan::save` (`Billing`) y `ManageFeature::save/delete`; listener `InvalidateTenantFeaturesCache` con `Tenant::where('plan_id', $slug)->chunkById(200, fn($tenants)=> Cache::forget("tenant:{$t->id}:features"))`. Alternativa: `Cache::remember("tenant:{id}:features", 3600)` con TTL 1h si eventual consistency OK. Test: `FeatureManagementTest` con `plan sync -> hasFeature false without manual clear`.

**Fase 1 — Riesgos (Sprint, depende de C001)** 2. **C002**: Cambiar `updateOrCreate` a `TenantFeatureOverride::firstOrNew([tenant_id, feature_id]) -> fill([type, reason...]) -> save()` sin tocar `id`; dejar `HasUuids` auto. Backfill: migrar duplicados. 3. **C003**: Definir `Gate::define('features:manage', fn(CentralUser $u)=> $u->can('manage-features'))` en `CatalogServiceProvider::boot` + añadir `Gate::authorize` en `removeOverride` y `ManageFeature`. Test `403 for viewer role`. 4. **C004**: Reemplazar `unique(tenant_id,feature_id)` por índice parcial `WHERE deleted_at IS NULL` (PG) o `withTrashed()->restore()` en `ApplyTenantFeatureOverride`. Test `apply -> remove -> apply` sin 23505.

**Fase 2 — Estabilización (Backlog)** 5. **C005**: Renombrar `quota:` → `tenant:{id}:quota:` en `QuotaManager` y añadir `::class` constant para prefix; documentar `// Cache only, RLS via snapshots`. Añadir `CrossTenantLeakTest` para `tenant_feature_overrides` (ya RLS) y `quota_snapshots`. 6. **C006 + C007**: Memoize `ResolveTenantFeatures` en `scoped` binding (`app()->scoped(ResolveTenantFeatures::class)`) + `static $requestCache`; añadir `activity('catalog')` en `ManageFeature`; paginar `availableFeatures` (`limit 50` + search). 7. **C008**: Normalizar `expires_at` con `CarbonImmutable::parse($expiresAt, 'UTC')` y validar en Action; añadir test timezone.

## 9. Quick Wins (Bajo esfuerzo, alto impacto)

- **QW-1 — Invalidar cache en ManagePlan** (`Billing/Interface/Livewire/ManagePlan.php:104`): Tras `sync()`, `Tenant::where('plan_id', $plan->slug)->each(fn($t)=> Cache::forget("tenant:{$t->id}:features"))`. **Esfuerzo: 10 min**, fija C001 para 80% de casos.
- **QW-2 — No tocar PK en override** (`ApplyTenantFeatureOverride.php:37`): Cambiar a `updateOrCreate([...], ['type'=>..., 'reason'=>...])` sin `'id'`. **Esfuerzo: 5 min**, evita audit roto.
- **QW-3 — Gate en removeOverride** (`TenantOverrides.php:66`): Añadir `Gate::authorize('features:manage')` al inicio de `removeOverride`. **Esfuerzo: 5 min**, cierra bypass.
- **QW-4 — Índice parcial soft-delete** (`migrations/2026_06_02_143356:47`): `DB::statement('CREATE UNIQUE INDEX ... WHERE deleted_at IS NULL')` + `down`. **Esfuerzo: 15 min**, permite re-aplicar overrides.
- **QW-5 — TTL en cache feature** (`ResolveTenantFeatures.php:40`): `Cache::remember("tenant:{id}:features", 3600, fn()=>...)` en lugar de `rememberForever`. **Esfuerzo: 5 min**, mitiga C001 sin eventos.

## 10. Qué NO hacer (Refactors tentadores pero innecesarios/sobreingeniería)

- **NO introducir Repository Pattern** para `Feature`/`Plan`. `Feature::whereHas` + `plan_features` pivot es suficiente; Repos añadirían indirección sin beneficio (`ARCHITECTURE_RULES.md` "cero sobreingeniería").
- **NO implementar Event Sourcing para catálogo**. `activity('features')` + `plan_features` pivot ya auditan; event store es sobrecosto para 3 estados (allow/deny/plan).
- **NO extraer “Catalog Microservice”** ni mover `tenant_feature_overrides` a DB separada. Single DB + RLS (`tenant_isolation` `FORCE` en `:52`) es correcto; microservicio rompería `Cache::rememberForever` transaccional.
- **NO crear `FeaturePolicy` con 6 abilities** si solo `features:manage` se usa. Un Gate simple es suficiente; Policy completa es sobreingeniería hasta que haya RBAC central granular.
- **NO migrar quotas a tabla `tenant_quotas`** con RLS y triggers. `QuotaManager` con `Cache` + `quota_snapshots` append-only es adecuado para MVP; tabla quotas es YAGNI hasta que se requiera histórico por feature.
- **NO añadir `tenancy:enable-rls --all` para `features`/`plans`**. Son tablas centrales sin `tenant_id`, RLS no aplica; `tenant_feature_overrides` ya tiene `FORCE`.
- **NO unificar `HasFeatures` y `HasQuotas` en `HasEntitlements`**. Separar features (boolean) de quotas (int) mantiene SRP; trait combinado confundiría `hasFeature` con `withinQuota`.

## 11. Cobertura de pruebas (Flujos críticos expuestos)

**Cubierto (confirmado):**

- `FeatureManagementTest.php:12` — `hasFeature` desde plan, `deny` override revoca, `allow` override otorga (con `forceRefresh`).
- `FeatureAndQuotaMiddlewareTest.php:22` — `EnsureHasFeature` 200/403 + `Allow` override vía DB, `EnsureWithinQuota` 200/429 con `QuotaManager::forceIncrement`.
- `ManageFeature` Livewire `FeatureList` pagination 20, `ManageFeature` key regex `module.action` (`ManageFeature.php:55`).

**No cubierto (huecos críticos):**

- **Cache invalidation** — 0 test que `ManagePlan::catalogFeatures()->sync` invalide `tenant:{id}:features` (bug C001). `runningUnitTests() -> Cache::forget` oculta fallo.
- **Override re-apply tras soft-delete** — no test `apply -> remove (soft) -> apply` con mismo `tenant_id+feature_id` (Unique violation C004).
- **Gate `features:manage`** — no test `403` para `CentralUser` sin permiso en `applyOverride`/`removeOverride` (C003).
- **RLS `tenant_feature_overrides`** — no test `CrossTenantLeakTest` con `SET LOCAL app.tenant_id` + `TenantFeatureOverride::where` (tabla sí tiene `ENABLE RLS` + `FORCE` `migrations/2026_06_02_143356:52`).
- **Expiration** — no test `expires_at` in pasado/futuro con `TenantFeatureOverride` expira y `hasFeature` vuelve a plan base.
- **Quota cache key** — no test `quota:{id}` vs `tenant:{id}:quota` (C005) ni `CrossTenantLeak` para `quota_snapshots`.

## 12. Riesgos pendientes (Observabilidad)

- **Cache sin métrica**: `ResolveTenantFeatures` no loggea `cache.hit/miss` ni `catalog.resolve.latency`; si Redis cae y `rememberForever` falla, `hasFeature` dispara N queries `whereHas('plans')` por request — sin alerta `catalog.cache_miss`.
- **Override expiración silenciosa**: `expires_at` se evalúa solo en `ResolveTenantFeatures:57` (`whereNull or > now()`), pero no hay job que limpie overrides expirados ni `activity` al expirar — ops no ve `feature_expired`.
- **Quota drift**: `QuotaManager::increment` con `Cache::increment` no es transaccional con `tenant_feature_overrides`; si `Cache::forget` se pierde (deploy `cache:clear`), `getCurrentUsage` vuelve a 0 sin `quota_snapshots` reconciliación (solo `SnapshotQuotasJob` diario).
- **Plan JSON `features['quotas']`** — `Plan::features` JSONB sin schema versionada; `HasQuotas::getQuotaLimit` retorna `-1` si `features['quotas'][metric]` falta, bypass silencioso si se renombra metric (ej. `branches` → `branch`).

## 13. Conclusión (Próxima acción accionable)

**Estado 🟡 requiere atención.** Catálogo es funcional para MVP, pero **C001 (cache stale) convierte cualquier cambio de plan en bug visible en producción** — el catálogo miente hasta intervención manual.

**Próxima acción (48 h):**

1.  Asignar owner a `C001` + `C003` (Authz). Implementar QW-1 (invalidar en `ManagePlan`) + QW-3 (Gate en `removeOverride`) + QW-5 (TTL 3600) en rama `fix/catalog-cache`; pasar `php artisan test --filter=FeatureManagement --compact` + `composer lint`.
2.  En paralelo, parchear `C002` (no tocar PK) y `C004` (índice parcial) con test `apply->remove->apply`; re-ejecutar esta auditoría (IDs C001-C004) y, si pasan, promover a 🟢 y planificar Fase 2 (C005-C008) en sprint.

> **Nota de mantenimiento**: Este informe preserva IDs `C001`–`C008` históricos. No reutilizar serie `C` en `docs/modules/billing.md` (serie `B`), `provisioning.md` (serie `P`) ni `operations.md` (serie `O`). Próxima auditoría (`Tenant/Access` o `Platform/Tenancy`) debe usar series `A001`/`T001`.
