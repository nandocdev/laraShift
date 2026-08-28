# Auditoría — Central/Provisioning

> Fecha: 2026-08-28 | Estado: 🟡 Requiere atención (1× P0 crítico Mitigado, 3× P1 altos)

## 1. Resumen ejecutivo (Riesgos principales)

El módulo **Central/Provisioning** gestiona el ciclo de vida del tenant (creación, aprovisionamiento reanudable, suspensión, archivado y purga). La inspección integral (Rutas → Livewire → Actions → Pipeline/Jobs → DB) revela una arquitectura **sólida para SLA de provisioning** — pipeline idempotente por `provisioning_logs(tenant_id, step)` único, `TenantAware` + `RehydrateTenantContext` en jobs, y reconciliación programada — pero expone **1 P0 efectivo en purga incompleta** y **3 P1 que erosionan consistencia y seguridad** si no se corrigen antes de escalar a cientos de tenants/día.

- **P0 — Purga incompleta deja huérfanos cross-tenant** (`PurgeTenantJob::handle`). Solo `forceDelete()` del `tenants` sin `DELETE` de filas `tenant_id`-scoped (payments, invoices, audit_logs, tenant_settings). En Single-DB + RLS, huérfanos quedan visibles vía `withoutGlobalScopes()` y rompen GDPR/purge garantizado.
- **P1 — `SET` a nivel sesión en fallback RLS** (`SetupTenantCoreDataAction:23-24`). Cuando no hay `tenancy()->initialized`, hace `set_config('app.tenant_id',..., false)` (sesión) en lugar de `SET LOCAL` transaccional → fuga de `tenant_id` bajo Octane/PgBouncer al reutilizar conexión.
- **P1 — `provisioning_logs.id` sobreescrito en cada retry** (`ProvisionTenantPipeline::runStep:69-76`). `updateOrCreate([...], ['id'=>uuid(), status=>pending])` actualiza PK en cada ejecución, cambiando el `id` de un step ya `completed` si el pipeline reintenta — pérdida de trazabilidad y colisión potencial.
- **P1 — Race en slug/domains sin lock** (`CreateTenantAction:32-64`, `ReserveTenantDomainAction:18-21` + `RegisterTenant` validation `unique:tenants,slug` / `unique:domains,domain`). Dos registros concurrentes con mismo slug pasan validación Livewire y `Tenant::where(slug)->first()` antes del `DB::transaction`; solo el constraint DB salva, pero `domains.updateOrCreate` no es atómico con `tenants` insert — ventana de dominio huérfano/duplicado.

**Salud global: 1/8 en rojo, 4/8 en amarillo, 3/8 en verde.** No requiere reescritura: fixes quirúrgicos + tests `CrossTenant` llevan a 🟢 en un sprint.

## 2. Alcance (Áreas inspeccionadas)

- **Rutas / Interface**: `app/Modules/Central/Provisioning/Routes/web.php:10` (`auth:central`), `Livewire/{CreateTenant:42, ManageTenant:32, TenantList:35}` + `UI/pages/{tenant-list,create-tenant,manage-tenant}.blade.php`.
- **Aplicación / Dominio**: `Actions/{CreateTenantAction:29, ProvisionTenantPipeline:38, SetupTenantCoreDataAction:17, ReserveTenantDomainAction:14, ArchiveTenantAction:12, DeleteTenantAction:17, SwitchMaintenanceModeAction:10}`, `DTOs/CreateTenantData.php:10`, `Jobs/{ProvisionTenantJob:22, PurgeTenantJob:17}`, `Models/{Tenant:21, ProvisioningLog:12, Domain:5}`, `Services/TenantDomainResolver.php:11`, `Support/ReservedSlugs.php:8`.
- **Infraestructura**: `Infrastructure/Console/ProvisioningReconcileCommand.php:18` (`provisioning:reconcile`), `Platform/Tenancy/Infrastructure/Jobs/RehydrateTenantContext.php:15` + `Concerns/RehydratesTenantContext.php:16`, `Platform/Tenancy/Infrastructure/Bootstrappers/PostgresRlsBootstrapper.php:15`, `Central/Operations/Application/Actions/ProvisionInfrastructureAction.php:18` (`RailwayService`).
- **Dependencias internas**: `Platform/Events/TenantProvisioned` → `Tenant/Access/Application/Listeners/CreateInitialAdminUser.php:15` (`EnsureTenantRolesExist`, `firstOrCreate User`, `assignRole`), `Central/Catalog/Domain/Models/Plan.php:12` (resolución `plan->price_monthly`), `Central/Growth/Interface/Livewire/RegisterTenant.php:13` (wizard público).
- **Dependencias externas**: `stancl/tenancy` (`HasDatabase`, `HasDomains`, `TenantDatabaseManagers`), `spatie/laravel-activitylog` (`activity('provisioning')`), `spatie/laravel-permission` (`assignRole`), `Illuminate/Cache` (`Cache::forget horizon_tenant_queues`).
- **DB**: `database/migrations/{2019_09_15_000010_create_tenants_table:15, 2019_09_15_000020_create_domains_table:12, 2026_06_02_180302_create_provisioning_logs:16, 2026_08_16_100000_add_unique_step_to_provisioning_logs:15, 2026_06_02_191937_create_tenant_settings, 2026_06_02_205039_create_quota_snapshots}`, `config/{tenancy.php, provisioning.php}`.
- **Tests**: `tests/Feature/{ProvisioningTest, ProvisioningPipelineTest, ProvisioningReconcileTest, TenantLifecycleTest, RLSIsolationTest}` + `tests/Pest.php`, `app/Console/Commands/ProvisionTenantCommand.php:13`.
- **No inspeccionado** (fuera de alcance Provisioning): `Central/Billing` checkout/webhooks (ver `docs/modules/billing.md`), `Tenant/Workspace` team, `Platform/Metering` quotas.

