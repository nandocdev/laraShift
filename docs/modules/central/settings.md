# Auditoría — Central/Settings

> Fecha: 2026-08-28 | Estado: 🟢 Saludable (0× P0, 0× P1, 3× P2 medios)

## 1. Resumen ejecutivo (Riesgos principales)

El módulo **Central/Settings** es el más pequeño del monolito (6 archivos PHP + 1 migración): un _key-value store_ `central_settings(key PK, value, type)` más fachada `CentralBranding` con `Cache::rememberForever("central_setting_{key}")` y un único Livewire `PlatformBranding` (`/central/settings/branding`). La inspección integral (Rutas → Livewire → Service → Model → DB → Branding consumers) confirma **diseño mínimo y correcto para el alcance actual**, sin deuda estructural.

- **P2 — Sin Gate/Policy ni `activity()` en mutación de branding** (`Interface/Livewire/PlatformBranding.php:28` `save()`). Cualquier `auth:central` puede cambiar `platform_name`/`primary_color`/`logo_url` sin rol y sin rastro audit. Hoy staff = trusted, pero viola `ARCHITECTURE_RULES.md` “validar autorización, nunca solo middleware” y deja a Ops sin `activity('settings')` para `who changed branding when`.
- **P2 — `Cache::rememberForever` sin invalidez de despliegue y sin contrato de tipo** (`Infrastructure/Services/CentralBranding.php:14` + `:22`). `set()` hace `Cache::forget`, pero un `UPDATE central_settings SET value=...` directo en DB, un `Cache::flush` parcial o un cambio de `type` deja cache vieja hasta `php artisan cache:clear`. `castValue()` acepta `bool/int/json` del `type` almacenado sin validar que `value` sea parseable (json_decode sin check), rompiendo `platformName()` con `json` malformado.
- **P2 sistémico — Superficie funcional incompleta para Settings real** (`Providers/SettingsServiceProvider.php:28` solo expone `/central/settings/branding`). No hay gestión de `central_settings` genérica, ni versionado, ni seed idempotente con `CentralBrandingSeeder` fuera de tests. No es bug, pero cualquier nuevo setting (p. ej. `central.mail_from`) tenderá a replicar la fachada en lugar de usar la tabla existente — deuda de evolución.

**Salud global: 6/8 verde, 2/8 amarillo.** No hay P0/P1 financieros ni de aislamiento tenant (tabla central → no RLS necesario). Fixes son de hardening (Gate + audit + TTL/contrato).

## 2. Alcance (Áreas inspeccionadas)

- **Rutas / Interface**: `Providers/SettingsServiceProvider.php:25` (`auth:central` → `GET /central/settings/branding`), `Interface/Livewire/PlatformBranding.php:12` (`mount` + `save` con `hex_color`), `Interface/Views/pages/platform-branding.blade.php:1` (Flux form 3 campos).
- **Dominio / Servicios**: `Domain/Models/CentralSetting.php:8` (`$table central_settings, $primaryKey key string`), `Infrastructure/Services/CentralBranding.php:11` (`get/set/platformName/primaryColor/logoUrl/castValue`), `Infrastructure/Services/CentralPlatformBranding.php:10` (`implements PlatformBrandingContract: name/logoUrl`).
- **DB**: `database/migrations/2026_06_02_133132_create_central_settings_table.php:16` (`key PK, value text, type string, timestamps` — sin RLS, correcto central), `database/seeders/CentralBrandingSeeder.php:12` (`platform_name LaraShift SaaS, primary_color #4f46e5`).
- **Consumidores**: `Central/Growth/Interface/Livewire/LandingPage.php:21` (`CentralBranding::platformName()` + branding en `render`), `Central/Billing/Application/Actions/GenerateInvoicePdf.php:22` (PDF branding), `Platform/UI/Views/components/app-logo.blade.php:3` (`PlatformBrandingContract` binding `CentralPlatformBranding`).
- **Tests**: `tests/Feature/Central/Settings/CentralBrandingTest.php:9` (8 tests: get/set/persist/default/cache), `tests/Feature/Central/DashboardTest.php:7` (`CentralBranding::set` en beforeEach).
- **No inspeccionado** (fuera de alcance Settings): `Tenant/Experience` branding (`TenantSetting` con RLS), `Central/Support` impersonation, `Central/Operations` health.

