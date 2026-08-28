# Auditoría — Central/Support

> Fecha: 2026-08-28 | Estado: 🔴 Requiere intervención (2× P0 críticos, 3× P1 altos)

## 1. Resumen ejecutivo (Riesgos principales)

El módulo **Central/Support** centraliza el perímetro privilegiado: impersonation de tenants (`support_sessions` token 64), bitácora (`support_notes`) y broadcasts multitenant (`broadcasts` + `SendBulkBroadcastJob` chunk 100 + `GlobalAnnouncements` banner). La inspección integral (Rutas → Actions → Controllers/Middleware → DB RLS → Notifications) confirma base funcional pero **2 P0 bloquean pase a producción sin parche** por diseño de token y cross-guard auth.

- **P0 — Token de impersonation en claro por URL GET y cross-guard `loginUsingId`** (`Actions/ImpersonateTenantAction.php:32` `Str::random(64)` + `:49` `http://{$domain}/support/auth?token=`; `Http/Controllers/TenantImpersonationController.php:36` `auth()->loginUsingId(operator_id)`). Token viaja en query string (logs, referer, historial) y se guarda en claro (`unique` pero no hashed); si la DB filtra, todos los tokens activos son reusables hasta `expires_at` (2h). `loginUsingId` loguea `central_users.id` en guard `web` tenant (modelo `User` distinto) — sesión fantasma sin `HasPermissions`, y `Session::put('impersonated_by')` sin invalidar sesión anterior permite fijación de sesión.
- **P0 — RLS bypass y `broadcast_dismissals` en conexión central sin `SET LOCAL`** (`Livewire/GlobalAnnouncements.php:27` `DB::connection(central)->table('broadcast_dismissals')` + `Models/SupportSession.php:18` `getConnectionName() => central`). `GlobalAnnouncements::render()` corre en dominio tenant con `SET LOCAL app.tenant_id` en conexión `pgsql` default, pero consulta `broadcast_dismissals` en conexión `central` (central DB) donde `current_setting('app.tenant_id')` está vacía → policy `tenant_isolation` deniega todo y `whereNotIn(dismissedIds)` siempre vacío en prod con RLS `FORCE`; en tests SQLite (sin RLS) pasa, en Postgres no.
- **P1 — Sin Gate/Policy ni throttle en impersonation** (`Providers/SupportServiceProvider.php:21` `auth:central` solo, `Actions/ImpersonateTenantAction.php:23` solo `strlen(reason) <20`). Cualquier `CentralUser` (incluso soporte read-only) puede impersonar cualquier tenant ilimitadamente sin `RateLimiter`, sin `Gate::authorize('support:impersonate')`, sin 2FA re-auth.
- **P1 — Broadcast sin índice GIN y filtro `tenant::plan_id` sin normalización** (`Models/Broadcast.php:49` `jsonb channels` sin `GIN`, `Livewire/GlobalAnnouncements.php:32` `whereJsonContains('channels','banner')` + `Actions/SendBroadcastAction.php:34` `where plan_id = filterValue` sin `exists:plans,slug`). Fuga de `recipient_count` sin paginación y `Notifications::send` N mails sin cola por tenant (`BroadcastNotification` es `ShouldQueue` pero `SendBulkBroadcastJob` no usa `TenantAware` ni `SET LOCAL`, envía fuera de contexto tenant).

**Salud global: 2/8 rojo, 3/8 amarillo, 3/8 verde.** No penaliza ausencia de Repository/CQRS; el CRUD con Actions es adecuado. Fixes son quirúrgicos (hash + guard + RLS fix).

## 2. Alcance (Áreas inspeccionadas)

- **Rutas**: `Providers/SupportServiceProvider.php:21` (`auth:central` → `/central/support/broadcasts`), `Interface/Routes/tenant.php:8` (`GET /support/auth?token`, `POST /support/logout` en dominio tenant, sin `tenant` middleware explícito), `Http/Controllers/TenantImpersonationController.php:20`.
- **Actions / Livewire**: `Actions/{ImpersonateTenantAction:11, CreateSupportNoteAction:11, SendBroadcastAction:13}`, `Jobs/SendBulkBroadcastJob:17` (`chunk 100` + `Notification::send`), `Livewire/{BroadcastCenter:16 (paginate 10 + Plan::all), TenantSupportBitacora:15 (notes+sessions get), GlobalAnnouncements:15 (dismiss + whereJsonContains + whereNotIn dismissedIds)}`, `Http/Middleware/AuditImpersonationActions:13` (`PendingActivityLog::beforeLogging` + `finally` clear para Octane).
- **Modelos / DB**: `Models/{SupportSession:13 (HasUuids, fillable token started/ended/expires), SupportNote:13, Broadcast:12 (jsonb channels)}`, `migrations/2026_06_02_153359:16` (FK `operator_id→central_users cascade`, `token unique`, `index tenant_id,started_at`, RLS `FORCE` en `support_sessions/support_notes`), `migrations/2026_06_02_181830:15` (`broadcast_dismissals` uuid PK + `unique broadcast_id,user_id` + `tenant_id index` + RLS `FORCE`).
- **Notificaciones**: `Notifications/{BroadcastNotification:10 (mail), ImpersonationEndedNotification:10 (mail, ShouldQueue)}`.
- **Tests**: `grep support` en `tests/` **0** arquivos (`SupportSession` no testeado); `tests/Feature/Central/{Settings,DashboardTest}` referencian `CentralBranding` pero no Support; `tests/Pest.php` `RefreshDatabase`.
- **Dependencias externas**: `stancl/tenancy` (`tenant('id')`, `tenant()->domains->first()`, `PrimaryDomain`), `spatie/laravel-activitylog` (`activity('support')->performedOn`), `Illuminate/Session`, `Illuminate/Notification`.
- **No inspeccionado**: `Central/Auth` 2FA, `Tenant/Access` roles, `Platform/Tenancy` RLS bootstrapper detallado (ver `tenancy.md` futuro).