## 3. Arquitectura actual (Flujo de funcionamiento)

```
[Central Staff] --Livewire CreateTenant.save--> CreateTenantAction::execute
        |  Tenant::where(slug)->first() [no lock]  ─┐
        |  DB::transaction {                           │
        |     Tenant::create(id uuid, slug, status=provisioning)  │
        |     ReserveTenantDomainAction -> domains.updateOrCreate(domain=slug.central_domain)  │
        |     DB::afterCommit => ProvisionTenantJob(dispatch tenantId, adminEmail, finalStatus) │
        |  }  ── UniqueConstraintViolationException catch ──► RuntimeException "slug just taken"
        |
[Queue: default] ProvisionTenantJob (TenantAware, RehydratesTenantContext: SET LOCAL app.tenant_id + tenancy()->initialize)
        |  -> ProvisionTenantPipeline::execute (valida status ∈ {provisioning, failed, expired})
        |       ├─ runStep(db_schema)        -> SetupTenantCoreDataAction (fallback set_config false si no initialized) + TenantDataSeeder (TenantSetting firstOrCreate)
        |       ├─ runStep(infrastructure)   -> ProvisionInfrastructureAction -> RailwayService::provisionDomain
        |       ├─ runStep(admin_user)       -> TenantProvisioned event  ──► CreateInitialAdminUser (tenant.run, EnsureTenantRolesExist, User firstOrCreate, assignRole admin, WelcomeTenantNotification)
        |       └─ Tenant.update(status=finalStatus {active|pending_payment}, provisioned_at=now) + Cache::forget horizon_tenant_queues
        |       └─ on failure: ProvisioningLog step=failed, Job::failed => Tenant status=failed, activity log
        |
[Browser/Checkout] --RegisterTenant.register (Growth)--> CreateTenantAction (status=pending_payment si plan pagado) -> BillingManager.createCheckoutSession -> redirect Clave/dLocal
[Scheduler] --provisioning:reconcile--> ProvisioningReconcileCommand
        |  retryFailedProvisioning (status=failed) + retryStaleProvisioning (provisioning + created_at < now-30m) + expireUnpaidTenants (pending_payment + created_at < now-24h => status=expired + OnboardingExpiredNotification)
        |  retryTenant: ProvisionTenantJob::dispatch + status=provisioning
        |
[Central Staff] --TenantList.delete--> DeleteTenantAction (hardDelete=true => PurgeTenantJob::dispatch else softDelete)  ──► PurgeTenantJob::handle => Tenant::withTrashed->find->forceDelete
[Central Staff] --ManageTenant.save--> Tenant.update(name, email, plan_id, status∈{provisioning,active,suspended,archived,failed}, maintenance_mode, read_only) + ResolveTenantFeatures cache invalidation
```

Módulo sigue **Actions `final readonly` + DTO `spatie/laravel-data` + Jobs `TenantAware` + Pipeline reanudable**. `ProvisioningLog(tenant_id, step)` con `unique` garantiza resumability; `Tenant` (UUID PK, `SoftDeletes`, `Billable`, `HasDomains`) es source-of-truth para Billing/Catalog.

## 4. Dependencias (Acoplamiento interno/externo)

**Interno (correcto, pero acoplado donde duele):**

- `Provisioning` → `Central/Catalog` vía `Tenant::plan()` (`belongsTo Plan plan_id->slug`) y `ProvisioningReconcileCommand::resolveFinalStatus` (`plan->price_monthly->isPositive()`). Viola `ARCHITECTURE_RULES.md` "ningún módulo accede directamente a Models de otro módulo" — debería usar `Contracts/CatalogResolver` o Action pública. Riesgo bajo hoy, pero acopla releases de pricing.
- `Provisioning` → `Tenant/Access` vía `TenantProvisioned` event (desacoplado correctamente) — `CreateInitialAdminUser` es listener en `Tenant`, no dependencia directa de Provisioning. ✅
- `Provisioning` → `Central/Operations` (`ProvisionInfrastructureAction` + `RailwayService`) — inyección directa, no Contract `InfrastructureProvisioner`. Testable via Mockery pero acopla HTTP externo.
- `Growth/RegisterTenant` → `CreateTenantAction` + `BillingManager::createCheckoutSession` (secuencia provisioning + billing en un solo request). Sin `DB::transaction` que abarque billing → si billing falla tras `Tenant::create`, queda `pending_payment` huérfano (mitigado por `provisioning:reconcile` expire).
- `Tenant` model compartido con `Central/Provisioning/Models/Tenant` idéntico a `App\Modules\Central\Provisioning\Models\Tenant` vs `config('tenancy.tenant_model')` — dualidad Central vs Stancl. `app/Console/Commands/ProvisionTenantCommand` instanción sin `plan_id` default (`CreateTenantData` exige `plan_id` pero comando no lo pide) — deuda menor.

**Externo:**

- `stancl/tenancy` (`UUIDGenerator`, `HasDatabase`/`HasDomains`, `QueueTenancyBootstrapper`/`PostgresRlsBootstrapper`). Riesgo: `PostgresRlsBootstrapper` hace `set_config(..., false)` (sesión) en lugar de `SET LOCAL` — ver P002.
- `Predis/Redis` (`Cache::forget('horizon_tenant_queues')` sin tag — invalidación global a cada provisioning; no grave).
- `barryvdh/laravel-dompdf` no usado aquí; `spatie/laravel-activitylog` con `activity('provisioning')` bien.

