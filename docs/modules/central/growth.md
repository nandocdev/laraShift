# Auditoría — Central/Growth

> Fecha: 2026-08-28 | Estado: 🟡 Requiere atención (0× P0, 2× P1 altos, 5× P2 medios)

## 1. Resumen ejecutivo (Riesgos principales)

El módulo **Central/Growth** es el _funnel_ público del SaaS: landing (`/`) + wizard de registro (`/register`) en 3 pasos (organización → plan → confirmación) que termina en `CreateTenantAction` y, para planes de pago, en `BillingManager::createCheckoutSession`. La inspección integral (Rutas → Livewire → Actions → Billing/Catalog → DB) confirma arquitectura **limpia y mínima (6 archivos PHP)** sin lógica de dominio en controladores, pero expone **2 P1 que bloquean escalar adquisición** y deuda que compromete seguridad del registro.

- **P1 — Contraseña en estado público Livewire sin limpieza** (`Interface/Livewire/RegisterTenant.php:42` `public string $password = ''`). Livewire 4 serializa props públicas en cada `wire:update` (payload JSON + snapshot). El password viaja en claro en la respuesta HTML + `wire:snapshot` y nunca se hace `$this->reset('password')`; si el wizard falla en paso 3, el secreto permanece en memoria del componente y en `logs` si se dumpea el payload. `Archived` en `AppServiceProvider` no afecta.
- **P1 — Throttle solo en ruta fallback, no en dominios centrales reales** (`Interface/Routes/web.php:12` `Route::domain($domain)->group` sin middleware, `:19` fallback `throttle:5,1`). `central_domain=larashift.test` usa la ruta con dominio (`acme.larashift.test/register` resuelto vía `central_domains` config), por lo que un bot puede inundar `POST livewire/update` para crear tenants sin límite (flood de `tenants` + `domains` + `ProvisionTenantJob`).
- **P2 sistémico** — `register()` no captura `UniqueConstraintViolationException` de `CreateTenantAction` (a diferencia de `Provisioning/Livewire/CreateTenant.php:58` que mapea a `addError`), por lo que slug tomado concurrentemente explota con 500; `isPlanFree()` + `getSelectedPlanProperty()` disparan 2× `Plan::where(slug)` sin cache por `render()`, y `PlanManager::all()` (`Billing/Infrastructure/Gateways/PlanManager.php:13` `Plan::where(is_active)`) sin `Cache::remember`; `LandingPage` y `RegisterTenant::render` recomputan planes/branding en cada interacción Livewire.

**Salud global: 4/8 verde, 4/8 amarillo.** No hay P0 financiero (el checkout no crea `Subscription` — solo redirect), pero sin P1 el registro público es abusivo y filtra secretos en wire payload.

## 2. Alcance (Áreas inspeccionadas)

- **Rutas / Interface**: `Interface/Routes/web.php:9` (`foreach tenancy.central_domains => Route::domain()->group` + `Route::get('/register')->middleware throttle:5,1`), `Interface/Livewire/{LandingPage:12, RegisterTenant:30}` + `Views/pages/{landing-page:1, register-tenant:1}`.
- **Aplicación / Dominio**: `RegisterTenant:137 register(CreateTenantAction)` → `Central/Provisioning/Actions/CreateTenantAction.php:29` (`DB::transaction` + `ReserveTenantDomainAction` + `afterCommit ProvisionTenantJob`), `DTOs/CreateTenantData.php:10` (`status = isPlanFree ? active : pending_payment`), `Provisioning/Support/ReservedSlugs.php:8`.
- **Dependencias internas**: `Central/Catalog/Domain/Models/Plan.php:12` (`price_monthly MoneyCast`), `Central/Billing/Infrastructure/Gateways/{BillingManager.php:34, PlanManager.php:13, InternalBillingProvider.php:17}` (`tenant_route` → `tenant.billing.checkout.hosted`), `Central/Settings/Infrastructure/Services/CentralBranding.php:12` (`Cache::rememberForever central_setting_*`).
- **Dependencias externas**: `Illuminate/Validation/Rules/Password::defaults()` (`RegisterTenant.php:68` 12 chars + mixedCase en prod), `Livewire 4` (`wire:model.live.debounce.300ms`, `#[Layout('layouts.marketing')]`), `Stancl/Tenancy helpers tenant_route()` (`vendor/stancl/tenancy/src/helpers.php:59`), `Spatie/laravel-data` (`CreateTenantData`).
- **DB**: `database/migrations/2019_09_15_000010_create_tenants_table:15` (`slug unique`, `plan_id default free`, `billing_gateway default clave`), `2019_09_15_000020_create_domains_table:12` (`domain unique`, `FK cascade`), `database/seeders/PlanSeeder.php:15` (`free/pro/enterprise` + `plan_features sync`).
- **Tests**: `tests/Feature/Central/RegisterTenantTest.php:1` (wizard steps + slug autogenerate), `tests/Pest.php` (`RefreshDatabase`), `tests/Feature/ProvisioningTest.php` (event `TenantProvisioned` fake).
- **No inspeccionado** (fuera de alcance Growth): `Central/Billing` `CheckoutController`/`WebhookController` (ver `billing.md`), `Tenant/Workspace` onboarding post-pago, `Platform/Metering` quotas; `LandingPage` SEO/analytics no auditado a fondo.

