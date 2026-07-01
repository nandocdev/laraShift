# Gap Analysis: Central Module vs `docs/FEATURES.md`

> **Fecha:** 2026-06-30 | **Versión FEATURES.md:** 1.0 | **Completitud:** ~45%

---

## Resumen

| Categoría | Total | ✅ Implementado | ⚠️ Parcial | ❌ Ausente |
|---|---|---|---|---|
| Tablas DB | 7 | 3 (43%) | 0 | 4 (57%) |
| Métodos API | 8 | 1 (12%) | 2 (25%) | 5 (63%) |
| FeatureResolver | 1 | 0 | 1 (100%) | 0 |
| Capa de cache | 6 | 2 (33%) | 0 | 4 (67%) |
| Edge cases | 6 | 2 (33%) | 2 (33%) | 2 (33%) |
| Tests (del doc) | 9 | 5 (56%) | 0 | 4 (44%) |
| **Total** | **37** | **13 (35%)** | **6 (16%)** | **18 (49%)** |

---

## 1. Database Tables

### ✅ `features` — Implementado
Tabla con columnas: `id` (UUID PK), `key` (unique), `name`, `description`, `module`, `is_active`, `targeting` (jsonb, bonus), `timestamps`, `softDeletes`.

### ✅ `plans` — Implementado (pre-existente en Billing)
Modelo: `app/Modules/Central/Billing/Models/Plan.php`. UUID, soft deletes, `catalogFeatures()` relación many-to-many.

### ✅ `plan_features` — Implementado
Pivot table `plan_features` con `plan_id`, `feature_id`.

### ✅ `tenant_feature_overrides` — Implementado
Columnas: `id`, `tenant_id`, `feature_id`, `type` (allow/deny), `reason`, `expires_at`, `created_by`, `timestamps`, `softDeletes`. Índice único `(tenant_id, feature_id)`. RLS policy para PostgreSQL.

### ❌ `feature_groups` — Ausente
No existe tabla, migración ni modelo. El doc especifica: `id`, `key`, `name`.

### ❌ `feature_group_items` — Ausente
No existe. M:N pivot con `group_id`, `feature_id`.

### ❌ `tenant_plans` — Ausente
No existe. El doc especifica: `id`, `tenant_id`, `plan_id`, `starts_at`, `ends_at`, `is_active`. Índices tenant-scoped.

---

## 2. API — Traits y Métodos

### ✅ `$tenant->hasFeature($key)` — Implementado
Trait `HasFeatures` en `Concerns/HasFeatures.php`. Llama a `ResolveTenantFeaturesAction` internamente.

### ✅ `$tenant->hasAllFeatures([...])` — Bonus (no en doc)
Método extra para múltiples features.

### ✅ `$tenant->hasAnyFeature([...])` — Bonus (no en doc)
Método extra.

### ❌ `$tenant->assignPlan($plan)` — Ausente
`plan_id` se asigna como atributo directo. No hay `tenant_plans` table.

### ⚠️ `$tenant->grantFeature($key)` — Parcial
No hay método directo en el trait. Equivalente: `app(ApplyTenantFeatureOverrideAction::class)->execute($tenant, $featureKey, 'allow')`.

### ⚠️ `$tenant->revokeFeature($key)` — Parcial
No hay método directo. Equivalente: `app(RemoveFeatureOverrideAction::class)->execute($override)`.

### ❌ `$plan->giveFeatureTo($key)` — Ausente
Requiere: `$plan->catalogFeatures()->attach($featureId)`.

### ❌ `$plan->revokeFeature($key)` — Ausente
Requiere: `$plan->catalogFeatures()->detach($featureId)`.

### ❌ `$plan->hasFeature($key)` — Ausente
Requiere: `$plan->catalogFeatures()->where('key', $key)->exists()`.

### ❌ `Feature::findByKey($key)` — Ausente
Requiere scope o método static.

---

## 3. FeatureResolver

### ⚠️ `ResolveTenantFeaturesAction` — Implementado con diferencias

| Doc especifica | Código actual |
|---|---|
| `execute(Tenant, string $feature): bool` | `execute(Tenant, bool $forceRefresh = false): array` |
| Retorna boolean por feature | Retorna array plano de feature keys |
| `$this->forTenant()->has()` fluido | `HasFeatures` trait usa `in_array()` |

**Correcto:** Jerarquía de resolución (Override deny > Override allow > Plan features), cache con TTL (300s), evaluación de targeting rules (bonus).

---

## 4. Capa de Cache