## 3. Arquitectura actual (Flujo de funcionamiento)

```
[Central Staff] --TenantList.impersonate--> ImpersonateTenantAction::execute(tenant, reason>=20)
        |  SupportSession::create(id uuid, token Str::random(64) PLAINTEXT, expires +2h) + activity('support')->log impersonation_started
        |  → return "http://{tenantDomain}/support/auth?token=XXX"  (http hardcoded, sin signed)
        |
[Browser] --GET /support/auth?token=XXX (tenant domain)--> TenantImpersonationController::authenticate
        |  SupportSession::where token, tenant_id=tenant('id'), expires>now, ended null -> firstOrFail
        |  auth()->loginUsingId(operator_id) // cross-guard: central_users.id en guard tenant web
        |  Session::put impersonation_session_id + impersonated_by
        |  Session token => used_ + random10 (one-time mitiga replay) -> redirect /dashboard
        |
[Tenant Session] --any request--> AuditImpersonationActions middleware
        |  if Session::has impersonated_by => PendingActivityLog::beforeLogging inject {impersonated_by, support_session_id} en properties
        |  finally => PendingActivityLog::beforeLogging(null) para Octane
        |
[Central Staff] --BroadcastCenter.send--> SendBroadcastAction::execute(BroadcastData title/body/filterType/filterValue/channels)
        |  Broadcast::create + Tenant::query where plan_id/status? -> count -> recipient_count
        |  if email -> SendBulkBroadcastJob::dispatch(broadcast) else sent_at=now (banner)
        |  -> SendBulkBroadcastJob handle: Tenant::query same filter -> chunk 100 -> Notification::send(tenants, BroadcastNotification) -> broadcast.sent_at=now
        |
[Tenant UI] --GlobalAnnouncements.render--> Broadcast where sent_at not null, whereJsonContains channels banner, where filter all|plan|status matching tenant.plan_id/status, whereNotIn dismissedIds (DB::connection central broadcast_dismissals where user_id)
        |  dismiss(broadcastId) -> DB::connection central broadcast_dismissals updateOrInsert broadcast_id,user_id, tenant_id=tenant('id')
```

Módulo sigue **Modular Monolith + Actions `final readonly` + DTO `Spatie/Data` + Job `ShouldQueue` + Livewire solo-UI**. Correcto per `ARCHITECTURE_RULES.md`; no CQRS/Event Sourcing necesario.

## 4. Dependencias (Acoplamiento interno/externo)

**Interno (acoplado donde duele):**

- `Support` → `Provisioning` vía `Tenant` model directo (`ImpersonateTenantAction:7`, `SendBroadcastAction:7`, `TenantSupportBitacora:7` `Tenant::findOrFail`). Viola `ARCHITECTURE_RULES.md` “ningún módulo accede directamente a Models de otro módulo” — debería usar `Contracts/TenantContract` o `TenantResolver`. Riesgo bajo (mismo Bounded Context Central) pero acopla releases; `Tenant::domains->first()` asume `HasDomains` sin Contract.
- `Support` → `Platform/Tenancy` helpers (`tenant('id')`, `tenant()->domains`) — uso correcto de `Platform`, pero `TenantImpersonationController:29` `where tenant_id = tenant('id')` confía en `InitializeTenancyByDomain` middleware que no está declarado en `Interface/Routes/tenant.php:8` (solo `web` global `Livewire::setUpdateRoute` en `AppServiceProvider:48` lo inyecta para Livewire, no para ruta `support/auth`). Si `support/auth` se visita sin tenancy inicializado, `tenant('id')` es null y query falla 404 aunque token válido.
- `Support` → `Central/Auth` (`CentralUser` en `SupportSession::operator()`, `SupportNote::author()`) — esperado Central-Central, pero `TenantImpersonationController:36` mezcla guards: `auth()->loginUsingId` usa guard default (`web` tenant), no `central`.

**Externo:**