## 3. Arquitectura actual (Flujo de funcionamiento)

```
[Central Staff] --GET /central/settings/branding--> SettingsServiceProvider:26 auth:central -> PlatformBranding::mount()
        |  CentralBranding::platformName() -> Cache::rememberForever("central_setting_platform_name", fn()=> CentralSetting::find('platform_name') ?? config('app.name'))
        |  CentralBranding::primaryColor() -> Cache::rememberForever("central_setting_primary_color", ...) ?? '#000000'
        |  CentralBranding::logoUrl() -> Cache::rememberForever("central_setting_logo_url")
        |  render('settings::pages.platform-branding') [Flux card 3 campos]
        |
[Browser] --wire:submit save--> PlatformBranding::save()
        |  validate(platformName required|min:3, primaryColor required|hex_color, logoUrl nullable|url)
        |  CentralBranding::set('platform_name', value) -> updateOr_create + Cache::forget
        |  CentralBranding::set('primary_color', value) -> updateOr_create + Cache::forget
        |  CentralBranding::set('logo_url', value) -> updateOr_create + Cache::forget
        |  session()->flash('status')
        |
[SRE/Marketing] --GET / (central_domain)--> Growth/LandingPage::render() -> CentralBranding::platformName()/primaryColor/logoUrl (cache hit) -> pricing hero + nav branding
[Queue/PDF] --GenerateInvoicePdf::execute--> CentralBranding::platformName()/primaryColor/logoUrl -> dompdf invoice-proforma
```

Módulo sigue **Modular Monolith + Service estático sin estado + Livewire solo-UI**. Sin Actions/Jobs/Events propios — correcto per `ARCHITECTURE_RULES.md`: toda lógica en `CentralBranding::set/get`, Livewire solo valida y delega. `PlatformBrandingContract` en `Platform/Contracts` desacopla consumers (Growth, Billing) de Settings concreto ✅.

## 4. Dependencias (Acoplamiento interno/externo)

**Interno (acoplamiento bajo, correcto):**

- `Settings` → `Platform/Contracts` ✅ (`CentralPlatformBranding implements PlatformBrandingContract` `Providers/SettingsServiceProvider.php:18` `bind`). Inversión correcta: `Growth/LandingPage` y `Billing/GenerateInvoicePdf` dependen del Contract, no de `CentralBranding` directo (aunque Growth usa directo `CentralBranding::platformName()` en `:21` — acoplo directo menor, ver `S005`).
- `Settings` → `Platform/UI` vía `app-logo.blade.php:3` `app(PlatformBrandingContract::class)` — binding en `SettingsServiceProvider::register` bien ordenado; si Settings se desactiva, logo cae a fallback `config('app.name')` sin romper.
- `Settings` → `Central/Auth` no existe; `auth:central` en `SettingsServiceProvider:26` usa guard `central` correctamente aislado de tenant.

**Externo:**

- `Illuminate/Cache` (`rememberForever`/`forget`, driver `redis` en prod `CACHE_STORE=redis` en `.env.example`); sin `tags` ni `TTL`, clave global `central_setting_*` sobrevive deploys (útil pero requiere invalidation consciente).
- `Livewire 4` + `FluxUI` (`wire:submit="save"`, `#[Layout('layouts.central')]`, `flux:input/input type=color/url`); sin `Locked`/`Computed` necesarios (3 scalars).
- `Stancl/Tenancy` no tocado (tabla central, no tenant-scoped, no `BelongsToTenant` — correcto).

**Dirección:** `Platform <- Settings` ✅; `Central/Growth` ↔ `Settings` via Contract/binding bien; `Platform` no importa `Settings` salvo `PlatformBrandingContract` (permitido).

## 5. Health Score (Tabla cualitativa por Dominio: 🟢/🟡/🔴)