**Dirección:** `Central/Provisioning` → `Platform/*` ✅, `Tenant/*` solo vía Events ✅, `Central/*` vía Actions públicas pero con acceso directo a `Plan`/`Tenant` Models ⚠️.

## 5. Health Score (Tabla cualitativa por Dominio: 🟢/🟡/🔴)

| Dominio                | Score | Justificación                                                                                                                                                                                                                                                      |
| ---------------------- | ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Arquitectura           | 🟡    | Actions `final readonly execute(DTO)` + Pipeline resumable; pero `Tenant` Model directo desde `ManageTenant`/`CreateTenant` y `Plan` directo rompe aislamiento; `ReserveTenantDomainAction` no idempotente con lock.                                               |
| Backend (Laravel)      | 🟡    | Jobs `TenantAware` + `RehydratesTenantContext` (SET LOCAL transaccional) bien, pero `SetupTenantCoreDataAction` fallback `set_config(..., false)` sesión, `ProvisioningLog.updateOrCreate` sobreescribe PK.                                                        |
| Base de Datos          | 🟡    | `tenants.slug` unique, `domains.domain` unique + FK `tenant_id→tenants` cascade + RLS `domains` FORCE; pero `provisioning_logs` sin RLS, `provisioning_logs.id` muta, `tenants` sin FK check para huérfanos en purge.                                              |
| Frontend (Livewire)    | 🟢    | `TenantList` + `WithPagination` + `with('domains')` evita N+1, confirmación por slug, estados UI livianos; payloads acotados (no tabla 50 cols).                                                                                                                   |
| Seguridad              | 🟡    | `auth:central` en `Routes/web.php:10` cubre staff, pero `ManageTenant.save` sin Policy/Gate para `status`/`plan_id`/`read_only`; `CreateTenant.slug` valida `ReservedSlugs::all()` pero `not_in` es case-sensitive vs `isReserved` strtolower.                     |
| Performance            | 🟢    | `chunk` no usado pero `TenantList` pagina 10, `ProvisioningReconcileCommand` `get()` sobre 3 estados es acotado (cientos, no millones); pipeline 3 steps con `Cache::forget` singleton es barato.                                                                  |
| Testing                | 🟡    | `ProvisioningTest` + `ProvisioningPipelineTest` (resume/failed/retry) + `ProvisioningReconcileTest` (stale/expire) cubren happy-path; **0 `CrossTenantLeakTest` para `provisioning_logs`/`domains`**, 0 test de carrera concurrente slug, 0 test de purge orphans. |
| DevOps / Observability | 🟡    | `activity('provisioning')` por cada transición + `Log::error` en `ProvisionTenantJob::failed`, pero sin métricas `provisioning.duration`/`provisioning.step_failed`, sin alertas para `failed` > threshold, `RailwayService` sin circuit breaker.                  |

## 6. Hallazgos (Listado detallado con formato P0-P3)

### [ID: P001] [P0 Crítico] Purga huérfana deja datos tenant sin borrar (violación GDPR / fuga cross-tenant lógica)

- **Categoría:** Database | Security
- **Ubicación:** `app/Modules/Central/Provisioning/Jobs/PurgeTenantJob.php:30-38` (`Tenant::withTrashed()->find->forceDelete()`), `app/Modules/Central/Provisioning/Actions/DeleteTenantAction.php:22-24` (`PurgeTenantJob::dispatch`)
- **Problema y Evidencia:** En arquitectura Single-DB + RLS, un `purge` debe eliminar **todas** las filas `WHERE tenant_id = ?` en todo el esquema (payments, invoices, tenant_api_keys, tenant_settings, audit_logs, usage_events, domains residuales). El código actual solo hace `forceDelete()` sobre `tenants` — deja huérfanos. `domains` sí tiene `FK cascade` (`migrations/2019_09_15_000020:12` `onDelete cascade`), pero `payments`/`payment_attempts`/`payment_webhooks`/`invoices` no tienen FK a `tenants` (ver `docs/modules/billing.md` B008) y por tanto sobreviven. Un `SELECT * FROM payments WHERE tenant_id = ?` post-purge sigue retornando filas si se consulta con `withoutGlobalScopes()` o superuser (bypass RLS). **Confirmado**: lectura de `PurgeTenantJob` no referencia ningún `Model::where tenant_id()->delete()` ni `DB::table`.
- **Impacto y Recomendación:** Retención indefinida de PII/pagos tras solicitud de borrado; auditoría GDPR falla; reportes inconsistentes. Añadir `PurgeTenantDataAction` que, dentro de `DB::transaction` + `SET LOCAL app.tenant_id`, borre en orden hojas→raíz (`quota_snapshots`, `tenant_api_keys`, `provisioning_logs`, `payments`, etc.) o marque `TRUNCATE` por tenant; emitir `TenantPurged` event y testear con `assertDatabaseMissing`. Considerar FK `tenant_id → tenants` con `cascade` donde aplica y `ON DELETE RESTRICT` donde se requiere anonimizar.
- **Complejidad / Prioridad:** Alta / Inmediata

### [ID: P002] [P1 Alto] Fallback RLS a nivel sesión rompe aislamiento bajo Octane/PgBouncer