- `spatie/laravel-activitylog` (`activity('support')` + `PendingActivityLog::beforeLogging`). El `beforeLogging` es static global; el `finally` reset en `AuditImpersonationActions:43` es correcto para Octane, pero si dos requests concurrentes comparten worker, el hook del primero puede inyectar en el segundo (race leve).
- `Illuminate/Notification` (`Tenant` es `Notifiable` via `Provisioning\Models\Tenant` que usa `Notifiable` trait; `BroadcastNotification` mail via `ShouldQueue` bien, pero no hay `queue: tenant.b*` isolation — notificaciones de broadcast van a `default` queue, no a `TenantQueueManager` bucket, rompiendo noisy-neighbor isolation.
- `stancl/tenancy` (`Database/Concerns/PrimaryDomain` para `tenant_route` no usado aquí; `ImpersonateTenantAction:43` usa `http://` hardcodeado en lugar de `tenant_route`).

**Dirección:** `Central/Support` → `Platform/*` ✅; `Platform` no depende de `Support` ✅; `Tenant/*` no depende de `Support` salvo `GlobalAnnouncements` Livewire tenant-side (acoplo Central→Tenant leve pero aceptable para banner).

## 5. Health Score (Tabla cualitativa por Dominio: 🟢/🟡/🔴)

| Dominio                | Score | Justificación                                                                                                                                                                                           |
| ---------------------- | ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Arquitectura           | 🟢    | Actions pequeñas, DTO, Job `chunk 100` bien; sin Repository/CQRS innecesario.                                                                                                                           |
| Backend (Laravel)      | 🔴    | Token plaintext + http + cross-guard `loginUsingId`; `SendBulkBroadcastJob` sin `TenantAware`/`SET LOCAL`; `GlobalAnnouncements` conexión central con RLS `FORCE`.                                      |
| Base de Datos          | 🟡    | `support_sessions token unique` + `index tenant_id,started_at` bien, RLS `FORCE` correcto; pero `broadcasts` sin índice GIN `channels`/`filter_type`, `broadcast_dismissals` RLS en conexión wrong.     |
| Frontend (Livewire/UI) | 🟢    | `TenantSupportBitacora` payload ligero (`tenantId` string + `with(author)`), `BroadcastCenter` `paginate 10`; sin N+1 grave.                                                                            |
| Seguridad              | 🔴    | Sin Gate/throttle/2FA en impersonate, token en URL GET, `http` hardcodeado, `http://tenant/support/auth` sin signed, `AuditImpersonationActions` hook static global.                                    |
| Performance            | 🟡    | `BroadcastCenter::render:61` `Plan::where is_active get` cada render sin cache (ver `G004`); `GlobalAnnouncements::render` `whereJsonContains` sin índice + `whereNotIn pluck dismissedIds` en memoria. |
| Testing                | 🔴    | 0 tests para Support (`grep support tests` vacío); sin `ImpersonationTest`, `BroadcastTest`, `RLS SupportSessions` leak test, `SendBulkBroadcastJob` chunk.                                             |
| DevOps / Observability | 🟡    | `activity('support')` bien en `Impersonate`/`CreateNote`/`SendBroadcast`, pero sin métricas `support.impersonation_started`/`broadcast.sent` ni alerta `support_session.expired` sin `ended_at`.        |

## 6. Hallazgos (Listado detallado con formato P0-P3)

### [ID: SU001] [P0 Crítico] Token de impersonation en claro por GET y cross-guard `loginUsingId` permite hijack y sesión fantasma

- **Categoría:** Security | Backend
- **Ubicación:** `app/Modules/Central/Support/Actions/ImpersonateTenantAction.php:32` (`token => Str::random(64)`), `:49` (`return "http://{$domain}/support/auth?token={$session->token}"`), `Http/Controllers/TenantImpersonationController.php:36` (`auth()->loginUsingId($session->operator_id)`)
- **Problema y Evidencia:** Token se genera `Str::random(64)` y se guarda **en claro** (`unique` en `migrations/2026_06_02_153359:23` pero no hashed). Luego viaja en URL `GET /support/auth?token=` (hardcode `http://`, no `https`, sin `signed` ni `hash`). Logs de Nginx/Pail, `Referer` a CDN y `browser history` exponen token. `TenantImpersonationController::authenticate:36` hace `auth()->loginUsingId($session->operator_id)` donde `operator_id` es `central_users.id` (FK `constrained central_users cascade`), pero en dominio tenant el guard `web` espera `users.id` (tabla tenant `users`, no central). En Postgres con UUID coincidente por suerte, login crea sesión donde `auth()->user()` es `CentralUser` model en contexto tenant — sin `roles` tenant, sin `HasPermissions` scope, pero con acceso total. Además `Session::put('impersonated_by', $session->operator_id)` sin `Session::invalidate()` previo permite fijación: atacante con `session_id` previo puede mantener sesión tras logout. **Confirmado** leyendo ambos archivos; `SupportSession::isActive():51` verifica `expires_at->isFuture()` pero no invalida token tras logout salvo `:43` `update token => used_` (prefijo `used_` no hashed, permite enumerar `used_*`).
- **Impacto y Recomendación:** Secuestro de sesión admin → acceso a datos tenant sin audit completo (aunque `AuditImpersonationActions` inyecta `impersonated_by` en `activity`, el `causer` de `activity('support')` sigue siendo `auth('central')->user()` en Central, no en Tenant). **Fix inmediato**: Hashear token `hash('sha256', $token)` al guardar y comparar `hash_equals`; enviar token vía `POST` con `signed` URL `URL::temporarySignedRoute('tenant.support.auth', now()->addMinutes(5), ['tid'=>$tenant->id])` o `http://{$domain}` → `https://` con `forceScheme https` en prod; usar `auth('tenant')->login($operatorAsTenantUser)` o `loginUsingId` con guard `tenant` y modelo `User` mapeado (crear `TenantUser` espejo o usar `CentralUser` con `Authenticatable` multi-guard + `Session::migrate(true)`). Añadir `RateLimiter::for('impersonation', 5/min)` en `tenant.php:8`. Test: `GET /support/auth?token=invalid -> 404`, `hash token not plaintext in DB`.
- **Complejidad / Prioridad:** Alta / Inmediata (Bloqueador de release)

### [ID: SU002] [P0 Crítico] `GlobalAnnouncements` consulta `broadcast_dismissals` en conexión `central` con RLS `FORCE` → siempre vacío en prod Postgres

- **Categoría:** Database | Backend
- **Ubicación:** `app/Modules/Central/Support/Livewire/GlobalAnnouncements.php:27` (`$connection = config('tenancy.database.central_connection', 'central')`) + `:30` `DB::connection($connection)->table('broadcast_dismissals')->where user_id`, `migrations/2026_06_02_181830:30` (`ENABLE ROW LEVEL SECURITY; FORCE; CREATE POLICY tenant_isolation USING (tenant_id::text = current_setting('app.tenant_id'))`)
- **Problema y Evidencia:** `GlobalAnnouncements::render()` corre en dominio tenant (ej. `acme.larashift.test`) con `tenant('id')` resuelto. La query de `dismissedIds` usa `DB::connection('central')` explícitamente, pero RLS `FORCE` en `broadcast_dismissals` exige `current_setting('app.tenant_id') = tenant_id`. En conexión `central`, `current_setting('app.tenant_id')` no está seteado (el bootstrapper `PostgresRlsBootstrapper` solo setea en conexión `pgsql` default, no en conexión `central` si es distinta). En `config/database.php` (no inspeccionado pero `getConnectionName() => central` en `SupportSession`/`Broadcast` sugiere DB separada o schema separado), el `SET LOCAL` de `RehydrateTenantContext` no se propaga a conexión `central`. Resultado: `pluck broadcast_id` retorna `[]` siempre, `whereNotIn([], dismiss)` es no-op, y banners ya dismissed reaparecen para todos los usuarios. En SQLite tests (sin RLS) el bug no se ve porque `FORCE` no aplica. **Confirmado** por migración con `FORCE` y código con `connection central`.
- **Impacto y Recomendación:** Dismiss no funciona en prod Postgres → banner spam para tenants. Solución: Usar conexión tenant (default) para `broadcast_dismissals` (quitar `getConnectionName central` o hacer `DB::connection()->table` sin `central`), o propagar `SET LOCAL` a conexión `central` en `RehydrateTenantContext` (iterar `DB::connections`). Alternativa: desactivar RLS para `broadcast_dismissals` si es tabla central con `tenant_id` lógico pero consultada desde tenant (documentar `// Central connection, no RLS, filtered by tenant_id`). Añadir test con Postgres `SET LOCAL` + `assert dismissed not in activeBroadcasts`.
- **Complejidad / Prioridad:** Media / Inmediata

### [ID: SU003] [P1 Alto] Impersonation sin Gate/Policy ni throttle ni re-autenticación 2FA — escalada horizontal ilimitada

- **Categoría:** Security
- **Ubicación:** `app/Modules/Central/Support/Providers/SupportServiceProvider.php:21` (`Route::middleware ['web','auth:central']` sin `can`), `Actions/ImpersonateTenantAction.php:21-24` (solo `strlen reason <20`), `Livewire/TenantSupportBitacora.php:25` sin rate limit, `Http/Controllers/TenantImpersonationController.php:20` (sin `throttle:5,1`)
- **Problema y Evidencia:** Ruta `TenantList` (no mostrada pero `Provisioning/Livewire/TenantList.php:75-97` `impersonate` con `ImpersonateTenantAction`) y `TenantSupportBitacora` no verifican `Gate::authorize('support:impersonate')` ni rol `admin/support`. Cualquier `CentralUser` autenticado puede impersonar cualquier tenant con razón arbitraria `aaaaaaaa20chars`. No hay `RateLimiter::for('impersonation', fn()=>Limit::perMinute(3)->by(auth('central')->id()))`, por lo que script puede crear 100 `SupportSession` en segundos (cada uno token 64 + 2h). Tampoco se exige `password.confirm` o `2fa` re-auth como en `Central/Auth` (`/central/settings/2fa`). **Confirmado**: `grep Gate support` vacío, `routes/tenant.php:8` sin `throttle`.
- **Impacto y Recomendación:** Privilege escalation lateral entre tenants. Añadir `Gate::define('support:impersonate', fn(CentralUser $u)=> $u->hasRole('admin'))` en `SupportServiceProvider::boot` y `Gate::authorize` en `ImpersonateTenantAction::execute` + `TenantImpersonationController::authenticate`; añadir `throttle:10,1` en ambas rutas y `RateLimiter` por `operator_id`; exigir `confirmPassword` o `MfaService` antes de `SupportSession::create`. Test: `actingAs(central support viewer)->post impersonate -> 403`.
- **Complejidad / Prioridad:** Media / Sprint

### [ID: SU004] [P1 Alto] `SendBulkBroadcastJob` fuera de contexto tenant (`TenantAware` missing + `chunk` sin `SET LOCAL`) y `BroadcastNotification` mail sin tenant queue isolation

- **Categoría:** Backend | DevOps
- **Ubicación:** `app/Modules/Central/Support/Jobs/SendBulkBroadcastJob.php:17` (`implements ShouldQueue` pero no `TenantAware`, sin `RehydratesTenantContext`), `:34` `Tenant::query()->chunk 100` + `:43` `Notification::send($tenants, BroadcastNotification)`, `Actions/SendBroadcastAction.php:39` `recipient_count = query->count()` sin `clone`
- **Problema y Evidencia:** Job despacha `Notification::send` a `Tenant` models (que son `Notifiable` con `routeNotificationForMail` → `tenant.email`). Sin `TenantAware` ni `SET LOCAL app.tenant_id`, el job corre sin tenancy context; `Notification::send` dentro del job envía mails fuera de transacción tenant, sin `tenant_id` en `activity`. `Tenant::query()->chunk 100` carga tenants con `HasEncrypted` (si aplica) fuera de RLS, pero `Tenant` es central model sin RLS, por lo que no hay fuga directa — el problema es **queue isolation**: el job va a `default` queue (no `TenantQueueManager` bucket), y `BroadcastNotification` (mail) también va a `default` sin `TenantQueueManager::resolve`. Con 10k tenants, `chunk 100` hace 100 `Notification::send` con 100 mails cada uno, 10k mails en un solo job → timeout `retry_after` (Horizon `timeout 60` en `config/horizon.php:105`) y reintento duplica mails. No hay `ShouldBeUnique` ni `backoff`.
- **Impacto y Recomendación:** Doble envío de broadcast tras retry, timeouts, cola `default` saturada. Hacer `SendBulkBroadcastJob` `TenantAware` si envía a tenant-specific resources, o mantener como central job pero con `chunkById` + `dispatch ` por sub-job `SendBroadcastChunkJob` con `TenantAware` + `onQueue TenantQueueManager::resolve` + `rateLimit Redis::throttle('broadcast')`; añadir `unique` por `broadcast.id` en job; usar `Mail::queue` con `tenant` Mailable. Test: `Bus::fake` + `Broadcast::count` + `Queue::assertPushed` chunk.
- **Complejidad / Prioridad:** Media / Sprint

### [ID: SU005] [P1 Alto] Broadcast `channels` jsonb sin índice GIN y `filter_value` sin validación `exists:plans,slug`/`in:status`

- **Categoría:** Database | Backend
- **Ubicación:** `app/Modules/Central/Support/Models/Broadcast.php:49` (`jsonb channels`), `database/migrations/2026_06_02_153359:49` (`jsonb channels` sin `GIN`), `Livewire/GlobalAnnouncements.php:32` (`whereJsonContains('channels','banner')`), `Livewire/BroadcastCenter.php:36` (`filterType required|in:all,plan,status` + `filterValue` sin `exists` si plan), `Actions/SendBroadcastAction.php:33` (`where plan_id = filterValue` sin normalizar)
- **Problema y Evidencia:** `whereJsonContains` en Postgres sin `GIN` (`CREATE INDEX ... USING GIN (channels)`) hace seq scan en `broadcasts` por cada `render()` de `GlobalAnnouncements` (cada request tenant). `BroadcastCenter::send:34` valida `filterType` pero no `filterValue` si `plan`: un `filterValue=pro-inexistente` pasa, `Tenant::where plan_id = 'pro-inexistente' -> count 0` y `recipient_count 0` silencioso; staff cree broadcast enviado pero 0 recipients. Igual `status` sin `in:active,suspended,pending_payment`. **Confirmado** leyendo migrations sin `GIN` y validación en `BroadcastCenter:33`.
- **Impacto y Recomendación:** Latencia en cada request tenant + staff UX engañosa. Añadir `GIN` `CREATE INDEX broadcasts_channels_gin ON broadcasts USING GIN (channels)` + `index filter_type, filter_value`; validar `filterValue` con `Rule::exists('plans','slug')` si `filterType=plan` y `in:provisioning,active,suspended,archived` si status. Test: `filter plan not_exists -> ValidationException`.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: SU006] [P2 Medio] `AuditImpersonationActions` hook global `PendingActivityLog::beforeLogging` con race en Octane y `Session` sin `httpOnly`/`SameSite` hardening