| Dominio                | Score | Justificación                                                                                                                                                                                                         |
| ---------------------- | ----- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Arquitectura           | 🟢    | Fachada estática + Contract `PlatformBrandingContract` + Livewire solo-UI; sin sobreingeniería (sin Action/Job donde no aporta).                                                                                      |
| Backend (Laravel)      | 🟡    | `CentralBranding::castValue` JSON sin validar + `rememberForever` sin TTL/observabilidad; `PlatformBranding::save` 3× `set()` sin transacción ni `activity`.                                                          |
| Base de Datos          | 🟢    | `central_settings.key PK` + `type` + `timestamps` correcto central (sin RLS necesario); sin N+1 ni scopes mal usados.                                                                                                 |
| Frontend (Livewire/UI) | 🟢    | `PlatformBranding` 3 scalars, sin `public Tenant $model`, payload mínimo; `mount` lee 3 caches, `render` sin queries; Flux form simple.                                                                               |
| Seguridad              | 🟡    | `auth:central` en `SettingsServiceProvider.php:26` ok, pero sin `Gate`/`Policy` para `branding:update` ni `activity('settings')`; `logoUrl nullable                                                                   | url`permite`javascript:`? no, `url`lo bloquea;`hex_color` bien. |
| Performance            | 🟢    | 3× `Cache::rememberForever` por request (`LandingPage` + `PlatformBranding mount`); hit en Redis O(1); sin `with`/`paginate` necesario; `updateOrCreate` 3 escrituras solo en save.                                   |
| Testing                | 🟡    | `CentralBrandingTest.php:9` cubre get/set/persist/cache-hit (8 tests); **0 test para Livewire `PlatformBranding` save (`assertHasNoErrors` + DB), 0 para validación `hex_color`/`url`, 0 para concorrencia `set()`**. |
| DevOps / Observability | 🟢    | Sin métricas `settings.branding_updated` ni `Log::info`, pero no crítico (1 tabla, baja frecuencia de escritura); `activity('provisioning')` no aplica aquí, pero `activity('settings')` faltante no es P1.           |

## 6. Hallazgos (Listado detallado con formato P0-P3)

### [ID: S001] [P2 Medio] Mutación de branding sin Gate ni auditoría — cualquier central puede rebrandear sin rastro

- **Categoría:** Security | DevOps
- **Ubicación:** `app/Modules/Central/Settings/Interface/Livewire/PlatformBranding.php:28-38` (`save()` con `validate` + 3× `CentralBranding::set` sin `Gate::authorize`), `Providers/SettingsServiceProvider.php:25` (`Route::middleware ['web','auth:central']` sin `can`)
- **Problema y Evidencia:** Ruta protegida solo por `auth:central`; un `CentralUser` con rol `support` (sin `admin`) puede `POST wire:update` y cambiar `platform_name` a phishing (`LaraShift` → `Evil SaaS`) o `logo_url` a host externo. No hay `Gate::define('branding:manage')` (grep `Gate::define` solo halla `viewHorizon` en `HorizonServiceProvider`). Tampoco hay `activity('settings')->performedOn(CentralSetting) ->log('branding_updated')` ni `Log::info` con `actor_id`. **Confirmado**: lectura de `PlatformBranding::save` y `AppServiceProvider.php:43` (solo `TenantSettingPolicy`).
- **Impacto y Recomendación:** Reputación / phishing interno; Ops ciego ante quién cambió branding (no hay `auditable`). Añadir `Gate::define('branding:manage', fn(CentralUser $u)=> $u->can('manage-platform'))` en `SettingsServiceProvider::boot` y `Gate::authorize('branding:manage')` al inicio de `save()`; añadir `activity('settings')->causedBy(auth('central')->user())->performedOn($setting)->withProperties([...])->log('branding_updated')` en `CentralBranding::set` o en Livewire post-save. Test: `actingAs(CentralUser support)->call save -> assertForbidden`.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: S002] [P2 Medio] `Cache::rememberForever` sin TTL ni invalidación de despliegue — branding viejo en workers tras `cache:clear` parcial o update directo en DB