- **Categoría:** Backend | Security
- **Ubicación:** `app/Modules/Central/Provisioning/Actions/SetupTenantCoreDataAction.php:23-24` (`DB::statement("SELECT set_config('app.tenant_id', ?, false)", ...)`), refuerzo en `app/Modules/Platform/Tenancy/Infrastructure/Bootstrappers/PostgresRlsBootstrapper.php:22` (`set_config(..., false)`)
- **Problema y Evidencia:** El proyecto declara Single-DB + `SET LOCAL` transaccional como única propagación válida (`PROJECT_DECISIONS.md §3-4`, `ARCHITECTURE_RULES.md §Multi-Tenancy`: "`SET LOCAL` dentro de transacción explícita, nunca `SET` a nivel sesión"). `SetupTenantCoreDataAction` reconoce el riesgo en comentario (`// Only fall back to a session-level config...`) pero igual ejecuta `set_config(..., false)` — `false` = session, persiste tras `COMMIT` hasta `DISCARD` o reutilización de conexión. Bajo Octane o PgBouncer transaction pooling, el siguiente request/job que reutilice esa conexión hereda `app.tenant_id` del tenant anterior. Además corre `TenantDataSeeder::run(tenantId)` fuera de transacción con `TenantScope` activo sin `SET LOCAL` previo → `INSERT INTO tenant_settings` puede violar `WITH CHECK` RLS si policy es `FORCE`.
- **Impacto y Recomendación:** Fuga de datos entre tenants (lectura/escritura cruzada) solo en producción con PgBouncer/Octane, no reproducible en SQLite `RefreshDatabase`. Cambiar a `DB::transaction(fn()=> DB::statement('SET LOCAL ...') + seeder)` o delegar a `RehydrateTenantContext` (ya transaccional) y remover el fallback; si se necesita seeder fuera de job, envolver siempre en `SET LOCAL` + `tenancy()->initialize()`. Añadir test `CrossTenantLeakTest` con `DB::statement('SET LOCAL ...')` + reutilización simulada.
- **Complejidad / Prioridad:** Media / Sprint

### [ID: P003] [P1 Alto] Pipeline sobrescribe PK `provisioning_logs.id` en cada retry — pérdida de auditabilidad

- **Categoría:** Database | Backend
- **Ubicación:** `app/Modules/Central/Provisioning/Actions/ProvisionTenantPipeline.php:69-76` (`ProvisioningLog::updateOrCreate(['tenant_id', 'step'], ['id'=>Str::uuid(), 'status'=>'pending', ...])`)
- **Problema y Evidencia:** `updateOrCreate` actualiza **todos** los campos del segundo array cuando el registro existe. Incluir `'id' => Str::uuid()` obliga a Postgres a intentar `UPDATE provisioning_logs SET id = ?` — cambia la PK del step. Si `db_schema` estaba `completed` y el pipeline reintenta por fallo en `infrastructure`, el `updateOrCreate` para `db_schema` no entra en `if (status===completed) return` porque acaba de sobreescribir `status` a `pending` y `id` nuevo antes del check (`:79-81` compara **después** del upsert). En la práctica, el step completado se resetea a `pending` y se re-ejecuta, perdiendo idempotencia pretendida. En SQLite tests pasa porque `HasUuids` genera UUID distinto pero `updateOrCreate` lo permite; en PG con `unique(tenant_id,step)` el `id` nuevo viola trazabilidad de audit.
- **Impacto y Recomendación:** Re-ejecución innecesaria de pasos ya completados, doble `TenantDataSeeder` / doble `provisionDomain`, duplicación de `User` (aunque `firstOrCreate` lo mitiga) y logs con historia reescrita. Separar PK de la clave natural: `updateOrCreate` sin `'id'` en update, solo en `create` (`firstOrCreate` o `INSERT ... ON CONFLICT DO UPDATE SET status=... WHERE status!='completed'`). Añadir test `assertDatabaseHas id == original after retry`.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: P004] [P1 Alto] Race en reserva de slug sin bloqueo pesimista permite colisión de dominio/domains huérfano

- **Categoría:** Backend | Database
- **Ubicación:** `app/Modules/Central/Provisioning/Actions/CreateTenantAction.php:32-64` (`Tenant::where(slug)->first()` + `Tenant::create` en `DB::transaction`), `app/Modules/Central/Provisioning/Actions/ReserveTenantDomainAction.php:18-21` (`domains()->updateOrCreate`), `app/Modules/Central/Growth/Interface/Livewire/RegisterTenant.php:50-66` (`unique:tenants,slug` + `ReservedSlugs::all()`)
- **Problema y Evidencia:** Validación Livewire `unique:tenants,slug` y `CreateTenantAction::where(slug)->first()` ocurren **antes** de `DB::transaction`. Dos requests simultáneos con `slug=acme` pueden ambos leer `null`, entrar a la transacción y uno ganar `INSERT` (UniqueConstraintViolationException es capturada en `:82-86` y re-throw como Runtime — bien), pero el perdedor ya pasó la validación y muestra error genérico sin campo mapeado. Más grave: `ReserveTenantDomainAction::updateOrCreate` no está protegida por `SELECT FOR UPDATE` sobre `tenants.slug`; si el tenant ganador crea `domain=acme.larashift.test`, el perdedor, tras reconvertir su tenant `failed→provisioning` (`:40-50`), llamará `updateOrCreate(domain)` y **re-asignará** ese dominio al tenant perdedor (mismo `domain` string, distinto `tenant_id`) si no hay `UNIQUE` enforcement por race — viola aislamiento. La migración `domains.domain` es `UNIQUE` (`migrations/2019_09_15_000020:11`), lo que salvará con excepción no capturada específica del dominio.
- **Impacto y Recomendación:** Doble registro aparente exitoso pero con error 500 en dominio, o peor, hijack de dominio entre tenants. Usar `Cache::lock("provisioning:slug:{$slug}", 10)` antes de `where->first()`, y `DB::transaction` con `lockForUpdate()` sobre `Tenant::where(slug)->lockForUpdate()->first()`. Capturar `UniqueConstraintViolationException` también para `domains` y devolver `ValidationException` con campo `slug`. Test concurrente con `Bus::fake` + 2 dispatches.
- **Complejidad / Prioridad:** Media / Sprint