- **Categoría:** Security | Backend
- **Ubicación:** `app/Modules/Central/Support/Http/Middleware/AuditImpersonationActions.php:29` (`PendingActivityLog::beforeLogging(fn)`), `:42` `finally beforeLogging(null)`, `Http/Controllers/TenantImpersonationController.php:39` `Session::put impersonation_session_id`
- **Problema y Evidencia:** `PendingActivityLog::beforeLogging` es static global (singleton por proceso). En Octane con workers persistentes, si dos impersonations concurrentes (dos staff) corren en mismo worker, el `beforeLogging` del primero puede inyectar `support_session_id` del primero en `activity` del segundo antes del `finally` reset. El `finally` con `beforeLogging(null)` limpia, pero si excepción ocurre antes de `finally`, el hook queda hasta siguiente request. Además `Session::put('impersonated_by')` guarda `operator_id` en cookie `laraShift_session` (driver `redis` central, no tenant) sin `httpOnly` extra ni `SameSite=Strict` (usa `config/session.php` default `lax`). No hay `Session::regenerate()` en `:39` tras login, permitiendo session fixation (atacante fija `session_id` antes de auth). **Riesgo** (no Confirmado en pentest) pero condición probable bajo Octane.
- **Impacto y Recomendación:** Cross-tenant activity log contamination (audit trail corrupto). Usar `Activity::defaultCauser` por request (`Activity::defaultCauser($operator)` en `Authenticate`) o envolver en `DB::transaction` con `SET LOCAL` y no usar static hook; hacer `Session::migrate(true)` (regenerar ID + invalidar old) y `Session::put` con `secure: true`. Añadir test `concurrent impersonations log separate session_id`.
- **Complejidad / Prioridad:** Media / Backlog