- **Categoría:** Backend | DevOps
- **Ubicación:** `app/Modules/Central/Settings/Infrastructure/Services/CentralBranding.php:14` (`Cache::rememberForever("central_setting_{$key}")`), `:22-27` (`set` hace `Cache::forget` pero no `Cache::put` ni `tags`)
- **Problema y Evidencia:** `rememberForever` es permanente hasta `forget`. Si un SRE hace `UPDATE central_settings SET value='#ff0000' WHERE key='primary_color'` directo en psql, o si `Cache::flush` se ejecuta solo en un pod (Redis cluster sin broadcast), el worker Octane/Horizon con cache local sigue sirviendo `primary_color` viejo. Con Octane, `Cache::rememberForever` con `redis` driver es centralizado (bien), pero con `CACHE_STORE=file` en local y `CACHE_STORE=redis` en prod, el `Cache::forget` post-`set` puede perder carrera si 2 admins guardan simultáneamente (último `forget` gana, pero `rememberForever` del primero puede recachear valor intermedio). No hay `::get` con `TTL` ni `Store::tags` para invalidar `central_setting_*` en lote. `CentralBrandingSeeder.php:14` hace 3× `set()` sin `DB::transaction` — si falla a medio seed, cache queda inconsistente.
- **Impacto y Recomendación:** Branding inconsistente entre pods tras deploy (`primaryColor` vieja en `/` vs nueva en `/central/settings/branding`). Cambiar a `Cache::remember("central_setting_{$key}", 3600, ...)` con TTL 1h (eventual consistency) o mantener `rememberForever` pero envolver `set()` en `Cache::lock("branding:{$key}", 5)->block(3, fn()=> updateOrCreate + forget)` + `Cache::tags(['central','branding'])->flush()` si driver soporta tags; documentar `// central settings are centrally cached; use CentralBranding::set() never direct SQL`. Test `Cache::forget` tras `set` + `concurrently` guard.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: S003] [P2 Medio] `CentralBranding::castValue` `json` sin validar y `logoUrl` sin whitelist de dominio — inyección de estilo / SSRF blando

- **Categoría:** Backend | Security
- **Ubicación:** `app/Modules/Central/Settings/Infrastructure/Services/CentralBranding.php:45-52` (`match type => json_decode without JSON_THROW_ON_ERROR`), `Interface/Livewire/PlatformBranding.php:33` (`logoUrl => nullable|url`)
- **Problema y Evidencia:** `type=json` hace `json_decode($value, true)` sin `JSON_THROW_ON_ERROR`; un row con `value='{not json'` (insert manual) retorna `null` silencioso y `CentralBranding::get('key', $default)` devuelve `null` en lugar de `$default`, rompiendo fallback. `logoUrl` validado como `url` acepta `http://evil.com/logo.png` o `data:text/html;base64,...` (aunque `url` rechaza `data:` en Laravel? No, `url` permite `data` scheme en algunos validators). Si `logoUrl` se renderiza en `app-logo.blade.php` con `<img src="{{ $logoUrl }}"` sin `escaped` adicional, un URL con `onerror` no aplica, pero un URL externo sin allowlist permite tracking pixel / mixed-content `http`. No hay `active_url` ni `mimes` check.
- **Impacto y Recomendación:** Branding roto por JSON malformado sin alerta; logo externo puede filtrar IP de visitantes centrales. Validar en `CentralBranding::set` con `if ($type==='json') json_validate($value) ?: throw ValidationException`; en `castValue` usar `json_decode($value, true, 512, JSON_THROW_ON_ERROR)` con try/catch → return `$default` + `Log::warning('central_setting_json_invalid', ['key'=>$key])`. Para logo, añadir `Rule::url()->where(fn($url)=> in_array(parse_host, allowlist))` o validar con `active_url` + `starts_with https://` + `ends_with .png|.svg` según CSP.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: S004] [P3 Bajo] Tabla `central_settings` sin contrato de seeds idempotentes para futuros settings — riesgo de divergencia env