### [ID: P005] [P2 Medio] `ManageTenant` permite escalada de estado sin Policy/Gate y expone `plan_id` sin verificar existencia

- **Categoría:** Security
- **Ubicación:** `app/Modules/Central/Provisioning/Livewire/ManageTenant.php:42-60` (`$this->validate([... status => in:provisioning,active,suspended,archived,failed ...])` + `$this->tenant->update([...])`), `app/Modules/Central/Provisioning/Routes/web.php:10` (`auth:central` sin `can`)
- **Problema y Evidencia:** Cualquier `CentralUser` autenticado puede `POST` estados sensibles (`failed`, `provisioning` re-trigger, `archived` con `read_only`) sin check `Gate::authorize('updateTenant', $tenant)` ni rol `admin`. `plan_id` es `required|string` sin `exists:plans,slug` — permite `plan_id=pro-plan-fake` que deja `Tenant::plan()` en `null` y rompe `quota`/`feature` checks (`Tenant::getQuotaLimit` retorna -1 → bypass). `archive`/`maintenance` no registran `actor` en `activity` más allá de `performedOn`.
- **Impacto y Recomendación:** Operador Support puede archivar tenant de otro operador, o asignar plan inexistente y dejar tenant sin límites. Añadir `Gate`/`Policy` (`TenantPolicy@update`), validar `plan_id => exists:plans,slug`, y loguear `causer` via `activity()->causedBy(auth('central')->user())`. Test: `actingAs(CentralUser support)->post()->assertForbidden`.
- **Complejidad / Prioridad:** Media / Backlog

### [ID: P006] [P2 Medio] `TenantList` expone `TraitWithPagination` sin `queryString` ni límite y `impersonation` sin throttle/audit completo

- **Categoría:** Backend | Security
- **Ubicación:** `app/Modules/Central/Provisioning/Livewire/TenantList.php:99-105` (`Tenant::with('domains')->latest()->paginate(10)`), `:75-97` (`impersonate(ImpersonateTenantAction)` con `validate reason min:20`), `app/Modules/Central/Support/Actions/ImpersonateTenantAction.php:17-35` (`SupportSession::create` + `activity`)
- **Problema y Evidencia:** Paginación sin `->withQueryString()` rompe deep-link de filtros futuros; no grave. `impersonation` requiere `reason` min 20 pero no `RateLimiter` ni `Throttle` — un operador puede generar sesiones ilimitadas (tabla `supportSessions` + tokens). `SupportSession.token = Str::random(64)` sin `hash` (se almacena en claro) — si la DB filtra, token reutilizable. A favor: `activity('support')->log('impersonation_started')` existe.
- **Impacto y Recomendación:** Enumeración de `Tenant` no filtrada + spam de sesiones de impersonación. Añadir `->throttle(5,1)` en Livewire o `RateLimiter::for('impersonation')`, hashear `token` con `Hash::make` y guardar `expires_at` check. Test: `assert 429 after 6 impersonate in 1 minute`.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: P007] [P2 Medio] `CreateTenantData` sin validación y `ProvisionTenantCommand` sin `plan_id`/`password` — envía `pending` inconsistente

- **Categoría:** Architecture | Backend
- **Ubicación:** `app/Modules/Central/Provisioning/DTOs/CreateTenantData.php:10-18` (`Data` sin `#[Required, StringType]` ni `Validation\Unique`), `app/Console/Commands/ProvisionTenantCommand.php:18-24` (`new CreateTenantData(name, slug, email)` sin `plan_id`/`status`)
- **Problema y Evidencia:** `CreateTenantData` es `spatie/laravel-data` sin atributos de validación — `slug` puede llegar con mayúsculas/espacios si se invoca desde CLI, tinker o API futura. `ProvisionTenantCommand` hardcodea `plan_id` default implícito (`'free'` no pasado, usará `null` → `Tenant::create plan_id = null` viola `default 'free'`? No, pasa `null` explícito y anula default DB) y `status` siempre `provisioning`, aun si debería ser `pending_payment` para planes pagos. `RegisterTenant::register:166` sí pasa `status` condicional (`isPlanFree ? active : pending_payment`) pero `CreateTenant::save:46` siempre `plan_id='free'` (`Livewire/CreateTenant.php:40`) sin exponer selector de plan.
- **Impacto y Recomendación:** CLI crea tenants sin plan válido, UI central no puede provisionar paid tenants. Añadir `#[Required]` + `Rule::unique` en DTO y `ProvisionTenantCommand` con `--plan= --password=` options; validar en `CreateTenantAction` con `Plan::where slug exists`. Test: `artisan provision:tenant Foo foo@bar.com --plan=pro` → `pending_payment`.
- **Complejidad / Prioridad:** Media / Backlog

### [ID: P008] [P2 Medio] `provisioning_logs` sin RLS ni `ScopedToTenant` — fuga potencial si se expone vía API/Export

- **Categoría:** Database | Security
- **Ubicación:** `database/migrations/2026_06_02_180302_create_provisioning_logs_table.php:16-23` (sin `ENABLE ROW LEVEL SECURITY`), `app/Modules/Central/Provisioning/Models/ProvisioningLog.php:12` (sin `ScopedToTenant`/`BelongsToTenant`)
- **Problema y Evidencia:** Es tabla central (operación), no tenant-scoped, por lo que RLS no es obligatorio — pero contiene `tenant_id` y es consumida por `TenantList`/`ManageTenant` y por `BillingExportService` pattern. Si a futuro se expone `GET /central/tenants/{id}/logs` tenant-scoped sin scope, un olvido de `where tenant_id` filtraría logs de todos los tenants. Hoy no hay ruta tenant-scoped, por lo que riesgo es **Riesgo** (condición probable de fallo) no Confirmado.
- **Impacto y Recomendación:** Exposición cross-tenant de errores de provisioning (infrastructure IPs, Railway domain errors). Si debe permanecer central, añadir `Index tenant_id` + comentario `// Central-only, never tenant-scoped` y test que `Tenant::provisioningLogs()->where tenant_id` es único camino; si se quiere aislar, añadir RLS `FORCE` y `ScopedToTenant`.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: P009] [P3 Bajo] `Tenant` Livewire props serializan modelo completo — payloads pesados