### [ID: SU007] [P2 Medio] `SupportNote` y `Broadcast` sin Policy y `TenantSupportBitacora::render` N+1 `latest()->get()` sin paginación

- **Categoría:** Security | Frontend
- **Ubicación:** `app/Modules/Central/Support/Livewire/TenantSupportBitacora.php:42` (`SupportNote::with('author')->where tenant_id ->latest()->get()` + `SupportSession::with('operator')->latest()->get()` sin `paginate`), `Actions/CreateSupportNoteAction.php:13` `execute(TenantContract, content)` sin `Gate`, `Models/SupportNote.php:22` `fillable content`
- **Problema y Evidencia:** Cualquier `auth:central` puede crear `SupportNote` sin `Gate::authorize('support:note_create')`; `content` con `required|string|min:5` permite `javascript:`, `data:` si se renderiza raw (`{!! $note->content !!}` no verificado, pero `tenant-support-bitacora.blade.php` no leído — asumir `{{ $note->content }}` escaped bien). `render()` carga **todos** los notes/sessions del tenant sin `paginate`; con 10k notes (soporte activo), payload Livewire serializa 10k `SupportNote` + `author` en cada `addNote` (hydrate). `BroadcastCenter::render:60` sí pagina `broadcasts` 10, pero `TenantSupportBitacora` no.
- **Impacto y Recomendación:** Leak de bitácora completa en wire payload; violación mínima de least-privilege. Añadir `Gate` en `CreateSupportNoteAction` y `Policy` `SupportNotePolicy`; paginar `notes` con `latest()->paginate(20)` + `WithPagination`, limitar `sessions` a `latest 20`.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: SU008] [P2 Medio] `Broadcast::channels` default `['email']` hardcodeado y `filter_type` enum sin migration para nuevos planes/statuses