- **Categoría:** Architecture
- **Ubicación:** `database/seeders/CentralBrandingSeeder.php:12` (`CentralBranding::set` 3 keys), `database/migrations/2026_06_02_133132:16` (solo `key/value/type`, sin `description` ni `group`), `app/Modules/Central/Settings/Domain/Models/CentralSetting.php:18` (`fillable key,value,type`)
- **Problema y Evidencia:** Hoy solo hay 3 keys de branding. El próximo setting central (`central.mail_from`, `central.legal_url`) tenderá a crear `CentralMailService` + `central_settings` row ad-hoc. La tabla no tiene `description` ni `is_secret` (para `smtp_password` central futuro), por lo que no se distingue entre branding público y secreto. El seeder no es idempotente para todos los envs (`APP_NAME` vs `platform_name` drift). No es bug, pero la falta de convenio invita a duplicar lógica de settings en `config/settings.php` disperso (ver `S005`).
- **Impacto y Recomendación:** Settings centrales crecerán desordenados. Documentar `docs/ARCHITECTURE_RULES.md` sección "Central Settings" con convenio `key = dot.case, type in [string,int,bool,json], group = branding|legal|mail`; añadir `description` nullable en migración futura; centralizar `CentralSetting` creation solo vía `CentralBranding::set` (evitar `CentralSetting::create` directo) y catalogar keys en `CentralBranding` con consts `KEY_PLATFORM_NAME`.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: S005] [P3 Bajo] Consumers acoplan `CentralBranding` estático en lugar de `PlatformBrandingContract` — testability y Octane staleness

- **Categoría:** Architecture | Backend
- **Ubicación:** `app/Modules/Central/Growth/Interface/Livewire/LandingPage.php:9` (`use CentralBranding` + `:21 CentralBranding::platformName()` 3×), `app/Modules/Central/Billing/Application/Actions/GenerateInvoicePdf.php:8` (igual), `Platform/UI/Views/components/app-logo.blade.php:3` sí usa Contract bien
- **Problema y Evidencia:** `LandingPage::render()` llama `CentralBranding::platformName()/primaryColor()/logoUrl()` directo (3× `Cache::rememberForever` por request). En `GrowthTest` con `RefreshDatabase`, el cache de branding sobrevive entre tests si no se hace `Cache::flush` (ver `CentralBrandingTest.php:18` `Cache::flush` en `beforeEach`). Un test que cambia `platform_name` puede filtrar al siguiente si no se limpia manualmente. Además `LandingPage` es `marketing` layout sin `auth:central`, pero `CentralBranding::get` no distingue guest vs staff (correcto, pero acopla cache global). El contract `PlatformBrandingContract` existe (`Platform/Contracts/PlatformBrandingContract.php:7` `name()/logoUrl()`) y `app-logo` ya lo usa, pero Growth/Billing lo ignoran.
- **Impacto y Recomendación:** Tests frágiles por cache global sin `RefreshDatabase` que flushea Redis; mocking `CentralBranding` stateless es incómodo vs `PlatformBrandingContract` mockable. Inyectar `PlatformBrandingContract $branding` en `LandingPage::render(PlatformBrandingContract $b)` y en `GenerateInvoicePdf::execute` (constructor injection), delegando a `CentralPlatformBranding`. Mantener `CentralBranding` solo como _store_; `CentralPlatformBranding` como _service_ testeable con `app()->scoped`.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: S006] [P3 Bajo] Validación `hex_color` sin normalización a lowercase y sin `Size` en DB — color `#FFF` pasa pero `primary_color` esperado `#ffffff` con 7 chars