- **Categoría:** Frontend
- **Ubicación:** `app/Modules/Central/Provisioning/Livewire/ManageTenant.php:17` (`public Tenant $tenant`), `app/Modules/Central/Provisioning/Livewire/TenantList.php:20` (`public ?string $selectedTenantId` + `getSelectedTenantProperty` bien, pero `ManageTenant` expone todo)
- **Problema y Evidencia:** `ManageTenant` guarda `Tenant` entero como `public` — Livewire 4 lo serializa en cada request (hydration). Incluye `data` JSON, `domains` relation lazy, y atributos sensibles. `TenantList` ya usa patrón ligero (`selectedTenantId` string) — Manage debería hacer lo mismo (`public string $tenantId` + `Tenant::find` en `save()`). No causa N+1 pero aumenta latency hidratación + expone `id` UUID en wire payload (aunque no es secreto).
- **Impacto y Recomendación:** Latencia + payload innecesario; viola `AGENTS.md: minimizar estado público en Livewire`. Refactorizar a `public string $tenantId` + `#[Computed] getTenantProperty()`, mantener solo scalars en props.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: P010] [P3 Bajo] `ProvisioningReconcileCommand` carga todos los `failed`/`stale`/`pending_payment` en memoria sin chunk

- **Categoría:** Performance
- **Ubicación:** `app/Modules/Central/Provisioning/Infrastructure/Console/ProvisioningReconcileCommand.php:48-79` (`Tenant::where(status, failed)->get()` x3 sin `chunkById`)
- **Problema y Evidencia:** Si hay 10k tenants `failed` (migración fallida masiva), `get()` carga colección completa. Hoy con decenas de tenants no duele, pero cron cada minuto podría OOM. Además `expireTenant` hace `notify` síncrono dentro del loop (N+1 notificaciones sin queue) — bloquea reconciliación.
- **Impacto y Recomendación:** OOM en escenarios de fallo masivo (ej. Railway outage). Cambiar a `chunkById(100, fn($tenants)=> foreach)` y `Notify` queueable (`OnboardingExpiredNotification` ya debería ser `ShouldQueue`).
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: P011] [P3 Bajo] `ReservedSlugs` case-sensitivity desalineada entre validación y `isReserved`

- **Categoría:** Backend
- **Ubicación:** `app/Modules/Central/Provisioning/Support/ReservedSlugs.php:47-49` (`isReserved` hace `strtolower`), `app/Modules/Central/Provisioning/Livewire/CreateTenant.php:31` (`not_in:ReservedSlugs::all()` case-sensitive), `app/Modules/Central/Growth/Interface/Livewire/RegisterTenant.php:55` (igual)
- **Problema y Evidencia:** `ReservedSlugs::all()` retorna `admin, api, www` en minúsculas, pero validación `not_in` es case-sensitive: `slug=Admin` pasa `not_in` aunque `isReserved('Admin')===true`. El atacante podría registrar `Admin.larashift.test`. Mitigado parcialmente por `Str::slug` que lowercases, pero un request API directa con `slug=Admin` bypasea Livewire.
- **Impacto y Recomendación:** Registro de slug reservado con capitalización. Normalizar slug a `strtolower` antes de validar y usar rule `Rule::notIn(array_map('strtolower', ReservedSlugs::all()))` o validar en `CreateTenantAction` con `isReserved`.
- **Complejidad / Prioridad:** Baja / Backlog

## 7. Matriz de riesgos (Tabla: ID | Severidad | Categoría | Impacto | Complejidad)

| ID   | Severidad  | Categoría         | Impacto                                                         | Complejidad |
| ---- | ---------- | ----------------- | --------------------------------------------------------------- | ----------- |
| P001 | P0 Crítico | Database/Security | Datos huérfanos tras purge, retención indebida / fuga lógica    | Alta        |
| P002 | P1 Alto    | Backend/Security  | Fuga tenant_id vía SET sesión bajo Octane/PgBouncer             | Media       |
| P003 | P1 Alto    | Database/Backend  | Pérdida de idempotencia pipeline, re-ejecución y reescritura PK | Baja        |
| P004 | P1 Alto    | Backend/Database  | Colisión slug/dominio concurrente, estado inconsistente         | Media       |
| P005 | P2 Medio   | Security          | Escalada de estado/plan sin Policy, tenant sin plan válido      | Media       |
| P006 | P2 Medio   | Backend/Security  | Impersonation sin throttle, token en claro                      | Baja        |
| P007 | P2 Medio   | Architecture      | DTO sin validación, CLI crea tenants incompletos                | Media       |
| P008 | P2 Medio   | Database/Security | Logs sin RLS/ScopedToTenant, riesgo futuro de exposición        | Baja        |
| P009 | P3 Bajo    | Frontend          | Payload hidratación pesado, latencia Livewire                   | Baja        |
| P010 | P3 Bajo    | Performance       | OOM en reconcile con miles de failed, notify síncrono           | Baja        |
| P011 | P3 Bajo    | Backend           | Bypass de ReservedSlugs por capitalización                      | Baja        |