## 3. Arquitectura actual (Flujo de funcionamiento)

```
[Browser] --GET / (central_domain)--> Growth/Routes/web.php:11 domain group
        |  -> LandingPage::render() -> PlanManager::all() (Plan where is_active) + CentralBranding::platformName/logo (Cache::rememberForever) -> marketing::pages.landing-page (Flux hero + pricing cards href="/register?plan=slug")
        |
[Browser] --GET /register?plan=pro--> RegisterTenant::mount() (Plan::where slug exists ? plan_id=query else free)
        |  wire:model.live.debounce.300ms company -> updatedCompany() -> Str::slug -> slug
        |  updatedSlug() -> autoGenerateSlug=false + Str::slug
        |  nextStep() validates rulesForStep(step) (step1: name,email,company,slug regex+unique+not_in ReservedSlugs, password) -> step++ (max 3)
        |  selectPlan(slug) -> plan_id=slug (no validate until nextStep/register)
        |
[Browser] --wire:click register (step 3)--> RegisterTenant::register(CreateTenantAction)
        |  validate(allRules merge step1+2)  // Re-valida todo
        |  isPlanFree() -> Plan::where(slug, plan_id)->first()->price_monthly->isPositive()  // 2nd query
        |  CreateTenantData(name: company, slug, email, plan_id, password, status: active|pending_payment)
        |  -> CreateTenantAction::execute (valida slug no failed, DB::transaction Tenant::create + ReserveTenantDomainAction domains.updateOrCreate(domain=slug.central_domain) + afterCommit ProvisionTenantJob dispatch)
        |  if pending_payment: BillingManager::createCheckoutSession(tenant, selectedPlan.id) -> InternalBillingProvider tenant_route(domain, 'tenant.billing.checkout.hosted', plan_uuid) -> redirect away (navigate:false)
        |  else: tenant_route(domain, 'login') -> redirect away (navigate:false)
        |
[Queue: default] ProvisionTenantJob (TenantAware, RehydratesTenantContext: SET LOCAL) -> ProvisionTenantPipeline db_schema (TenantDataSeeder) -> infrastructure (Railway stub) -> admin_user (TenantProvisioned -> CreateInitialAdminUser tenant.run)
[Scheduler] --provisioning:reconcile hourly--> expire pending_payment >24h -> status expired + OnboardingExpiredNotification (Growth no participa)
```

Módulo sigue **Livewire 4 + Actions + BillingManager**; sin Jobs/Events propios. Correcto per `ARCHITECTURE_RULES.md`: sin Repository/CQRS, sin lógica HTTP en Action.

## 4. Dependencias (Acoplamiento interno/externo)

**Interno (acoplado donde duele):**

- `Growth` → `Provisioning` vía `CreateTenantAction` directo (`RegisterTenant.php:10` `use CreateTenantAction` + `DTO CreateTenantData`). Esperado (Central-Central), pero `RegisterTenant::register` no captura `RuntimeException` de slug tomado (ver `CreateTenantAction.php:82` `UniqueConstraintViolationException -> RuntimeException`), mientras `Provisioning/Livewire/CreateTenant.php:58` sí mapea a `addError`. Inconsistencia Central vs Growth vis-a-vis mismo Action.
- `Growth` → `Billing` vía `BillingManager` (`RegisterTenant.php:7` + `:160` `app(BillingManager::class)->createCheckoutSession`). Acopla Growth a Billing sin Contract `CheckoutSessionResolver`; `BillingManager` resuelve `forTenant` bien, pero `InternalBillingProvider::createCheckoutSession` usa `tenant_route` con `plan_uuid` no `checkoutUrl` real de gateway — redirect a tenant hosted checkout, no a Clave/dLocal directo (flujo correcto, pero `PaymentData` no se crea aquí, solo después en `HostedCheckout`).
- `Growth` → `Catalog` vía `Plan` model directo (`RegisterTenant.php:9` + `PlanManager` + `isPlanFree` query). Viola `ARCHITECTURE_RULES.md` "ningún módulo accede directamente a Models de otro módulo" — debería usar `Contracts/CatalogResolver` o `PlanManager` como facade (parcialmente lo hace en `render` pero no en `mount`/`isPlanFree`).
- `Growth` → `Settings` vía `CentralBranding` (`LandingPage.php:9`) — `Cache::rememberForever` sin `tags` (mismo antipatrón `C001` catalog, pero para branding es aceptable).

**Externo:**

- `Stancl/tenancy` (`tenant_route` helper `vendor/stancl/tenancy/src/helpers.php:59` reemplaza hostname vía `parse_url` + `substr_replace`); frágil si `route('tenant.billing.checkout.hosted')` usa `APP_URL` central sin tenant domain en cache route.
- `Livewire` (`wire:model.live.debounce.300ms` + `wire:click` en cada card `selectPlan`); payload `plans` (colección Eloquent) serializado en cada `render` vía `PlanManager::all()` — no usa `Locked` ni `Computed`.
- `Password::defaults()` (`AppServiceProvider.php:49` 12 chars + uncompromised en prod) — en local/testing es `null` → `password` puede ser `12345678`.