- **Categoría:** Frontend | Database
- **Ubicación:** `app/Modules/Central/Settings/Interface/Livewire/PlatformBranding.php:32` (`primaryColor => required|hex_color`), `database/migrations/2026_06_02_133132:18` (`value text` sin check), `CentralBrandingSeeder.php:14` (`#4f46e5`)
- **Problema y Evidencia:** `hex_color` valida `#abc` y `#aabbcc` (3 o 6 hex + `#`). Un color `#FFF` sería válido por Livewire pero en `app-logo.blade.php` `style="color: {{ $primaryColor }}"` puede renderizar `#FFF` que es equivalente a `#ffffff` pero inconsistente con `CentralBranding::primaryColor()` default `#000000` (6 chars). No normalizado a lowercase, por lo que `Cache::rememberForever` puede tener 2 variantes de cache miss si se compara case-sensitive en `PriceFormatter`. No hay `CHECK (value ~ '^#[0-9a-fA-F]{6}$')` en DB.
- **Impacto y Recomendación:** Inconsistencia cosmética menor; viola `DesignSystem` si se espera siempre 6 chars. Normalizar en `CentralBranding::set('primary_color', strtolower($value))` + expandir `#abc` a `#aabbcc` en setter; documentar formato en `PlatformBranding::save` con `->lowercase()` transform.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: S007] [P3 Bajo] `PlatformBranding::mount` y `CentralBranding::get` con 3 roundtrips a Cache/DB en cada `render` — N consultas evitables

- **Categoría:** Performance
- **Ubicación:** `app/Modules/Central/Settings/Interface/Livewire/PlatformBranding.php:22-25` (`platformName = get(); primaryColor = get(); logoUrl = get()` 3× `Cache::rememberForever` + `CentralSetting::find` cada una), `Growth/LandingPage.php:21` igual 3× por request
- **Problema y Evidencia:** Cada `get()` es un `Cache::rememberForever` con closure `CentralSetting::find`. En `mount` + `render` de `PlatformBranding` se ejecutan 6 lookups (3 en mount, 3 implícitas en render si no memoiza). Con `CACHE_STORE=redis` son 6 roundtrips O(1) — no N+1 grave, pero con `LandingPage` (10k visitas/día) son 30k gets sin `Cache::many`. No usa `Cache::getMultiple` ni `CentralSetting::whereIn(['platform_name', ...])->pluck`.
- **Impacto y Recomendación:** Latencia agregada leve. Añadir `CentralBranding::all(['platform_name','primary_color','logo_url'])` con `Cache::many` o `CentralSetting::whereIn(...)->get()->mapWithKeys` + single `Cache::rememberForever('central_settings:branding', fn()=>...)` array; memoize en `CentralBranding` con `static $memo = []` por request. Esfuerzo bajo.
- **Complejidad / Prioridad:** Baja / Backlog

## 7. Matriz de riesgos (Tabla: ID | Severidad | Categoría | Impacto | Complejidad)

| ID   | Severidad | Categoría         | Impacto                                                 | Complejidad |
| ---- | --------- | ----------------- | ------------------------------------------------------- | ----------- |
| S001 | P2 Medio  | Security/DevOps   | Cualquier central rebrandea sin rol ni audit trail      | Baja        |
| S002 | P2 Medio  | Backend/DevOps    | Cache stale tras direct SQL / deploy multi-pod          | Baja        |
| S003 | P2 Medio  | Backend/Security  | JSON malformado rompe fallback; logo externo trackeable | Baja        |
| S004 | P3 Bajo   | Architecture      | Crecimiento desordenado de future central settings      | Baja        |
| S005 | P3 Bajo   | Architecture      | Acoplamiento a fachada estática, tests con Cache leak   | Baja        |
| S006 | P3 Bajo   | Frontend/Database | Hex color inconsistente #FFF vs #ffffff, sin norma DB   | Baja        |
| S007 | P3 Bajo   | Performance       | 3–6 gets por request, sin batch                         | Baja        |

## 8. Ruta de trabajo (Fases ordenadas por dependencia: 0. Bloqueadores -> 1. Riesgos -> 2. Estabilización)

**Fase 0 — Bloqueadores (no hay P0/P1, pero sprint debe priorizar S001+S002)**

1.  **S001**: Añadir `Gate::define('branding:manage', fn(CentralUser $u)=> $u->isAdmin())` en `SettingsServiceProvider::boot` + `Gate::authorize` en `PlatformBranding::save`; loguear `activity('settings')->causedBy(auth('central')->user())->performedOn(CentralSetting::find)->log('branding_updated', props)` . Test `assertForbidden for support role`.
2.  **S002**: Envolver `CentralBranding::set` en `Cache::lock` + envolver `get` con `Cache::remember` TTL 3600 o documentar `// never use direct SQL, always via CentralBranding::set` + añadir `central_settings:version` key para bust manual.