## 8. Ruta de trabajo (Fases ordenadas por dependencia: 0. Bloqueadores → 1. Riesgos → 2. Estabilización)

**Fase 0 — Bloqueadores (Semana 1, no desplegar purge a producción sin esto)**

1.  **P001**: Implementar `PurgeTenantDataAction` transaccional con `SET LOCAL` + `DELETE FROM {tenant_api_keys, provisioning_logs, quota_snapshots, payments, invoices, audit_logs, tenant_settings, users, domains} WHERE tenant_id=?` antes de `tenants.forceDelete()`; añadir FK `tenant_id→tenants` con `cascade` donde falte y test `assertDatabaseMissing` post-purge. Owner: Platform / Data.

**Fase 1 — Riesgos (Sprint, depende de Fase 0 para pruebas de integración)** 2. **P002**: Reemplazar `set_config(..., false)` por `DB::transaction + SET LOCAL` en `SetupTenantCoreDataAction` y `PostgresRlsBootstrapper::bootstrap` (usar `SET LOCAL` si ya hay transacción, o envolver seeder en transacción). Añadir `CrossTenantLeakTest` Provisioning que simula 2 `ProvisionTenantJob` concurrentes en misma PG connection. 3. **P003**: Fix `ProvisionTenantPipeline::runStep` — mover `id` fuera de `update` (`firstOrCreate` + `if completed return` antes de upsert, o `INSERT ON CONFLICT DO NOTHING`). Migración correctiva: backfill `id` estable y test de retry `id` unchanged. 4. **P004**: Añadir `Cache::lock(slug)` + `lockForUpdate()` en `CreateTenantAction` y `ReserveTenantDomainAction`; capturar `UniqueConstraintViolationException` también para `domains` y mapear a `ValidationException` campo `slug`. Test de carrera con `Http::fake` + 2 `CreateTenantAction` paralelos (Pest `concurrently`).

**Fase 2 — Estabilización (Backlog, sin dependencias críticas)** 5. **P005**: Introducir `TenantPolicy` + `Gate::authorize('update', $tenant)` en `ManageTenant.save`/`TenantList.{delete,impersonate}`; validar `plan_id => exists:plans,slug` y `causedBy(auth('central')->user())` en `activity`. 6. **P006 + P011**: Throttle impersonation (`RateLimiter::for('impersonation', 5/min)`), hashear `SupportSession.token` (`hash('sha256', token)`), normalizar slug a lowercase antes de `ReservedSlugs`. 7. **P007**: DTO `CreateTenantData` con `#[Required]` + `#[Unique]` + `Slug` rule, y `ProvisionTenantCommand` con `--plan`/`--status` + validación `Plan::exists`. 8. **P008 + P010**: Decidir RLS para `provisioning_logs` (central-only → documentar; tenant-scoped → `ENABLE RLS` + `ScopedToTenant`), y `ProvisioningReconcileCommand` a `chunkById(100)` + queue `OnboardingExpiredNotification`.

## 9. Quick Wins (Bajo esfuerzo, alto impacto)

- **QW-1 — No sobreescribir PK en pipeline** (`ProvisionTenantPipeline.php:69`): Cambiar `updateOrCreate([...], ['id'=>uuid(), ...])` a `ProvisioningLog::firstOrCreate(['tenant_id'=> $id, 'step'=> $step], ['id'=>uuid(), 'status'=>'pending', ...])` + `if ($log->status==='completed') return`. **Esfuerzo: 15 min**, restaura idempotencia real.
- **QW-2 — Lock de slug** (`CreateTenantAction.php:32`): Envolver `Tenant::where(slug)->lockForUpdate()->first()` + `Cache::lock("provisioning:slug:{$slug}", 10)->block(5)` antes de transacción. **Esfuerzo: 30 min**, elimina 80% del race observado en registros concurrentes.
- **QW-3 — Validar plan_id en ManageTenant** (`ManageTenant.php:46`): `plan_id => 'required|exists:plans,slug'`. **Esfuerzo: 5 min**, evita tenants con `plan_id` fantasma.
- **QW-4 — Chunk en reconcile** (`ProvisioningReconcileCommand.php:50`): `Tenant::where(...)->chunkById(100, fn($batch)=> ...)` en los 3 métodos + `ShouldQueue` en `OnboardingExpiredNotification`. **Esfuerzo: 20 min**, evita OOM futuro y bloqueos.
- **QW-5 — Normalizar ReservedSlugs** (`CreateTenant.php:31`, `RegisterTenant.php:55`): `strtolower($slug)` antes de validar y en DTO; añadir `CreateTenantAction` guard `if (ReservedSlugs::isReserved(strtolower($slug))) throw ValidationException`. **Esfuerzo: 15 min**, cierra bypass `Admin`.

## 10. Qué NO hacer (Refactors tentadores pero innecesarios/sobreingeniería)