**Dirección:** `Platform` (Contracts, Tenancy) ← `Growth` ← no depende de `Tenant` ✅; `Platform` no depende de `Growth` ✅.

## 5. Health Score (Tabla cualitativa por Dominio: 🟢/🟡/🔴)

| Dominio                | Score | Justificación                                                                                                                                                                                                                                                                             |
| ---------------------- | ----- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Arquitectura           | 🟢    | Wizard 3 pasos con `rulesForStep` + `CreateTenantAction` `final readonly execute(DTO)`; sin lógica de negocio en Livewire más allá de `validate` + `action->execute` + redirect.                                                                                                          |
| Backend (Laravel)      | 🟡    | `password` en prop pública sin `reset`, `register` sin try/catch para `RuntimeException` slug, `isPlanFree()` doble query + `PlanManager::all()` sin cache; `Str::slug` bien pero `not_in` case-sensitive (ver `P011` provisioning).                                                      |
| Base de Datos          | 🟢    | `tenants.slug unique` + `domains.domain unique` + FK cascade bien; `CreateTenantAction` captura `UniqueConstraintViolationException` pero Growth no lo mapea a campo.                                                                                                                     |
| Frontend (Livewire)    | 🟡    | `wire:model.live.debounce.300ms` reduce roundtrips, pero `render()` carga `PlanManager::all()` + `CentralBranding` en cada interacción; cards `wire:click="selectPlan"` sin `Locked` plan_id es tamperable (mitigado por `exists:plans,slug` en validation).                              |
| Seguridad              | 🟡    | `throttle:5,1` solo en fallback `/register` (`Routes/web.php:19`), dominios centrales sin throttle; `password` viaja en snapshot; `company/name` sin `strip_tags`; no honeypot/captcha para registro público.                                                                             |
| Performance            | 🟡    | `LandingPage::render` + `RegisterTenant::render` sin cache: 2× `Plan::where` + `CentralBranding` hits por cada `nextStep`/`updatedCompany`; con 10k visitas/día → N consultas innecesarias.                                                                                               |
| Testing                | 🟡    | `RegisterTenantTest.php:7` cubre wizard + slug autogenerate + `step 3 register` 200; **0 test para paid plan checkout redirect, 0 para slug race/throttle, 0 para `isPlanFree` pending_payment, 0 para `GrowthServiceProvider` routes domain group**.                                     |
| DevOps / Observability | 🟢    | `activity('provisioning')` + `activity('infrastructure')` en `CreateTenantAction`/`ProvisionInfrastructureAction` bien; sin métricas `growth.registrations`/`growth.checkout_redirects`, sin `Log::warning` en `register` fail, pero no crítico (observability heredada de Provisioning). |

## 6. Hallazgos (Listado detallado con formato P0-P3)

### [ID: G001] [P1 Alto] Contraseña en prop pública Livewire viaja en payload y nunca se limpia — exposición en snapshot y riesgo de log

- **Categoría:** Security | Frontend
- **Ubicación:** `app/Modules/Central/Growth/Interface/Livewire/RegisterTenant.php:42` (`public string $password = ''`), `:137 register()` sin `$this->reset('password')`, `:68` (`Password::defaults()`)
- **Problema y Evidencia:** Livewire 4 serializa todas las props públicas en `snapshot` (JSON) y en respuesta `wire:update`. `password` queda en memoria JS (`$wire.password`) y en payload HTTP `POST /livewire/update` (base64 en `components[].calls`). En `app/Modules/Central/Provisioning/Livewire/CreateTenant.php:40` tampoco se resetea, pero allí es `auth:central`; aquí es **público**. Si `register()` lanza excepción (slug taken, `BillingManager` 500), el componente re-renderiza con `password` intacto y Livewire lo re-envía en siguiente `nextStep`. Además `ProvisionTenantCommand.php:18` no enmascara password en logs. En producción `Password::defaults()` exige 12 chars, pero en testing `null` permite `Password123!` simple — tests no cubren fuerza.
- **Impacto y Recomendación:** Secret en tránsito en cada interacción; si Pail/Log captura `payload` o si alguien inspecciona `wire:snapshot` en HTML, ve password. Usar `#[Locked]` no aplica a password; mejor: marcar `public string $password = ''` con `#[Validate]` y hacer `$this->reset('password')` tras `register` (éxito o fail) + `unset($this->password)` en `dehydrate`; o mover a `$password` no persistida vía `protected` + `#[Form]` request object. Añadir `Log::info` sin incluir password.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: G002] [P1 Alto] Throttle solo en ruta fallback — dominios centrales reales sin rate-limit permiten flood de tenants