**Fase 1 — Riesgos (Sprint)** 3. **S003**: Endurecer `castValue` con `JSON_THROW_ON_ERROR` + `logoUrl` con `https` + allowlist de host; añadir `central_settings` `CHECK`/`Rule` en Livewire `logoUrl => ['nullable','url','regex:/^https:\/\//']`. 4. **S005**: Migrar consumers a `PlatformBrandingContract` DI (`LandingPage::render(PlatformBrandingContract $b)`) y registrar `CentralPlatformBranding` como `scoped` en Octane.

**Fase 2 — Estabilización (Backlog)** 5. **S004 + S006**: Añadir `description` + `is_secret` + `group` a `central_settings` en migración; normalizar `primary_color` a `strtolower 6-hex` en setter; batch `CentralBranding::all()` con `Cache::many`. 6. **S007**: Memoize `CentralBranding::get` por request (`static array $requestMemo`) y batch `whereIn` para branding triad; añadir `metrics(settings.cache_miss)`.

## 9. Quick Wins (Bajo esfuerzo, alto impacto)

- **QW-1 — Gate en save** (`PlatformBranding.php:28`): `Gate::authorize('branding:manage');` al inicio de `save()`. **Esfuerzo: 5 min**, cierra re-branding por support.
- **QW-2 — Activity log** (`CentralBranding.php:21`): `activity('settings')->causedBy(auth('central')->user())->withProperties(['key'=>$key])->log('central_setting_updated')` tras `updateOrCreate`. **Esfuerzo: 10 min**, aporta audit.
- **QW-3 — TTL en branding cache** (`CentralBranding.php:14`): Cambiar `rememberForever` a `remember("central_setting_{$key}", 3600, ...)` para Growth/Landing (eventual consistency 1h). **Esfuerzo: 5 min**, mitiga S002 sin lock.
- **QW-4 — Validar JSON** (`CentralBranding.php:45`): `json_decode($value, true, 512, JSON_THROW_ON_ERROR)` + catch → `Log::warning` + return default. **Esfuerzo: 5 min**, evita null silencioso.
- **QW-5 — Test Livewire branding** (`tests/Feature/Central/Settings/PlatformBrandingTest.php` nuevo): `Livewire::test(PlatformBranding::class)->set platformName -> call save -> assertHasNoErrors + assertDatabaseHas central_settings`. **Esfuerzo: 20 min**, cubre validación hex_color/url.

## 10. Qué NO hacer (Refactors tentadores pero innecesarios/sobreingeniería)

- **NO introducir Repository Pattern** sobre `CentralSetting::find`/`updateOrCreate`. Finder de 1 PK es suficiente; Repos añadirían indirección sin beneficio per `ARCHITECTURE_RULES.md`.
- **NO implementar Event Sourcing para branding**. `activity('settings')` + `central_settings` es suficiente; event store es sobrecosto para 3 keys.
- **NO extraer “Settings Microservice”** ni mover `central_settings` a DB tenant. Tabla central sin RLS es correcta; microservicio rompería `Cache::rememberForever` y single-DB.
- **NO crear `BrandingPolicy` con 6 abilities** si solo `branding:manage` se usa. Un Gate simple es suficiente; Policy completa es sobreingeniería hasta RBAC granular.
- **NO añadir `tenancy:enable-rls` para `central_settings`** — tabla central sin `tenant_id`, RLS no aplica (a diferencia de `tenant_settings` que sí tiene `tenant_isolation`).
- **NO unificar `CentralBranding` y `Tenant/Experience` `TenantSetting`** — dominios separados (`central` vs `tenant` branding) per `PRD §11`; unificarlos acoplaría ciclos de release framework/producto.
- **NO migrar a `spatie/laravel-settings` ni `config(settings.php)` dinámico** para 3 keys. La fachada actual con `Cache::rememberForever` es YAGNI-safe.

## 11. Cobertura de pruebas (Flujos críticos expuestos)