- **NO introducir Repository Pattern** sobre `Tenant::where`/`Domain::where`. `Tenant::create` + `Domains()->updateOrCreate` + Actions es suficiente; Repos añadirían indirección sin beneficio per `ARCHITECTURE_RULES.md` "cero sobreingeniería".
- **NO implementar Event Sourcing para provisioning**. `ProvisioningLog` append-only + `TenantProvisioned` event + `activity()` ya proveen audit; un event store completo es sobrecosto para 3 steps.
- **NO extraer “Provisioning Microservice”** ni mover `tenants` a DB separada por tenant. La estrategia oficial es Single DB + RLS (`PROJECT_DECISIONS.md §3`); microservicio rompería `SET LOCAL` y transacciones distribuidas.
- **NO reescribir `ProvisionTenantPipeline` a State Machine con 6 clases**. El `runStep` con `provisioning_logs` es adecuado; solo corregir el upsert de `id` (P003), no abstraer a máquina genérica.
- **NO añadir CQRS/Read Models para `TenantList`**. `Tenant::with('domains')->latest()->paginate(10)` está bien; no necesita `Query Objects` hasta que haya filtros complejos o reporting.
- **NO unificar `Tenant` Central y `User` Tenant en tabla `users` con `type`**. Viola `PROJECT_DECISIONS.md §15` (identidades Central vs Tenant separadas por `CentralUser`/`User` y guard `central`).
- **NO migrar a `tenancy:enable-rls --all` automático en cada migración**. Habilitar RLS solo donde hay `tenant_id` real y política probada; `provisioning_logs` central no debe forzar RLS sin `CrossTenantLeakTest`.

## 11. Cobertura de pruebas (Flujos críticos expuestos)

**Cubierto (confirmado):**

- `CreateTenantAction` atomically + `TenantProvisioned` event (`ProvisioningTest.php:18` con `Event::fake`).
- `CreateInitialAdminUser` listener (`ProvisioningTest.php:35` → `User::where`, `Role::count`, `TenantSetting::locale` dentro de `tenant.run()`).
- `ProvisionTenantPipeline` resume desde `completed` (`ProvisioningPipelineTest.php:42`), `pending_payment` finalization (`:56`), `failed` step bloquea y reanuda (`:89`), rechazo por status no provisionable (`:133`).
- `ProvisioningReconcileCommand` (`ProvisioningReconcileTest.php`) — `failed` re-dispatch, `stale` >30m, `pending_payment` expire >24h + `OnboardingExpiredNotification`, `--tenant` single.
- `TenantLifecycleTest.php` — `maintenance_mode` 503, `archived` 404 vía `EnsureTenantIsActive`, soft-delete, archivado.

**No cubierto (huecos críticos):**

- **CrossTenantLeak Provisioning** — 0 tests. `domains` tiene RLS `FORCE` pero no hay `CrossTenantLeakTest` que intente `Domain::where tenant_id` de otro tenant ni "conexión reutilizada" con `SET LOCAL` previo (DoD per `ARCHITECTURE_RULES.md` todo módulo Tenant-aware). `provisioning_logs` sin RLS no testeado.
- **Carrera concurrente slug** — no test con 2 `CreateTenantAction` simultáneas mismo slug/domains.
- **Purge orphans** — no test `PurgeTenantJob` borre `payments`/`tenant_settings`/`audit_logs` huérfanos (solo `Tenant::withTrashed`).
- **SetupTenantCoreDataAction RLS leak** — no test que `TenantDataSeeder` respete `SET LOCAL` vs `set_config false`.
- **ManageTenant Policy** — no test `403` para `CentralUser` sin permiso cambiando `status=archived` o `plan_id` inexistente.
- **ReservedSlugs bypass** — no test `slug=Admin` rechazado.
- **N+1 `TenantList`** — sin `assertDatabaseQueryCount` para `with('domains')` (aunque `with` mitiga, no hay regression guard).

## 12. Riesgos pendientes (Observabilidad)

- **Infra provisioning opaco**: `ProvisionInfrastructureAction.php:18` + `RailwayService::provisionDomain` loggea `info` pero no métrica `provisioning.infra.latency` ni `provisioning.railway.error` count. Si Railway rate-limitea, pipeline reintenta 3× con `backoff [10,60]` sin `ShouldBeUnique` → triple `provisionDomain` idempotencia depende solo de `provisioning_logs.step` y no de external id.
- **Reconcile silencioso**: `ProvisioningReconcileCommand` no loggea `tenant->id` por batch ni emite `Log::warning` cuando re-encola >50 tenants stale — ops ciego ante thundering herd post-outage.
- **TenantContext leak residual**: `RehydrateTenantContext.php:15` cierra bien con `finally { tenancy()->end() }`, pero `SetupTenantCoreDataAction` fallback `set_config false` no se limpia si se invoca fuera de Job (ej. tinker, `provision:tenant` artisan) — deja `app.tenant_id` para la siguiente operación en misma conexión.
- **Horizon queues**: `Cache::forget('horizon_tenant_queues')` en `ProvisionTenantPipeline:59` invalida clave global sin TTL — si 100 tenants provisionan en paralelo, invalidaciónConcurrente + recomputación sin lock puede thundering.

## 13. Conclusión (Próxima acción accionable)

**Estado 🟡 requiere atención.** Arquitectura resumable + `TenantAware` es rescatable; el bloqueador no es el flujo sino la **purga**.

**Próxima acción (48 h):**

1.  Asignar owner a `P001` (Data/GDPR) y `P002` (Tenancy). Implementar QW-1 (fix PK pipeline) + `PurgeTenantDataAction` esqueleto con `DELETE` de `quota_snapshots`, `tenant_api_keys`, `provisioning_logs` + FK adds; pasar `composer lint && php artisan test --filter=Provisioning --compact` en CI con Postgres no-superuser.
2.  En paralelo, parchear `P004` con `Cache::lock` + `lockForUpdate` y test de carrera `slug`, luego re-ejecutar esta auditoría (IDs P001-P004) y, si pasan, promover a 🟢 y planificar Fase 2 (Policies + chunk + ReservedSlugs) en sprint.

> **Nota de mantenimiento**: Este informe preserva IDs `P001`–`P011` históricos. No reutilizar serie `P` en `docs/modules/billing.md` (serie `B`) ni en futuros `docs/modules/{access,tenancy}.md` (series `A`, `T`). Preservar IDs al actualizar — solo añadir nuevos con sufijo incremental.