- **Categoría:** Security | Backend
- **Ubicación:** `app/Modules/Central/Growth/Interface/Routes/web.php:9-19` (`foreach central_domains as domain => Route::domain($domain)->group` sin middleware, `:19` `Route::get('/register')->middleware throttle:5,1`)
- **Problema y Evidencia:** `config/tenancy.php: central_domain=larashift.test`, `central_domains=[larashift.test, localhost]` — en prod el host es `larashift.test` o `127.0.0.1`, que caen en el `foreach` y su `GET /register` **no** tiene `throttle`. Solo `GET /register` sin host (catch-all) tiene `throttle:5,1`. Un atacante con `Host: larashift.test` puede hacer `POST /livewire/update` indefinido (Livewire update route es `POST /livewire/update` con middleware `web` global, no `throttle` de Growth). `RegisterTenant` no tiene `RateLimiter::hit('register:'.$ip)` en `register()`. `CreateTenantAction` solo protege con `UniqueConstraint` DB, pero el flood crea cientos de `tenants` `pending_payment` + `domains` + `ProvisionTenantJob` cada uno 3 tries.
- **Impacto y Recomendación:** DoS por creación masiva de tenants; costos `ProvisionTenantJob` + Railway stub + mails `WelcomeTenantNotification` (si `active` bypass). Añadir `->middleware('throttle:5,1')` a ambos grupos de dominio, o mejor `RateLimiter::for('register', fn()=> Limit::perMinute(5)->by($request->ip()))` y en `RegisterTenant::register` hacer `RateLimiter::hit`. Test: `assert 429 after 6 POST /register` en dominio central.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: G003] [P2 Medio] `register()` sin mapeo de excepción de slug tomado — race expone 500 en lugar de validación en campo `slug`

- **Categoría:** Backend
- **Ubicación:** `app/Modules/Central/Growth/Interface/Livewire/RegisterTenant.php:137-156` (`$tenant = $action->execute(...)` sin try/catch), `app/Modules/Central/Provisioning/Actions/CreateTenantAction.php:82` (`catch UniqueConstraintViolationException -> RuntimeException "slug just taken"`)
- **Problema y Evidencia:** `CreateTenantAction` sí captura `UniqueConstraintViolationException` y lanza `RuntimeException` con mensaje traducido. `Provisioning/Livewire/CreateTenant.php:58` lo captura y hace `$this->addError('name', $e->getMessage())`. Aquí `RegisterTenant::register` no tiene `try/catch` — la excepción rompe Livewire y retorna 500 (componente en error), no `assertHasErrors(['slug'])`. En tests `RegisterTenantTest.php` no hay test de slug duplicado (solo `unique:tenants,slug` en `nextStep` validación, que puede pasar si dos usuarios registran simultáneamente entre `validate` y `DB::transaction`).
- **Impacto y Recomendación:** UX rota: usuario ve "Something went wrong" en lugar de "Slug just taken". Capturar en `register()` igual que `CreateTenant`: `try { $action->execute } catch (RuntimeException $e) { $this->addError('slug', $e->getMessage()); return; }`. Añadir test `Livewire::test(RegisterTenant)->set slug dup -> call register -> assertHasErrors slug`.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: G004] [P2 Medio] Doble query por `Plan` por render/request + `PlanManager::all()` sin cache — latencia en landing y wizard

- **Categoría:** Performance | Backend
- **Ubicación:** `app/Modules/Central/Growth/Interface/Livewire/RegisterTenant.php:78-84` (`mount` Plan::where slug exists), `:179-183` (`isPlanFree` Plan::where + price_monthly->isPositive), `:189-192` (`getSelectedPlanProperty` Plan::where), `:196` (`render` `PlanManager::all()`), `app/Modules/Central/Billing/Infrastructure/Gateways/PlanManager.php:13` (`Plan::where is_active get` sin Cache), `Interface/Livewire/LandingPage.php:19` (igual)
- **Problema y Evidencia:** `RegisterTenant::render` se invoca en cada `updatedCompany` (300ms debounce), `nextStep`, `selectPlan`. Cada render ejecuta `PlanManager::all()` (query `where is_active`) + `getSelectedPlanProperty` (otra query si no memoizado). `isPlanFree()` se llama en `register` **y** en `render` (`$isPlanFree`), disparando 2× `Plan::where(slug)`. Sin `Cache::remember` ni `#[Computed]`, con 3 planes y 10k visitas/día es 20k queries/día solo para Growth. `CentralBranding::platformName()` sí usa `Cache::rememberForever("central_setting_*")` bien (`Settings/Infrastructure/Services/CentralBranding.php:16`), pero `PlanManager` no.
- **Impacto y Recomendación:** Latencia agregada + presión DB innecesaria. Cachear `PlanManager::all()` con `Cache::remember('plans:active', 3600, fn()=> Plan::where is_active->get())` + invalidar en `ManagePlan::save` (ya es `Billing`); memoizar `isPlanFree` / `selectedPlan` con `#[Computed]` o `once()` en el componente; mover `Plan::where` de `mount` a `mount` cacheado. Test: `assertDatabaseQueryCount` para `GET /register`.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: G005] [P2 Medio] `company`/`name` sin sanitización y `slug` normalización inconsistente entre `updatedSlug` y validación