**Cubierto (confirmado):**

- `CentralBrandingTest.php:9` — 8 tests: `platformName fallback config`, `set/get string`, `persist to DB`, `primaryColor default #000000` + `set #ff0000`, `logoUrl null vs set url`, `get unknown key default`, `caches after first read` (`Cache::flush` + `where update Changed` sigue `Cached`).
- `Central/DashboardTest.php:7` — usa `CentralBranding::set('platform_name', ...)` en `beforeEach` (indirectamente valida seed).
- `CentralBrandingSeeder.php:12` — idempotente `set 3 keys` para dev/demo.

**No cubierto (huecos críticos):**

- **Livewire `PlatformBranding` save** — 0 tests. No test `Livewire::test(PlatformBranding)->set platformName + save -> assertHasNoErrors + assertDatabaseHas` ni validación `hex_color`/`url` fallida ni `Gate 403`.
- **Concurrency `set()`** — no test `2× concurrent set same key` con `Cache::lock` ni `Cache::forget` race.
- **Invalid JSON / type handling** — no test `type=json value='{not json'` + `castValue` throws vs silent null.
- **RLS inexistente correcto** — no aplica (central table, no tenant_id), pero tampoco hay test que asegure `central_settings` nunca recibe `tenant_id`.
- **Consumers branding** — no test `GET / (central) -> assertSee platformName` ni `GenerateInvoicePdf` con branding custom.

## 12. Riesgos pendientes (Observabilidad)

- **Branding drift sin alerta**: `CentralBranding::get` no emite `metrics(branding.cache_miss)` ni `Log::warning` cuando `CentralSetting::find` retorna null y cae a `config('app.name')`; si `central_settings` se trunca, landing muestra `LaraShift` por defecto sin alerta.
- **Cache sin métrica**: Si Redis cae, `Cache::rememberForever` con `redis` driver dispara `ConnectionException` y `LandingPage::render` lanza 500 sin fallback a `config('app.name')`; no hay `try/catch` con fallback + `Log::error('branding.cache_failed')`.
- **Logo externo sin CSP**: `logoUrl` external (`https://evil.com/logo.png`) se carga en `app-logo` sin `Content-Security-Policy` `img-src` allowlist; si CSP es estricta, logo externo bloqueado sin aviso; si es laxa, tracking pixel.
- **Octane staleness**: Con Octane, `Cache::rememberForever` en `redis` es compartido, pero `static $memo` en `CentralBranding` (si se añade per `S005`) viviría por worker y requeriría `scoped()` binding para no staleness cross-request — hoy no hay `scoped`, pero si se añade, documentar `// scoped() not singleton for Octane`.

## 13. Conclusión (Próxima acción accionable)

**Estado 🟢 Saludable.** Settings es funcional y mínimo para MVP de branding central. No bloquea release; los riesgos son de hardening y trazabilidad, no de corrupción de datos.

**Próxima acción (48 h):**

1.  Asignar owner a `S001` (Security). Implementar QW-1 (Gate en `save`) + QW-2 (activity `settings` log) + QW-5 (Livewire test) en rama `fix/settings-hardening`; pasar `php artisan test --filter=CentralBranding --compact` + `composer lint`.
2.  En paralelo, evaluar QW-3 (TTL 3600) vs `Cache::lock` para `S002`; si se mantiene `rememberForever`, documentar `// use CentralBranding::set only` en `ARCHITECTURE_RULES.md` y cerrar `S003` con `JSON_THROW_ON_ERROR`. Re-auditar solo si se añaden nuevos settings centrales (ej. `central.mail_from`) antes de escalar a 100 tenants.

> **Nota de mantenimiento**: Este informe preserva IDs `S001`–`S007` históricos. No reutilizar serie `S` en `docs/modules/billing.md` (serie `B`), `provisioning.md` (serie `P`), `operations.md` (serie `O`), `catalog.md` (serie `C`) ni `growth.md` (serie `G`). Próxima auditoría (`Tenant/Access` o `Platform/Tenancy`) debe usar series `A001`/`T001`.