| Feature | Estado |
|---|---|
| Redis-first (vía `Cache::remember`) | ✅ |
| TTL 300s (no `rememberForever`) | ✅ |
| Key format: `tenant:{id}:features` | ✅ |
| Payload: array plano de keys | ✅ |
| **Warmup en middleware de tenancy** | ❌ Ausente |
| **Invalidación en cambio de plan** | ❌ Ausente |
| **Invalidación en feature desactivada** | ❌ Ausente |
| **Invalidación en billing sync** | ❌ Ausente |

**Solo override changes** gatillan invalidación de cache vía `forceRefresh`. Los cambios de plan, features desactivadas y billing sync dejan el cache stale hasta que expire el TTL.

---

## 5. Edge Cases

| Edge case | Estado |
|---|---|
| **1. Expiración** (`expires_at` en overrides) | ✅ Implementado: filtro en `ResolveTenantFeaturesAction` |
| **2. Feature retirada** (`is_active=false`) | ✅ Implementado: filtro `where('is_active', true)` |
| **3. Downgrade — invalidar cache** | ⚠️ Parcial: solo con `forceRefresh` manual |
| **3. Downgrade — disparar evento** | ❌ No existe `PlanDowngraded` ni `PlanChanged` |
| **3. Downgrade — bloquear acceso** | ⚠️ Parcial: `hasFeature()` funciona, pero cache stale permite acceso post-downgrade |
| **3. Downgrade — preservar overrides** | ✅ Soft deletes, nunca se borran |
| **4. Queue contamination** | ❌ Sin protección para jobs que resuelven features sin tenancy |

---

## 6. Desviaciones Clave

### 6A. Status code en middleware
**Doc/Coding Standard:** 404
**`EnsureHasFeature` middleware:** 403

Esto viola la regla explícita de AGENTS.md: *"Cross-tenant access returns 404, never 403."*

### 6B. Resolución retorna array vs boolean
El doc especifica resolver una feature por vez (`execute(Tenant, string): bool`). El código resuelve todas y usa `in_array`. Más eficiente pero diferente de lo documentado.

### 6C. Targeting rules (bonus)
La columna `targeting` en features table no está en el doc. Implementa reglas por región, staff count, y antigüedad del tenant. Bien testeado.

### 6D. Ubicación del dominio
El doc prefiere `Central/Features` sobre `Central/Billing`. ✅ El código sigue esto correctamente.

---

## 7. Tests

### ✅ Implementados (del doc)
- Plan → Feature resolution (`FeatureManagementTest`)
- Override allow (`FeatureManagementTest`)
- Override deny (`FeatureManagementTest`)
- Cache TTL (`FeatureTargetingTest`)
- Force refresh bypasses cache (`FeatureTargetingTest`)
- Targeting by region (`FeatureTargetingTest`)
- Targeting by staff count (`FeatureTargetingTest`)
- Targeting by tenancy age (`FeatureTargetingTest`)
- Activity logging (`FeatureTargetingTest`)
- Middleware feature gate (`FeatureAndQuotaMiddlewareTest`)
- Livewire component registration (`FeatureTargetingTest`)

### ❌ Ausentes (del doc)
- Override expiration test (`expires_at` pasado → override ignorado)
- Isolation test (Tenant A ≠ Tenant B)
- Cross-tenant 404 test
- Downgrade cache invalidation test
- Queue contamination test

---

## 8. Plan de Mitigación Priorizado

### Fase 1 — 🔥 Hotfix (Seguridad)
1. **Fix `EnsureHasFeature` middleware**: cambiar 403 → 404 para mantener consistencia con cross-tenant isolation policy.
2. **Fix cache invalidation on plan change**: agregar listener `TenantPlanChanged` que invalide cache de features.
3. **Fix cache invalidation on feature deactivation**: agregar observer en `Feature` model.

### Fase 2 — 🛠 API Surface
4. Agregar `$tenant->assignPlan($plan)` con migración `tenant_plans`.
5. Agregar `$tenant->grantFeature($key)` / `$tenant->revokeFeature($key)` en trait `HasFeatures`.
6. Agregar `$plan->giveFeatureTo($key)` / `$plan->revokeFeature($key)` / `$plan->hasFeature($key)` en `Plan` model.
7. Agregar `Feature::findByKey($key)` como scope o método static.

### Fase 3 — 📌 Estructural
8. Agregar tablas `feature_groups` y `feature_group_items`.
9. Agregar cache warmup middleware para tenancy initialization.
10. Agregar tests faltantes: expiration, isolation, cross-tenant 404, downgrade, queue.