- **Categoría:** Backend | Security
- **Ubicación:** `app/Modules/Central/Growth/Interface/Livewire/RegisterTenant.php:62-72` (`updatedCompany` Str::slug, `updatedSlug` Str::slug), `:55-66` (`rulesForStep 1: name required|string|max:255` sin `strip_tags`, `company required|string|max:255`), `Provisioning/Support/ReservedSlugs.php:47` (`isReserved strtolower`), `CreateTenantData.php:12` (`slug` sin lowercase)
- **Problema y Evidencia:** `company` se usa como `Tenant.name` (`CreateTenantData name: $this->company`). Si contiene `<script>` o emojis, se guarda en `tenants.name` sin sanitizar (no XSS directo en Blade si se escapa, pero `Tenant::name` se usa en `CentralBranding::platformName` fallback y en mails `WelcomeTenantNotification` sin `e()`). `updatedSlug` hace `Str::slug($value)` (lowercase + dash), pero validación `slug: regex:/^[a-z0-9-]+$/` + `unique:tenants,slug` es case-sensitive; un `slug=Admin` normalizado a `admin` por `updatedSlug` pasa, pero un POST API directa con `slug=Admin` bypasea Livewire y llega a `CreateTenantAction` con mayúsculas (ver `P011` en `provisioning.md`). `CreateTenantData` no fuerza `strtolower`.
- **Impacto y Recomendación:** Registro de slug reservado capitalizado (`Admin` vs `admin`) o nombre con HTML. Normalizar `slug` a `strtolower(Str::slug(...))` en `CreateTenantAction` guard y añadir `Rule::notIn(ReservedSlugs::all())` case-insensitive; sanitizar `company`/`name` con `strip_tags` o `htmlspecialchars` en DTO. Test: `Livewire::test(RegisterTenant)->set slug Admin -> assertHasErrors`.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: G006] [P2 Medio] Falta de verificación de email/honeypot/captcha en registro público — spam y tenants fantasma

- **Categoría:** Security | Architecture
- **Ubicación:** `app/Modules/Central/Growth/Interface/Livewire/RegisterTenant.php:58-68` (`email required|email` sin `Verified` ni `unique:tenants,email` + `unique:users,email`), `Interface/Routes/web.php:19` (sin `honeypot` ni `recaptcha`), `Provisioning/Models/Tenant.php:22` (`email` no unique global? sí `tenants.email unique` en `migrations/2019_09_15_000010:20`, pero no validado en Growth como `unique:tenants,email`)
- **Problema y Evidencia:** `RegisterTenant` valida `email` solo `required|email`, no `unique:tenants,email`. `tenants.email` tiene `unique` DB, por lo que segundo registro con mismo email lanza `UniqueConstraintViolationException` pero con mensaje genérico de slug (misleading). No hay `email_verified_at`, no hay `MustVerifyEmail`, no hay `honeypot` field, no hay `turnstile/recaptcha`. Un script puede crear `tenant` por minuto (ver `G002` sin throttle efectivo). `TenantProvisioned` → `CreateInitialAdminUser` crea `User` con `email` del tenant sin verificar que el email pertenece al registrante.
- **Impacto y Recomendación:** Tenants spam, reputación email `WelcomeTenantNotification` marcada como spam. Añadir `email => 'required|email|unique:tenants,email|max:255'` en `rulesForStep`, añadir `honeypot` hidden field con `validate honeypot:filled 0` o `altcha/turnstile` en step 1, y opcional `email-verification` antes de `active` (estado `pending_verification`). No es P0 porque `pending_payment` expira en 24h (`provisioning:reconcile`).
- **Complejidad / Prioridad:** Media / Backlog

### [ID: G007] [P2 Medio] `redirect()` con `navigate:false` externo sin validación de dominio — open redirect potencial y fuga de tenant context

- **Categoría:** Security
- **Ubicación:** `app/Modules/Central/Growth/Interface/Livewire/RegisterTenant.php:168-173` (`tenant_route($tenant->domains->first()?->domain ?? "{$slug}.".config('tenancy.central_domain'), 'login')` + `$this->redirect($url, navigate:false)`), `vendor/stancl/tenancy/src/helpers.php:60` (`tenant_route` hace `substr_replace` del hostname)
- **Problema y Evidencia:** `tenant_route` construye URL reemplazando `hostname` de `route('login')` con `$domain`. Si `tenant->domains->first()` retorna `null` (tenant `failed` sin domain por race `P004`), el fallback `"{$slug}.larashift.test"` se usa; pero `slug` viene de input usuario (`Str::slug`) y aunque es regex `^[a-z0-9-]+$`, un slug con `central` podría generar `central.larashift.test` que es central domain (`ReservedSlugs` lo bloquea, pero `Admin` bypass `P011`). Además `redirect($checkoutUrl, navigate:false)` hace redirect externo sin `signed` URL — si `BillingManager::createCheckoutSession` es manipulado (plan_id tampered en `selectPlan` sin `exists:plans,slug` check en `selectPlan` mismo, solo en `register` final), el atacante podría inyectar `plan_id=pro` sin pagar y obtener `pending_payment` + checkout URL de otro plan.
- **Impacto y Recomendación:** Bajo (requiere `Host` manipulado + `central` slug). Sanitizar con `TenantDomainResolver::resolveDomain($tenant->id) ?? abort(500)` y validar `plan_id` en `selectPlan` (`$this->validateOnly('plan_id')`). Añadir `signed` route para checkout redirect o validar en `HostedCheckout` que `plan` pertenece a tenant.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: G008] [P3 Bajo] `GrowthServiceProvider` solo registra Livewire sin rutas ni config — rutas en `routes/web.php` desacopladas y sin `loadRoutesFrom` ni `view` namespace versionado