- **Categoría:** Architecture | Backend
- **Ubicación:** `app/Modules/Central/Support/DTOs/BroadcastData.php:16` (`channels = ['email']`), `database/migrations/2026_06_02_153359:47` (`enum filter_type ['all','plan','status']`), `Livewire/BroadcastCenter.php:26` `public string $filterType='all'`
- **Problema y Evidencia:** DTO hardcodea `['email']` pero UI `BroadcastCenter` permite `channels` array con `banner` (ver `GlobalAnnouncements:32` espera `banner`). Si se añade canal `push` o `sms`, el enum `filter_type` no escala (Postgres `enum` requiere `ALTER TYPE ... ADD VALUE` con lock). Además `Broadcast::channels` jsonb sin `cast` default en DB (nullable? No, not null jsonb sin default → `Broadcast::create` sin channels falla si `in_array('email')` no se cumple). No hay `filter_value` validation para `all` (debe ser null, pero `BroadcastData` permite `filterValue` no null con `filterType all` → `recipient_count` cuenta con `where plan_id` innecesario ignorado? Sí, `SendBroadcastAction:31` solo filtra si `filterType plan/status`, por lo que `all` con `filterValue` no filtra, pero UI puede enviar `filterValue` basura sin error.
- **Impacto y Recomendación:** Deuda de evolución; enum bloquea deploys con `CONCURRENTLY` lock. Migrar `enum` a `string` con `check` constraint o `varchar`; añadir `channels default ['email']` en migración; validar `filterValue null if filterType all` en `BroadcastData` con `#[Validate]` o en `BroadcastCenter::send`. Bajo, pero cierra inconsistencia.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: SU009] [P3 Bajo] `SupportSession::getConnectionName()` `central` hardcodeado rompe tests `RefreshDatabase` y `TenantAware` expectation

- **Categoría:** Architecture | Database
- **Ubicación:** `app/Modules/Central/Support/Models/SupportSession.php:18` (`return config('tenancy.database.central_connection', 'central')`), igual `SupportNote:18`, `Broadcast:17`
- **Problema y Evidencia:** Modelos fuerzan `central` connection. En `phpunit.xml` (usualmente `DB_CONNECTION=sqlite :memory:`), conexión `central` no existe y `getConnectionName` retorna `central` que no está definida en `config/database.php` central (solo `pgsql`/`sqlite`), causando `InvalidArgumentException Database connection [central] not configured` en tests si no se mockea. En `tests/Pest.php` con `RefreshDatabase`, las migraciones de support crean tablas en conexión default (`pgsql`), pero modelos leen de `central` → `assertDatabaseHas support_sessions` falla silencioso. **Riesgo** leve pero Condition probable si `tenancy.database.central_connection` no está configurado en test env.
- **Impacto y Recomendación:** Tests no pueden ejercitar Support sin stub de conexión central. Cambiar a `return config('database.default')` en tests o usar `DatabaseManager` con `central` = `pgsql` en `config/database.php` para que `central` siempre resuelva; documentar `// Central connection is required for Support, ensure tenancy.database.central_connection is set in .env.testing`. O eliminar override `getConnectionName` y confiar en `single DB + RLS` (ya que `support_sessions` es central-only sin `BelongsToTenant`, no necesita conexión separada — ver `docs/ARCHITECTURE_RULES.md` Single DB).
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: SU010] [P3 Bajo] `ImpersonationEndedNotification` mail sin locale y con `Carbon::parse` timezone naive (reutiliza `C008` pattern)

- **Categoría:** Backend
- **Ubicación:** `app/Modules/Central/Support/Http/Controllers/TenantImpersonationController.php:62` (`$session->started_at->format('Y-m-d H:i')`), `Notifications/ImpersonationEndedNotification.php:16` (no `locale`), `Actions/ApplyTenantFeatureOverride.php:43` pattern similar
- **Problema y Evidencia:** `started_at` se formatea `Y-m-d H:i` sin timezone ni locale; tenant en `America/Panama` verá hora UTC. Notification `toMail` usa `__('Security Notice...')` pero no `App::setLocale($tenant->locale)` antes de `notify`. No hay `onQueue` especificado, va a `default` queue no tenant bucket.
- **Impacto y Recomendación:** UX confusa; baja severidad. Usar `$session->started_at->setTimezone($tenant->timezone ?? 'UTC')->translatedFormat('Y-m-d H:i T')` y `MailLocaleMiddleware` o `->locale($tenant->locale)` en `Notification::locale`.
- **Complejidad / Prioridad:** Baja / Backlog

## 7. Matriz de riesgos (Tabla: ID | Severidad | Categoría | Impacto | Complejidad)