- **Categoría:** Architecture
- **Ubicación:** `app/Modules/Central/Growth/Providers/GrowthServiceProvider.php:14` (`boot: loadViewsFrom marketing, Livewire::component` sin `loadRoutesFrom`), `Interface/Routes/web.php:1` (no cargado vía provider, incluido en `routes/web.php:1` `require base_path(...)`)
- **Problema y Evidencia:** `ProvisioningServiceProvider.php:28` y `CatalogServiceProvider.php:27` sí hacen `loadRoutesFrom`, pero `GrowthServiceProvider` no — depende de `routes/web.php` global con `require`. Si se desactiva el módulo (feature flag), rutas siguen cargadas. Además `LandingPage` y `RegisterTenant` usan `view('marketing::pages.landing-page')` donde `marketing` es namespace de `GrowthServiceProvider::loadViewsFrom`, pero `routes/web.php` no usa `TenantDomainResolver` ni `InitializeTenancyByDomain` — correcto porque son centrales, pero inconsistencia de wiring.
- **Impacto y Recomendación:** DX menor; no afecta producción pero rompe patrón Modular Monolith `Providers/ → Routes/`. Mover `require base_path('app/Modules/Central/Growth/Interface/Routes/web.php')` a `GrowthServiceProvider::boot: loadRoutesFrom`, y añadir `// Growth is always enabled, central-only` comentario. No crear abstracción extra.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: G009] [P3 Bajo] `selectPlan(string $slug)` sin validación ni feedback — Livewire state tamperable sin `validateOnly`

- **Categoría:** Frontend | Backend
- **Ubicación:** `app/Modules/Central/Growth/Interface/Livewire/RegisterTenant.php:129-132` (`public function selectPlan(string $slug): void { $this->plan_id = $slug; }`)
- **Problema y Evidencia:** `wire:click="selectPlan('{{ $plan->slug }}')"` envía `slug` desde DOM; el atacante puede ejecutar `$wire.selectPlan('enterprise')` incluso si no está en `PlanManager::all()` (ej. `plan_id=private-flag`). No hay `validate` en ese método — la validación ocurre solo en `nextStep()` o `register()` con `exists:plans,slug`, por lo que el UI muestra `Selected` en plan inválido hasta el final. No usa `#[On]` ni `Locked`.
- **Impacto y Recomendación:** UX engañosa, no seguridad directa (final `register` sí valida). Mejorar con `selectPlan` → `$this->validateOnly('plan_id')` o `Plan::where slug exists else addError`, y usar `#[Locked]` no aplicable a plan_id dinámico. Test: `Livewire::test(RegisterTenant)->call selectPlan fake -> assert plan_id reset`.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: G010] [P3 Bajo] `LandingPage::render` y `RegisterTenant::render` sin paginación ni `with` ni `SEO` — pricing hardcodeado a `pro` ring

- **Categoría:** Frontend | Performance
- **Ubicación:** `app/Modules/Central/Growth/Interface/Views/pages/landing-page.blade.php:96` (`@foreach $plans` sin paginate, `@if $plan->slug === 'pro'` hardcode), `Interface/Livewire/LandingPage.php:19` (`PlanManager::all()` sin select)
- **Problema y Evidencia:** `LandingPage` renderiza todos los planes (3 hoy) sin `paginate`; con 20 planes futuros, página crece sin `limit`. `pro` ring hardcodeado (`$plan->slug === 'pro' ? 'ring-2'`) no usa `is_featured` flag de `Plan`/`features['is_featured']`. `PriceFormatter::format` se invoca 2× por plan (landing + register). No hay `SEO` meta (`title`, `description`), ni `openGraph`, ni `sitemap`.
- **Impacto y Recomendación:** No bloquea MVP; con crecimiento de planes, paginación no es necesaria (pricing siempre <10). Reemplazar hardcode con `$plan->features['is_featured']` o `is_popular`, y cachear `PlanManager::all()` (ver `G004`). Baja prioridad.
- **Complejidad / Prioridad:** Baja / Backlog

## 7. Matriz de riesgos (Tabla: ID | Severidad | Categoría | Impacto | Complejidad)

| ID   | Severidad | Categoría         | Impacto                                                                                 | Complejidad |
| ---- | --------- | ----------------- | --------------------------------------------------------------------------------------- | ----------- |
| G001 | P1 Alto   | Security/Frontend | Password en snapshot Livewire y payload wire:update, persiste tras error                | Baja        |
| G002 | P1 Alto   | Security/Backend  | Flood de tenants sin throttle en dominios centrales reales, DoS provisioning            | Baja        |
| G003 | P2 Medio  | Backend           | Race slug 500 en lugar de error en campo slug, UX rota                                  | Baja        |
| G004 | P2 Medio  | Performance       | N queries Plan por render sin cache, 2× isPlanFree, PlanManager no cacheado             | Baja        |
| G005 | P2 Medio  | Backend/Security  | company/name sin sanitizar, slug capitalizado bypasea ReservedSlugs                     | Baja        |
| G006 | P2 Medio  | Security          | Sin honeypot/captcha/email unique, spam tenants fantasma                                | Media       |
| G007 | P2 Medio  | Security          | tenant_route fallback slug.central_domain sin validar, plan_id tamperable en selectPlan | Baja        |
| G008 | P3 Bajo   | Architecture      | Provider no carga rutas, require global en routes/web.php, desacoplado                  | Baja        |
| G009 | P3 Bajo   | Frontend          | selectPlan sin validateOnly, estado tamperable hasta register                           | Baja        |
| G010 | P3 Bajo   | Frontend          | Pricing pro ring hardcode, sin paginate/SEO, formatter doble                            | Baja        |

## 8. Ruta de trabajo (Fases ordenadas por dependencia: 0. Bloqueadores -> 1. Riesgos -> 2. Estabilización)

**Fase 0 — Bloqueadores (no hay P0, pero sprint debe priorizar P1)**

1.  **G001**: En `RegisterTenant::register` hacer `try/finally { $this->reset('password'); }` + en `dehydrate` `unset($this->password)` o usar `protected $password` + `FormRequest`; añadir `#[Validate]` en `password` con `Password::min(12)` en prod. Test: `assert snapshot not contains password`.
2.  **G002**: Añadir `->middleware('throttle:register')` a `Route::domain($domain)->group` en `Routes/web.php:9` y definir `RateLimiter::for('register', fn()=> Limit::perMinute(5)->by($request->ip()))` en `GrowthServiceProvider::boot`; también throttle `POST /livewire/update` vía `Livewire::setUpdateRoute middleware throttle:60,1` en `AppServiceProvider`.

**Fase 1 — Riesgos (Sprint, depende de Fase 0)** 3. **G003**: Envolver `register` en `try/catch RuntimeException` y mapear a `$this->addError('slug', $e->getMessage())` (igual que `CreateTenant.php:58`), test slug dup concurrent. 4. **G004**: Cachear `PlanManager::all()` con `Cache::remember('plans:active', 3600, ...)` + invalidar en `ManagePlan::save`; memoizar `selectedPlan` con `#[Computed]` y `isPlanFree` con `once()`. 5. **G005 + G006**: Normalizar slug `strtolower(Str::slug(...))` en `CreateTenantAction` guard + añadir `email => unique:tenants,email` y `company => strip_tags`; añadir honeypot field `website_url` mustBeEmpty.

**Fase 2 — Estabilización (Backlog)** 6. **G007 + G009**: Validar `plan_id` en `selectPlan` con `exists:plans,slug` y sanitizar `tenant_route` fallback con `TenantDomainResolver`; mover `GrowthServiceProvider` a `loadRoutesFrom`. 7. **G008 + G010**: Unificar wiring (`GrowthServiceProvider::loadRoutesFrom` + comentario), reemplazar `pro` hardcode con `features['is_featured']`, añadir `SEO` meta en `LandingPage`.

## 9. Quick Wins (Bajo esfuerzo, alto impacto)

- **QW-1 — Reset password tras register** (`RegisterTenant.php:137`): `try { $tenant = $action->execute(...) } finally { $this->reset('password'); }` + `if (app()->isProduction()) $this->password = ''` en `dehydrate`. **Esfuerzo: 10 min**, elimina secret en snapshot.
- **QW-2 — Throttle en dominio central** (`Routes/web.php:9`): `Route::domain($domain)->middleware('throttle:5,1')->group(...)`. **Esfuerzo: 5 min**, cierra flood de tenants.
- **QW-3 — Capturar slug taken** (`RegisterTenant.php:148`): `catch (\RuntimeException $e) { $this->addError('slug', $e->getMessage()); return; }`. **Esfuerzo: 10 min**, UX correcta en race.
- **QW-4 — Cache planes** (`PlanManager.php:13`): `return Cache::remember('plans:active', 3600, fn()=> Plan::where(is_active)->get())` + `Cache::forget` en `ManagePlan::save`. **Esfuerzo: 15 min**, -2 queries por render.
- **QW-5 — Honeypot anti-spam** (`register-tenant.blade.php:71`): `<flux:input name="website" class="hidden" wire:model="honeypot" />` + `rulesForStep: 'honeypot'=>'prohibited'` y en `register` `if ($this->honeypot) abort(422)`. **Esfuerzo: 15 min**, reduce spam sin captcha.

## 10. Qué NO hacer (Refactors tentadores pero innecesarios/sobreingeniería)