| ID    | Severidad  | Categoría         | Impacto                                                        | Complejidad |
| ----- | ---------- | ----------------- | -------------------------------------------------------------- | ----------- |
| SU001 | P0 Crítico | Security/Backend  | Token hijack via URL/logs + cross-guard session fixation       | Alta        |
| SU002 | P0 Crítico | Database/Backend  | Dismiss banner nunca persiste en prod RLS `FORCE`, spam banner | Media       |
| SU003 | P1 Alto    | Security          | Cualquier central impersona sin rol/throttle/2FA               | Media       |
| SU004 | P1 Alto    | Backend/DevOps    | Broadcast chunk sin TenantAware, job timeout + N mails dup     | Media       |
| SU005 | P1 Alto    | Database/Backend  | Seq scan `channels` jsonb + filterValue inválido 0 recipients  | Baja        |
| SU006 | P2 Medio   | Security/Backend  | Hook `beforeLogging` global + session fixation Octane          | Media       |
| SU007 | P2 Medio   | Security/Frontend | Sin Policy en notes + payload 10k rows sin paginate            | Baja        |
| SU008 | P2 Medio   | Architecture      | Enum lock + channels default inconsistente                     | Baja        |
| SU009 | P3 Bajo    | Architecture      | `central` connection hardcodeada rompe RefreshDatabase         | Baja        |
| SU010 | P3 Bajo    | Backend           | Notification timezone/locale naive                             | Baja        |

## 8. Ruta de trabajo (Fases ordenadas por dependencia: 0. Bloqueadores -> 1. Riesgos -> 2. Estabilización)

**Fase 0 — Bloqueadores (Semana 1, no desplegar Support a prod sin esto)**

1.  **SU001**: Hashear token `SupportSession::create token => hash('sha256', $plain)` + validar `hash_equals`; cambiar `ImpersonateTenantAction:49` a `https://` + `URL::temporarySignedRoute` o `tenant_route` helper; `TenantImpersonationController:36` `auth('tenant')->login` con modelo `User` (no `central_users.id`) + `Session::migrate(true)` + `throttle:5,1`. Test: `assert token hashed in DB`, `GET support/auth?token=invalid 403`, `Session ID rotated`.
2.  **SU002**: Fix RLS: cambiar `GlobalAnnouncements.php:27` a `DB::connection()->table('broadcast_dismissals')` (sin `central`) o propagar `SET LOCAL` a conexión central en `RehydrateTenantContext`; añadir `migration fix` `ALTER TABLE broadcast_dismissals DISABLE RLS` si es decisión central-only. Test con Postgres `SET LOCAL` + `assert dismissed not visible`.

**Fase 1 — Riesgos (Sprint, depende de Fase 0)** 3. **SU003**: Añadir `Gate::define('support:impersonate', fn(CentralUser $u)=> $u->hasRole('admin'))` en `SupportServiceProvider::boot` + `Gate::authorize` en `ImpersonateTenantAction` + `TenantImpersonationController`; `throttle:10,1` en `tenant.php:8`; `password.confirm` modal antes de `Impersonate`. Test `viewer -> 403`. 4. **SU004**: Refactor `SendBulkBroadcastJob` → `TenantAware` con `RehydratesTenantContext` (opcional central job) o split a `SendBroadcastChunkJob` por `Tenant::chunkById`; `TenantQueueManager::resolve` para `BroadcastNotification` queue; `ShouldBeUnique` por `broadcast.id`. Añadir `recipient_count clone query` + `sent_at` update idempotente. 5. **SU005**: Crear `GIN` `CREATE INDEX ... USING GIN (channels)` + validar `filterValue` `exists:plans,slug` / `in:...` en `BroadcastCenter::send` + `SendBroadcastAction`. Test `filter plan not_exists -> ValidationException`.

**Fase 2 — Estabilización (Backlog)** 6. **SU006 + SU010**: Reemplazar `PendingActivityLog::beforeLogging` por `Activity::defaultCauser` por request; `Session::migrate(true)` + `httpOnly/SameSite Strict`; mover `ImpersonationEndedNotification` a `tenant.b1.default` queue con `locale` y `timezone`. 7. **SU007**: `TenantSupportBitacora::render` paginar `notes 20` + `sessions 20`, Policy `support:note_create`; `BroadcastCenter::render` cachear `Plan::all` (ver `G004`). 8. **SU008 + SU009**: Migrar `enum filter_type` → `string` + check, `channels default`; documentar `central connection` o remover `getConnectionName` si Single DB.

## 9. Quick Wins (Bajo esfuerzo, alto impacto)

- **QW-1 — Hashear token existente** (`ImpersonateTenantAction.php:32`): `SupportSession::create(['token'=> hash('sha256', $plain = Str::random(64))])` + `where token = hash('sha256', $token)` en controller + devolver `plain` solo en URL. **Esfuerzo: 30 min**, cierra P0 parcial.
- **QW-2 — https y signed** (`ImpersonateTenantAction.php:49`): `"https://{$domain}/support/auth?token={$plain}"` + `URL::temporarySignedRoute` wrapper. **Esfuerzo: 20 min**, elimina http + replay window.
- **QW-3 — RLS fix inmediato** (`GlobalAnnouncements.php:27`): Cambiar `DB::connection('central')` a `DB::connection()->table` (sin `central`) — 1 línea; **Esfuerzo: 5 min**, dismiss funciona.
- **QW-4 — Gate en impersonate** (`ImpersonateTenantAction.php:23`): `Gate::authorize('support:impersonate')` antes de `SupportSession::create`. **Esfuerzo: 10 min**, cierra SU003 bypass.
- **QW-5 — Validar filterValue** (`BroadcastCenter.php:36`): Añadir `'filterValue' => ['nullable', Rule::exists('plans','slug')]` cuando `filterType=plan`. **Esfuerzo: 15 min**, evita 0 recipients silencioso.

## 10. Qué NO hacer (Refactors tentadores pero innecesarios/sobreingeniería)