- **NO introducir Repository Pattern** sobre `Plan::where`/`Tenant::where`. `PlanManager::all()` + `CreateTenantAction` es suficiente; Repos añadirían indirección sin beneficio (`ARCHITECTURE_RULES.md` "cero sobreingeniería").
- **NO implementar CQRS/Event Sourcing para registro**. `TenantProvisioned` event + `ProvisionTenantJob` ya desacoplan; event store es sobrecosto para funnel de 3 pasos.
- **NO extraer "Growth Microservice"** ni mover `tenants` a DB separada. Single DB + RLS (`PROJECT_DECISIONS.md §3`) es correcto; microservicio rompería `tenant_route` y `afterCommit` transaccional.
- **NO crear Step Machine con 6 clases**. El wizard `step int + rulesForStep` es adecuado; solo validar `validateOnly` en `nextStep` (ya lo hace), no abstraer a `WizardState` genérico.
- **NO añadir DTOs para cada `wire:model`**. `CreateTenantData` ya tipa el caso de uso; `RegisterTenant` props `name/company/slug/email` no necesitan `RegisterTenantData` extra hasta que haya API móvil.
- **NO unificar `Central/Growth` y `Tenant/Experience` landing builder**. `Growth` es central (adquisición), `Experience` es tenant (branding/landing builder para cliente) — dominios separados per `PRD §11`.
- **NO migrar a Inertia/React para wizard**. Livewire 4 + FluxUI es stack oficial (`PROJECT_DECISIONS.md §13`); SPA rompería SSR y `wire:navigate`.

## 11. Cobertura de pruebas (Flujos críticos expuestos)

**Cubierto (confirmado):**

- `Wizard steps` (`RegisterTenantTest.php:7`) — `nextStep` step1→2→3 con `assertHasNoErrors` + `assertSet('step')`.
- `Slug autogenerate` (`RegisterTenantTest.php:25`) — `updatedCompany -> Str::slug` + `updatedSlug` desactiva `autoGenerateSlug`.
- `Register tenant free` (`RegisterTenantTest.php:14`) — `step 3 + plan_id free -> call register -> assertHasNoErrors 200` (crea `Tenant` + `domain` + `ProvisionTenantJob` afterCommit).
- `PlanSeeder` (`Database/Seeders/PlanSeeder.php:15`) — `free/pro/enterprise` con `plan_features sync` para `crm.pipeline`/`api.access`.

**No cubierto (huecos críticos):**

- **Paid plan checkout redirect** — 0 test que `plan_id pro -> register -> assertRedirect tenant_route('tenant.billing.checkout.hosted')` vía `BillingManager::createCheckoutSession` mock.
- **Slug race/throttle** — no test `2× Livewire::test(RegisterTenant) same slug concurrent -> second assertHasErrors slug` ni `throttle 5,1` 429.
- **Password handling** — no test `assert snapshot not contains password` ni `Password::defaults` 12 chars en prod.
- **ReservedSlugs/case** — no test `slug Admin -> Str::slug -> admin blocked by ReservedSlugs::isReserved`.
- **LandingPage** — 0 tests. No test `GET / (central) -> assertSee plans + platformName + primaryColor`.
- **Email unique/honeypot** — no test `email duplicate -> assertHasErrors email` ni `honeypot filled -> 422`.

## 12. Riesgos pendientes (Observabilidad)

- **Growth sin métricas**: No hay `Log::info('growth.registration', ['slug'=>..., 'plan_id'=>...])` ni `Metrics::increment('growth.registrations')` en `RegisterTenant::register`; `Provisioning` sí loggea `tenant_provisioning_queued`, pero Growth no distingue `free` vs `pending_payment` conversiones.
- **Checkout redirect opaco**: `BillingManager::createCheckoutSession` loggea solo en `InternalBillingProvider`, no en `RegisterTenant`; si `tenant_route` genera URL con `APP_URL` host incorrecto (local con `port`), redirect falla silencioso sin `Log::warning`.
- **PlanManager cache miss**: Si Redis cae, `PlanManager::all()` dispara `Plan::where` sin fallback; `LandingPage` renderiza pricing vacío sin alerta `growth.plans_empty`.
- **Provisioning leak residual**: Si `CreateTenantAction` deja `status=provisioning` tras excepción en `BillingManager` (ver `G003` sin try/catch), el tenant queda huérfano `provisioning` sin `ProvisionTenantJob`? No, `afterCommit` sí dispatch job incluso si `BillingManager` falla después, por lo que tenant queda `pending_payment` pero job ya corre — bien, pero sin `Log::error` con `tenant_id` estructurado.

## 13. Conclusión (Próxima acción accionable)

**Estado 🟡 requiere atención.** Growth es funcional para MVP (wizard + `CreateTenantAction` reutilizada), pero **sin P1 el registro público es abusivo y expone password en wire payload**.

**Próxima acción (48 h):**

1.  Asignar owner a `G001` (Security) y `G002` (Ops). Implementar QW-1 (reset password) + QW-2 (throttle en dominio central) + QW-3 (catch slug taken) en rama `fix/growth-p1`; pasar `php artisan test --filter=RegisterTenant --compact` + `composer lint`.
2.  En paralelo, cachear `PlanManager` (QW-4) y añadir honeypot (QW-5); re-ejecutar esta auditoría (IDs G001-G002) y, si pasan, promover a 🟢 y planificar Fase 1 (G003-G006) en sprint; preservar IDs `G001`–`G010` sin reutilizar serie `B`/`P`/`O`/`C`.

> **Nota de mantenimiento**: Este informe preserva IDs `G001`–`G010` históricos. No reutilizar serie `G` en `docs/modules/billing.md` (serie `B`), `provisioning.md` (serie `P`), `operations.md` (serie `O`) ni `catalog.md` (serie `C`). Próxima auditoría (`Tenant/Access` o `Platform/Tenancy`) debe usar series `A001`/`T001`.