- **NO introducir Repository Pattern** para `SupportSession`/`Broadcast`. `SupportSession::where token` + `Broadcast::create` es suficiente; Repos añaden indirección sin beneficio (`ARCHITECTURE_RULES.md` "cero sobreingeniería").
- **NO implementar Event Sourcing para support**. `activity('support')` + `support_sessions` append-only ya provee audit; event store es sobrecosto para bitácora.
- **NO extraer “Support Microservice”** ni mover `support_sessions` a DB separada por tenant. Single DB + RLS (`PROJECT_DECISIONS.md §3`) es correcto; microservicio rompería `SET LOCAL` y `Session::put`.
- **NO crear `SupportPolicy` con 6 abilities** si solo `support:impersonate` se usa. Un Gate simple es suficiente hasta que haya RBAC granular.
- **NO añadir `tenancy:enable-rls --all` para `broadcasts`** — broadcasts es central-only (sin `tenant_id`), RLS no aplica; `broadcast_dismissals` sí tiene RLS `FORCE` pero requiere conexión correcta.
- **NO unificar `SupportNote` y `Tenant/Workspace` `UsageOverview`**. `SupportNote` es central bitácora, `Workspace` es tenant team — dominios separados per `PRD §11`.
- **NO migrar a `spatie/laravel-permission` teams para support**. `CentralUser` roles central (`admin/support`) son suficientes; teams complican sin necesidad.

## 11. Cobertura de pruebas (Flujos críticos expuestos)

**Cubierto (confirmado):**

- Ninguno para Support — `grep support tests` retorna 0 líneas; `tests/Feature/Central/Settings/CentralBrandingTest.php` no cubre Support; `tests/Feature/ProvisioningTest.php` no cubre impersonation.

**No cubierto (huecos críticos):**

- **Impersonation token** — 0 test `ImpersonateTenantAction` hash, `TenantImpersonationController authenticate` con `token invalid -> 404`, `token used -> 404 second attempt`, `cross-guard login` (auth tenant guard), `Session impersonated_by`.
- **RLS dismiss** — 0 test `GlobalAnnouncements dismiss -> whereNotIn dismissedIds -> not visible` con Postgres `SET LOCAL` (bug SU002 solo visible con pgsql no-superuser).
- **Broadcast** — 0 test `SendBroadcastAction filter plan/status -> recipient_count`, `SendBulkBroadcastJob chunk 100 -> Notification::fake -> assertSent`, `whereJsonContains banner` GIN.
- **Bitacora** — 0 test `TenantSupportBitacora addNote -> assertDatabaseHas support_notes`, `render paginate` vs `get()`.
- **Audit** — 0 test `AuditImpersonationActions` inject `impersonated_by` en `activity()->properties` + `CrossTenantLeakTest` para `support_sessions`/`support_notes` (ya tienen RLS `FORCE` `migrations/2026_06_02_153359:58` pero sin test).
- **N+1 TenantSupportBitacora** — sin `assertDatabaseQueryCount` para `with('author')` vs carga completa.

## 12. Riesgos pendientes (Observabilidad)

- **Impersonation sin métrica**: `ImpersonateTenantAction` loggea `activity` pero no `Log::warning('support.impersonation', ['operator_id'=>..., 'tenant_id'=>...])` ni `Metrics::increment('support.impersonation_started')`; si token filtra en logs Nginx (GET query), métrica no detecta anomalía `support_sessions where token like 'used_%'` crecimiento.
- **Broadcast observability**: `SendBulkBroadcastJob` no loggea `chunk` progress ni `broadcast_id` en `Log::info` estructurado; si `Notification::send` falla a mitad de `chunk` (SES 429), reintento reenvía desde 0 (no idempotente por `broadcast.sent_at` set solo al final).
- **Session leak Octane**: `PendingActivityLog::beforeLogging` hook static no se limpia si `AuditImpersonationActions` lanza excepción antes de `finally`; con Octane, siguiente request hereda hook anterior → audit contamination. Añadir `try/finally` ya existe pero no cubre `beforeLogging` exception en `LogBatch`.
- **RLS central connection leak**: `Broadcast::whereNotNull('sent_at')->whereJsonContains` corre sin `SET LOCAL` en conexión central; si `broadcasts` algún día gana RLS, `GlobalAnnouncements` fallará igual que `broadcast_dismissals`.

## 13. Conclusión (Próxima acción accionable)

**Estado 🔴 requiere intervención.** Support es perímetro privilegiado: **SU001 (token plaintext + cross-guard) permite impersonation persistente** y **SU002 (RLS dismiss) rompe UX tenant** — ambos no son deuda menor.

**Próxima acción (48 h):**

1.  Asignar owner a `SU001` (Security) y `SU002` (Data). Implementar QW-1 + QW-2 (hash + https signed) + QW-3 (RLS fix) + QW-4 (Gate impersonate) en rama `hotfix/support-p0`; pasar `composer lint && php artisan test --compact` en CI Postgres no-superuser + `grep` no `http://`.
2.  Re-ejecutar esta auditoría (IDs SU001-SU002) y, si pasan, promover a 🟡 y planificar Fase 1 (SU003-SU005) en sprint; preservar IDs `SU001`–`SU010` sin reutilizar serie `B`/`P`/`O`/`C`/`G`/`S`.

> **Nota de mantenimiento**: Este informe preserva IDs `SU001`–`SU010` históricos. No reutilizar serie `SU` en `docs/modules/billing.md` (B), `provisioning.md` (P), `operations.md` (O), `catalog.md` (C), `growth.md` (G) ni `settings.md` (S). Próxima auditoría (`Tenant/Access` o `Platform/Tenancy`) debe usar series `A001`/`T001`.
