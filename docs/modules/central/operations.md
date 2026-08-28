# Auditoría — Central/Operations

> Fecha: 2026-08-28 | Estado: 🟡 Requiere atención (0× P0, 2× P1 altos)

## 1. Resumen ejecutivo (Riesgos principales)

El módulo **Central/Operations** es el más pequeño del monolito (8 archivos PHP) y actúa como _glue_ de infraestructura: provisioning externo del dominio vía `RailwayService`, resolución de colas de Horizon por buckets, y health-check central. La inspección integral (Rutas → Controllers → Actions/Clients → Horizon/Queues) confirma **código limpio y sin P0**, pero expone **2 P1 que bloquean escalabilidad real** y **deuda de observabilidad** que deja a Ops ciego ante degradación.

- **P1 — `RailwayService` es un stub sin implementación y sin contrato de error** (`Infrastructure/Clients/RailwayService.php:42`). El `try/catch` retorna `always true`; la mutación GraphQL está comentada y nunca se envía HTTP. `ProvisionInfrastructureAction` (`Application/Actions/ProvisionInfrastructureAction.php:28`) loggea `info` y continua siempre, por lo que `ProvisionTenantPipeline` marca `infrastructure=completed` aunque Railway haya fallado → dominio nunca existe y no hay retry.
- **P1 — Health-check inutilizable para sondas de orquestador y filtra `Queue::size()` global** (`Interface/Http/Controllers/HealthCheckController.php:24`). Ruta bajo `auth:central` (`Routes/web.php:8`) + check IP interno contradictorio → `GET /central/health` exige sesión central, imposible para `/up` de Kubernetes/ELB. Además `checkQueue(): 82` mide solo `Queue::size()` default, no los 15 buckets `tenant.b*. *`, ocultando back-pressure real.

**Salud global: 2/8 amarillo, 6/8 verde.** Módulo correcto para MVP, pero requiere completar el adapter Railway y arreglar health/queues antes de 1k tenants.

## 2. Alcance (Áreas inspeccionadas)

- **Rutas / Interface**: `Interface/Routes/web.php:8` (`auth:central` → `GET /central/health`), `Interface/Http/Controllers/HealthCheckController.php:15` (`__invoke` con `checkDatabase/checkRedis/checkQueue`).
- **Aplicación / Dominio**: `Application/Actions/ProvisionInfrastructureAction.php:11` (`execute(Tenant)` → `RailwayService::provisionDomain`), `Application/Services/TenantQueueManager.php:10` (`resolve()` buckets `tenant.b{1-5}.{priority}`).
- **Infraestructura**: `Infrastructure/Clients/RailwayService.php:12` (GraphQL stub, `Http` comentado), `Infrastructure/Horizon/HorizonQueueResolver.php:7` (static 19 queues), `Infrastructure/Console/HorizonUpdateCommand.php:12` (`horizon:update-queues` con `Cache::forever`).
- **Config / Horizon**: `config/horizon.php:105` (`queue => HorizonQueueResolver::resolve()`, `supervisor-1` balance `auto`, `maxProcesses 1→10`), `config/infrastructure.php:4` (solo `health.allowed_ips`, sin `railway.*`).
- **Dependencias internas**: `Central/Provisioning` (`Models/Tenant`, `Actions/ProvisionTenantPipeline.php:26` inyecta `ProvisionInfrastructureAction`), `Platform/Observability/Health/HealthChecker.php:7` (solo `checkDatabaseConnection`, no usado por Operations).
- **Dependencias externas**: `Illuminate/Http::Http`, `Illuminate/Support/Facades/Redis`, `Queue`, `Cache`, `Laravel/Horizon`, `Predis`.
- **DB**: `database/migrations/0001_01_01_000002_create_jobs_table.php` (única tabla usada; Operations no crea tablas propias).
- **Tests**: `tests/Feature/Central/OperationsTest.php:1` (health requires auth + queue bucket tests), `tests/Feature/HorizonQueueResolutionTest.php:1` (19 queues), `tests/Pest.php`, `app/Console/Commands/ProvisionTenantCommand.php` no cubre Operations.
- **No inspeccionado** (fuera de alcance Operations): `Central/Billing` recurring (`billing.md` B004), `Central/Provisioning` pipeline reanudable (`provisioning.md` P003), `Tenant/Workspace` UI.

## 3. Arquitectura actual (Flujo de funcionamiento)

```
[Central Staff/Provisioning] --ProvisionTenantPipeline.runStep(infrastructure)--> ProvisionInfrastructureAction::execute(Tenant)
        |  Tenant.domains()->first()?->domain == null ? skip
        |  RailwayService::provisionDomain(tenant, domain)   // <-- stub: log info + return true, GraphQL comentado
        |  activity('infrastructure')->log('infrastructure_provisioned')
        |
[Queue] TenantQueueManager::resolve(tenant, priority) -> "tenant.b{crc32(id)%5+1}.{priority}"
        |  HorizonQueueResolver::resolve() -> ['default','notifications','broadcasts','webhooks-priority']
        |                                   + tenant.b{1..5}.{high,default,low}  (=19 queues static)
        |  config/horizon.php supervisor-1 queue=resolver() balance=auto maxProcesses 10 (prod)
        |
[Ops/SRE] --GET /central/health--> HealthCheckController::__invoke
        |  if allowed_ips not empty && request.ip not in list => 403
        |  checks = {database: DB::getPdo(), redis: Redis::ping(), queue: Queue::size() >1000 ? warn}
        |  status = any fail ? degraded (503) : healthy (200)
        |
[Deploy] --artisan horizon:update-queues--> HorizonUpdateCommand::handle
        |  HorizonQueueResolver::resolve() sort vs Cache::forever('horizon_active_queues_list')
        |  if force || changed => Cache::forever + Artisan::call('horizon:terminate')
```

Módulo sigue **Modular Monolith + Actions inyectables** (`final readonly class` en `ProvisionInfrastructureAction`), sin lógica de negocio — solo _wiring_ de infraestructura. Correcto per `ARCHITECTURE_RULES.md`: no repository, no CQRS.

## 4. Dependencias (Acoplamiento interno/externo)

**Interno (acoplado donde duele):**

- `Operations` → `Provisioning` vía `Tenant` model directo (`ProvisionInfrastructureAction.php:8` `use Provisioning\Models\Tenant` + `TenantQueueManager.php:8`). Viola `ARCHITECTURE_RULES.md` "ningún módulo accede directamente a Models de otro módulo" — debería depender de `Platform/Contracts/TenantContract` o `TenantDomainResolverContract`. Riesgo bajo hoy (mismo Bounded Context Central) pero acopla releases: cambiar `tenants` table rompe Operations sin contrato.
- `Provisioning` → `Operations` (Pipeline inyecta `ProvisionInfrastructureAction`) — dependencia circular suave Central↔Central permitida vía Actions públicas, pero sin Contract `InfrastructureProvisioner` (mock en `ProvisioningPipelineTest.php:101` requiere `Mockery::mock(RailwayService::class)` acoplado a implementación).
- `HealthCheckController` → `Platform/Observability/Health/HealthChecker` no usado; duplicación de `checkDatabase` (`HealthChecker.php:9` vs `HealthCheckController.php:49`).

**Externo:**

- `RailwayService` → `Http` (GraphQL `backboard.railway.app/graphql/v2`) timeout 5s comentado, retry 1 no usado; `Log::info` sin `correlationId`.
- `HorizonQueueResolver` → `Cache`/`Redis` (`HorizonUpdateCommand:43` `Cache::forever`) sin tag `tenant:` ni TTL → clave global comparte todos los workers.
- `HealthCheckController` → `Redis::connection()->ping()` exige extensión `phpredis` o `predis`; maneja `class_exists('Redis')` check (`:65`) bien.

**Dirección:** `Central/Operations` → `Platform/*` ✅ vía `TenantContract` pendiente; `Platform` no depende de `Operations` ✅.

## 5. Health Score (Tabla cualitativa por Dominio: 🟢/🟡/🔴)

| Dominio                | Score | Justificación                                                                                                                                                                                                                                |
| ---------------------- | ----- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Arquitectura           | 🟢    | `final readonly` Action + Service estático sin estado; sin lógica de negocio fuera de Action; no sobreingeniería.                                                                                                                            |
| Backend (Laravel)      | 🟡    | `ProvisionInfrastructureAction` sin idempotencia ni `tries`; `RailwayService` stub retorna `true` ocultando fallo; `TenantQueueManager::dispatch` no tipa `$job` ni valida queue.                                                            |
| Base de Datos          | 🟢    | No crea tablas; usa `jobs` existente; sin N+1 ni scopes mal usados.                                                                                                                                                                          |
| Frontend (Livewire/UI) | 🟢    | Sin Livewire propio; solo enlace sidebar `layouts/central/sidebar.blade.php: heart → central.health` — payload nulo.                                                                                                                         |
| Seguridad              | 🟡    | `auth:central` en ruta es correcto, pero `allowed_ips` check interno es inalcanzable sin auth; `HealthCheckController.php:56` expone `$e->getMessage()` con posible host DB en JSON.                                                         |
| Performance            | 🟡    | 19 queues static bien para MVP, pero `Queue::size()` solo mide `default` y `checkQueue` ignora buckets; `HorizonUpdateCommand` nunca detecta cambio porque resolver es static.                                                               |
| Testing                | 🟡    | `OperationsTest.php` cubre health auth + bucket hashing + suspended→low; `HorizonQueueResolutionTest.php` cubre 19 queues; **0 test para `RailwayService` fallo/retry, 0 para `horizon:update-queues --force`, 0 para health con Redis down. |
| DevOps / Observability | 🟡    | `Log::info` por provisioning sin métrica `provisioning.infra.latency`; health responde `503` pero no integra `Platform/Observability/HealthChecker`; sin alerta `LongWaitDetected` para buckets tenant.                                      |

## 6. Hallazgos (Listado detallado con formato P0-P3)

### [ID: O001] [P1 Alto] `RailwayService` es un stub que siempre retorna éxito — infraestructura nunca falla visiblemente

- **Categoría:** Backend | DevOps
- **Ubicación:** `app/Modules/Central/Operations/Infrastructure/Clients/RailwayService.php:42-68` (GraphQL comentado, `return true` sin HTTP), `Application/Actions/ProvisionInfrastructureAction.php:28` (`$this->railway->provisionDomain(...)` sin check de `false`)
- **Problema y Evidencia:** El método documenta `[RIESGOS] API Rate limits, DNS propagation` pero el bloque `Http::withHeaders()->post('https://backboard.railway.app/graphql/v2', ...)` está **comentado**. El `try/catch` solo loggea y retorna `false` si excepción, pero nunca hay excepción porque no hay HTTP. `ProvisionInfrastructureAction` ignora el `bool` retornado y siempre hace `activity('infrastructure')->log('infrastructure_provisioned')`. `ProvisionTenantPipeline::runStep('infrastructure', fn()=> provisionInfra->execute)` marca `completed` aunque el dominio no exista. **Confirmado** por lectura: `return true` en `:69` sin condición.
- **Impacto y Recomendación:** Tenants `active` sin dominio Railway real; `tenant.run()` en `CreateInitialAdminUser` funciona (Single-DB), pero ingress externo 404. Pipeline no reintentable por error real. Descomentar y completar mutación GraphQL, añadir `timeout 5s retry(3, 200)` con backoff exponencial, y hacer `ProvisionInfrastructureAction` lanzar `InfrastructureProvisioningException` si `provisionDomain` retorna `false` para que `ProvisioningLog` marque `failed` y `ProvisioningReconcileCommand` reintente. Añadir test `RailwayServiceTest` con `Http::fake` + `provisionDomain` `assert false` cuando API 429.
- **Complejidad / Prioridad:** Media / Sprint

### [ID: O002] [P1 Alto] Health-check exige `auth:central` y mide solo `Queue::size()` global — inutilizable para probes y ciego a buckets tenant

- **Categoría:** DevOps | Security
- **Ubicación:** `app/Modules/Central/Operations/Interface/Routes/web.php:8` (`middleware(['web','auth:central'])`), `Interface/Http/Controllers/HealthCheckController.php:23` (`allowed_ips` check tras auth), `:84` (`Queue::size()` sin conexión/cola)
- **Problema y Evidencia:** Ruta protegida por `auth:central` obliga a cookie/central guard; un `livenessProbe: httpGet path: /central/health` de K8s sin sesión siempre 302→ `/central/login`. El `allowed_ips` check (`:24`) pretende permitir IP whitelisting sin auth, pero nunca se alcanza sin login. Además `checkQueue` (`:84`) llama `Queue::size()` que en Redis driver sin args mide solo `default` (ver `Illuminate\Queue\RedisQueue::size($queue = null)` → `default`). Los 15 buckets `tenant.b*. *` pueden estar con 10k jobs y health seguirá `pass`. **Confirmado** leyendo `web.php` + controller.
- **Impacto y Recomendación:** Orquestador marca pod `healthy` aunque colas tenant saturadas; no hay paginación de `failed_jobs` ni `horizon:queue-metrics`. Solución: exponer `GET /up` (ya en `bootstrap/app.php: health: '/up'`) o `GET /central/health` **sin** `auth:central` pero con `throttle:health` + `allowed_ips` como gate principal; añadir `signed` o `Bearer` token para SRE. En `checkQueue`, iterar `HorizonQueueResolver::resolve()` y sumar `Queue::size($queue)` por bucket o leer `horizon:metrics` vía `Redis::zcard`. Añadir check `failed_jobs > 100` → `warn`.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: O003] [P2 Medio] Falta `infrastructure.railway.*` en config — servicio siempre no-op en local y en producción sin .env

- **Categoría:** Backend | Architecture
- **Ubicación:** `app/Modules/Central/Operations/Infrastructure/Clients/RailwayService.php:21` (`config('infrastructure.railway.api_token')`), `config/infrastructure.php:1` (solo `health.allowed_ips`)
- **Problema y Evidencia:** `config/infrastructure.php` define solo `health.allowed_ips`; no hay clave `railway` (`api_token`, `project_id`, `service_id`). `RailwayService::__construct()` lee `null` y en `:35` `if (!apiToken||!projectId||!serviceId) return true` — no-op silencioso en todos los envs donde `.env` no define `INFRASTRUCTURE_RAILWAY_API_TOKEN`. No hay `config:cache` warm ni validación en `OperationsServiceProvider::register`.
- **Impacto y Recomendación:** Deploy a Railway sin env var parece "provisionado" pero sin dominio; difícil de debugear (solo `Log::info` "skipped"). Añadir `config/infrastructure.php` `railway => ['api_token'=>env(...), 'project_id'=>..., 'service_id'=>...]` + `php artisan config:validate` o `RailwayService` throw si `app()->isProduction()` y faltan vars. Documentar en `.env.example`.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: O004] [P2 Medio] `HorizonUpdateCommand` nunca detecta cambios porque el resolver es static — `Cache::forever` global sin TTL ni tenant-scope

- **Categoría:** Backend | Performance
- **Ubicación:** `app/Modules/Central/Operations/Infrastructure/Console/HorizonUpdateCommand.php:33` (`$currentQueues = HorizonQueueResolver::resolve(); sort`), `Infrastructure/Horizon/HorizonQueueResolver.php:17` (static 19 queues)
- **Problema y Evidencia:** `HorizonQueueResolver::resolve()` retorna array hardcoded (4 base + 5×3 buckets) sin consultar DB ni `tenants` count. `HorizonUpdateCommand` compara `current vs last` cached; como nunca cambia, `horizon:terminate` jamás se dispara salvo `--force`. El `Cache::forever('horizon_active_queues_list')` guarda clave global sin prefix `tenant:` y sin TTL; bajo Redis con `CACHE_STORE=redis` sobrevive deploys. Útil si en futuro se generan colas dinámicas por slug, pero hoy es código muerto. **Riesgo** (no Confirmado en prod): si se implementan colas por `tenant.slug`, el resolver static causaría `QueueNotFound`.
- **Impacto y Recomendación:** Falsa sensación de "auto-update" de Horizon; deploy de nuevo bucket requiere manual `horizon:terminate`. O bien eliminar comando (YAGNI) o hacerlo útil: si se mantiene bucket fijo, documentar `// static, no DB query needed` y borrar `Cache` logic; si se quiere dinámico, cambiar a `Tenant::pluck('slug')` con `Cache::remember(60)` y `horizon:terminate --wait`. Añadir test `HorizonUpdateCommandTest` con `Cache::get` mock.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: O005] [P2 Medio] Sin contrato `InfrastructureProvisioner` — `ProvisionTenantPipeline` acopla `RailwayService` concreto

- **Categoría:** Architecture
- **Ubicación:** `app/Modules/Central/Operations/Application/Actions/ProvisionInfrastructureAction.php:14` (`private RailwayService $railway`), `app/Modules/Central/Provisioning/Actions/ProvisionTenantPipeline.php:27` (`private ProvisionInfrastructureAction $provisionInfra`)
- **Problema y Evidencia:** `ProvisionInfrastructureAction` inyecta `RailwayService` concreto, no `Platform/Contracts/InfrastructureProvisioner`. Para testear fallo de infraestructura, `ProvisioningPipelineTest.php:101` debe `Mockery::mock(RailwayService::class)` y `app()->instance(RailwayService::class, $mock)` — acopla test al cliente HTTP específico. Si se añade Cloudflare/AWS, habría que modificar la Action, no componer providers.
- **Impacto y Recomendación:** Violación DIP leve; no bloquea MVP pero impide estrategia multi-provider (Railway vs Cloudflare). Extraer `InfrastructureProvisionerContract::provisionDomain(TenantContract $tenant, string $domain): bool` en `Platform/Contracts`, y `RailwayProvisioner` + `NullProvisioner` (local) como implementaciones. `ProvisionInfrastructureAction` itera `provisioners[]`. No es sobreingeniería si hay 2+ providers previstos; si solo Railway, documentar `// single provider YAGNI` y dejar así.
- **Complejidad / Prioridad:** Media / Backlog

### [ID: O006] [P2 Medio] Health-check expone mensajes de excepción de DB/Redis al cliente

- **Categoría:** Security
- **Ubicación:** `app/Modules/Central/Operations/Interface/Http/Controllers/HealthCheckController.php:56` (`return ['status'=>'fail','message'=>$e->getMessage()]`), `:76` y `:92` igual
- **Problema y Evidencia:** En `checkDatabase`/`checkRedis`/`checkQueue`, el `catch (\Exception $e)` devuelve `$e->getMessage()` en JSON público (tras auth, pero `auth:central` ya filtra). Si `APP_DEBUG=true` o DB down con `SQLSTATE[08006] connection to "pgsql:host=..." failed`, el JSON filtra host/puerto. Aunque solo staff central ve `/central/health`, el principio de mínima exposición exige sanitizar.
- **Impacto y Recomendación:** Fuga de info de infra baja. Retornar `['message'=>'Database unreachable']` genérico y loggear `$e->getMessage()` en `Log::warning` con `context ['ip'=> $request->ip()]`. Añadir test `health returns generic message when DB down`.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: O007] [P3 Bajo] `TenantQueueManager::dispatch` sin type-hint ni `ShouldQueue` check — jobs síncronos podrían encolarse sin queue

- **Categoría:** Backend
- **Ubicación:** `app/Modules/Central/Operations/Application/Services/TenantQueueManager.php:32` (`public static function dispatch(Tenant $tenant, $job, string $priority='default'): void`)
- **Problema y Evidencia:** Parámetro `$job` es `mixed` sin `object|ShouldQueue`; si se pasa un `Mailable` o `Event` no queueable, `dispatch($job)->onQueue()` lanza runtime error solo en producción. Además usa `dispatch()` helper global (prohibido per `laravel-livewire.md`: preferir inyección) y no valida `priority` (`high|default|low`).
- **Impacto y Recomendación:** DX menor, no afecta rutas actuales (ningún caller usa `TenantQueueManager::dispatch` hoy — solo `resolve()`). Tipar `dispatch(Tenant $tenant, object $job, string $priority='default'): void` con `assert($job instanceof ShouldQueue)` y `in_array($priority, ['high','default','low'], true) ?: throw`.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: O008] [P3 Bajo] Duplicación `HealthChecker` vs `HealthCheckController` — dos implementaciones de check DB

- **Categoría:** Architecture
- **Ubicación:** `app/Modules/Platform/Observability/Health/HealthChecker.php:9` (`checkDatabaseConnection(): bool`), `app/Modules/Central/Operations/Interface/Http/Controllers/HealthCheckController.php:49` (`checkDatabase(): array`)
- **Problema y Evidencia:** `HealthChecker` existe en Platform pero Operations no lo usa; ambos hacen `DB::connection()->getPdo()`. `HealthChecker` retorna `bool`, controller retorna `['status'=>'pass']`. No hay `HealthChecker::checkRedis`/`checkQueue`.
- **Impacto y Recomendación:** Duplicación menor; viola DRY pero no causa bug. Unificar: `HealthCheckController` debe delegar a `HealthChecker::checkDatabase()` + `checkRedis()` + `checkQueue()` y mapear a `pass/fail`. No crear abstracción extra.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: O009] [P3 Bajo] Horizon `waits` y `trim` solo para `redis:default` — buckets tenant sin umbral de `LongWaitDetected`

- **Categoría:** DevOps
- **Ubicación:** `config/horizon.php:78` (`'waits' => ['redis:default'=>60]`), `:95` (`'trim' => [...]`)
- **Problema y Evidencia:** `waits` configura `LongWaitDetected` event solo para `redis:default`; las 15 colas `tenant.b*. *` no tienen threshold, por lo que Horizon nunca dispara `LongWaitDetected` para jobs tenant aunque esperen 10 min. `trim` y `metrics` globales no distinguen buckets.
- **Impacto y Recomendación:** Ops ciego a colas tenant saturadas. Añadir `'waits' => ['redis:default'=>60, 'redis:tenant.b1.default'=>60, ...]` o pattern `'redis:tenant.*'=>60` si Horizon lo soporta, o iterar buckets en config. Bajo hoy (pocos jobs), alto cuando haya 1k tenants.
- **Complejidad / Prioridad:** Baja / Backlog

## 7. Matriz de riesgos (Tabla: ID | Severidad | Categoría | Impacto | Complejidad)

| ID   | Severidad | Categoría            | Impacto                                                                    | Complejidad |
| ---- | --------- | -------------------- | -------------------------------------------------------------------------- | ----------- |
| O001 | P1 Alto   | Backend/DevOps       | Dominio nunca provisionado pero Pipeline marca completed, ingress 404      | Media       |
| O002 | P1 Alto   | DevOps/Security      | Health no usable por probes + métrica Queue engañosa, back-pressure oculto | Baja        |
| O003 | P2 Medio  | Backend/Architecture | Railway no-op silencioso en prod sin env, difícil debug                    | Baja        |
| O004 | P2 Medio  | Backend/Performance  | horizon:update-queues nunca dispara, falsa auto-escala                     | Baja        |
| O005 | P2 Medio  | Architecture         | Sin contrato multi-provider, tests acoplados a Railway concreto            | Media       |
| O006 | P2 Medio  | Security             | Exposición de mensaje de excepción con host DB                             | Baja        |
| O007 | P3 Bajo   | Backend              | dispatch sin type-hint, posible runtime error                              | Baja        |
| O008 | P3 Bajo   | Architecture         | Duplicación HealthChecker vs Controller                                    | Baja        |
| O009 | P3 Bajo   | DevOps               | Sin waits para buckets tenant, LongWait nunca dispara                      | Baja        |

## 8. Ruta de trabajo (Fases ordenadas por dependencia: 0. Bloqueadores -> 1. Riesgos -> 2. Estabilización)

**Fase 0 — Bloqueadores (no hay P0, pero sprint debe priorizar P1)**

1.  **O001**: Completar `RailwayService::provisionDomain` (descomentar GraphQL, añadir `timeout 5s retry 3` + circuit breaker) y hacer `ProvisionInfrastructureAction` lanzar excepción si `false` para que `ProvisionTenantPipeline:85` marque `failed` y `provisioning:reconcile` reintente. Test `Http::fake` con 429/500.
2.  **O002**: Desdoblar health: mantener `/central/health` con `auth:central` para staff, y exponer `/up` (ya en `bootstrap/app.php`) o `/central/health/public` sin auth pero con `throttle:health` + `allowed_ips` + `signed` para probes; fix `checkQueue` para iterar `HorizonQueueResolver::resolve()` y sumar `Queue::size($queue)`.

**Fase 1 — Riesgos (Sprint, depende de Fase 0)** 3. **O003**: Añadir `config/infrastructure.php` `railway => [...]` con defaults y validación `if app()->isProduction() && !apiToken throw`; documentar en `.env.example` + `README.md`. 4. **O004**: Decidir destino de `HorizonUpdateCommand`: eliminar si buckets son static (YAGNI) o volverlo dinámico con `Cache::remember(60)` + `Tenant::count()` si se migra a colas por slug. 5. **O006 + O008**: Sanitizar `HealthCheckController` mensajes (`'Database unreachable'` genérico) y delegar a `Platform/Observability/Health/HealthChecker` (unificar `checkRedis`/`checkQueue`).

**Fase 2 — Estabilización (Backlog)** 6. **O005**: Extraer `InfrastructureProvisionerContract` solo si se confirma 2º provider (Cloudflare); si no, dejar `RailwayService` concreto y documentar `// YAGNI: single provider`. 7. **O009 + O007**: Añadir `waits` para buckets tenant en `config/horizon.php` y tipar `TenantQueueManager::dispatch(object $job)`. 8. **Observabilidad**: Métrica `provisioning.infra.duration` + `provisioning.railway.error` count, y `Log::warning` con `tenant_id`/`domain` estructurado (Pail-friendly).

## 9. Quick Wins (Bajo esfuerzo, alto impacto)

- **QW-1 — Health público para probes** (`Interface/Routes/web.php:8`): Añadir `Route::get('/up/central', HealthCheckController::class)->middleware('throttle:health')` sin `auth:central` pero con `allowed_ips`. **Esfuerzo: 20 min**, habilita K8s/Railway health checks sin sesión.
- **QW-2 — Queue size real** (`HealthCheckController.php:84`): `collect(HorizonQueueResolver::resolve())->sum(fn($q)=> Queue::size($q))` + `warn if >1000`. **Esfuerzo: 15 min**, expone back-pressure real.
- **QW-3 — Config railway** (`config/infrastructure.php:4`): Añadir `'railway'=>['api_token'=>env(...), 'project_id'=>..., 'service_id'=>...]` + `.env.example`. **Esfuerzo: 10 min**, evita no-op silencioso.
- **QW-4 — Log estructurado** (`ProvisionInfrastructureAction.php:23`): `Log::info('infra.provisioning', ['tenant_id'=>$tenant->id,'domain'=>$primaryDomain])` en lugar de string interpolado. **Esfuerzo: 5 min**, grep en Pail.
- **QW-5 — Test de health con Redis down** (`tests/Feature/Central/OperationsTest.php`): `Redis::shouldReceive('connection->ping')->andThrow(...) ->assertJsonPath('checks.redis.status','fail')`. **Esfuerzo: 20 min**, regression guard.

## 10. Qué NO hacer (Refactors tentadores pero innecesarios/sobreingeniería)

- **NO introducir Repository Pattern** sobre `Tenant::where`/`Domain`. `Tenant::create` + `domains()->updateOrCreate` es suficiente; Repos añaden indirección.
- **NO implementar CQRS/Event Sourcing para infra**. `ProvisioningLog` + `activity()` ya cubren audit; event store es sobrecosto para 1 Action.
- **NO extraer “Operations Microservice”** ni mover `tenants` a DB separada. Estrategia oficial Single DB + RLS (`PROJECT_DECISIONS.md §3`); microservicio rompe `SET LOCAL`.
- **NO crear `InfrastructureProvisioner` con 6 providers** si solo Railway está confirmado. Un contrato con `NullProvisioner` para local es suficiente; factory genérica es sobreingeniería.
- **NO migrar a colas por `tenant.slug` dinámicas** hasta que haya 1k+ tenants activos. Los 5 buckets `tenant.b{1-5}` son intencionales para evitar _queue explosion_ en Redis/Horizon (ver `TenantQueueManager.php:14` comentario "prevent Noisy Neighbor").
- **NO añadir `tenancy:enable-rls` para `jobs` table** — `jobs` es global, no tenant-scoped; RLS no aplica.
- **NO unificar `HealthChecker` y `HealthCheckController` con DDD hexagonal** — un controller que delega a `HealthChecker` es suficiente.

## 11. Cobertura de pruebas (Flujos críticos expuestos)

**Cubierto (confirmado):**

- `Infrastructure Health Check` (`OperationsTest.php:7`) — `GET /central/health` 302 sin auth, 200 con `CentralUser` + `Redis::shouldReceive(ping)` mock.
- `Tenant Queue Management` (`OperationsTest.php:22`) — `TenantQueueManager::resolve` bucket `tenant.b[1-5].default/high` y `suspended→low` (`:38` `status===suspended` force low).
- `HorizonQueueResolver` (`HorizonQueueResolutionTest.php:4`) — 19 queues static (`default`, `notifications`, `broadcasts`, `webhooks-priority` + `tenant.b1..5.*`), `balance auto`.

**No cubierto (huecos críticos):**

- **Railway failure path** — 0 tests. `RailwayService::provisionDomain` nunca testeado con `Http::fake` 429/500 o missing config; `ProvisionInfrastructureAction` no testea `false` → `failed` log.
- **Health failure modes** — no test `Database down` → `503 degraded`, `Redis down` → `fail`, `Queue deep >1000` → `warn`, ni `allowed_ips` filtering.
- **HorizonUpdateCommand** — 0 tests. No test `horizon:update-queues --force` → `Cache::forever` + `horizon:terminate`, ni idempotencia cuando queues no cambian.
- **TenantQueueManager::dispatch** — no test `dispatch($job)->onQueue(bucket)` ni validación `priority` inválido.
- **CrossTenant / RLS** — no aplica (Operations no tiene `tenant_id` scoped), pero `HealthChecker` no testea `SET LOCAL` leak (correctamente no debe).

## 12. Riesgos pendientes (Observabilidad)

- **Infra opaca**: `RailwayService` sin métrica `provisioning.railway.latency` ni `provisioning.infra.error` count; si Railway rate-limitea, `ProvisionTenantJob` reintenta 3× (`tries 3 backoff [10,60]` en `Provisioning/Jobs/ProvisionTenantJob.php:26`) sin `ShouldBeUnique` → triple `provisionDomain` con mismo domain (Railway idempotente? No verificado).
- **Horizon silent**: `Cache::forever('horizon_active_queues_list')` sin `Log::info` con `queues` diff; si `horizon:terminate` falla, no hay alerta `horizon.termination_failed`.
- **Queue explosion futura**: Si se migra a `tenant.slug` por cola, `HorizonQueueResolver` debe paginar `Tenant::pluck` con `chunkById`; hoy static evita OOM, pero documentar límite 5 buckets → max 19 queues (Horizon OK, Redis `LLEN` O(1)).
- **Health log leak**: `HealthCheckController.php:56` `Log::error` no existe — excepción se retorna al cliente pero no se loggea en `storage/logs/laravel.log` para Pail; añadir `Log::warning('health.database.fail', ['ip'=>..., 'error'=>...])`.

## 13. Conclusión (Próxima acción accionable)

**Estado 🟡 requiere atención.** No hay P0, pero `O001` (stub Railway) hace que el provisioning sea "verde" en logs y "rojo" en ingress, y `O002` (health con auth) deja al orquestador sin sonda.

**Próxima acción (48 h):**

1.  Asignar owner a `O001` (Infra) y `O002` (Observability). Implementar QW-3 (config railway) + descomentar GraphQL en `RailwayService` con test `Http::fake`, y QW-1+QW-2 (health público + queue size real) en rama `fix/operations-p1`; pasar `php artisan test --filter=Operations --compact` + `composer lint`.
2.  Re-ejecutar esta auditoría (IDs O001-O002) y, si pasan, promover a 🟢 y planificar Fase 1 (O003-O006) en sprint; mantener IDs `O001`–`O009` sin reutilizar serie `B`/`P`.

> **Nota de mantenimiento**: Este informe preserva IDs `O001`–`O009` históricos. No reutilizar serie `O` en `docs/modules/billing.md` (serie `B`) ni `provisioning.md` (serie `P`). Próxima auditoría (`Tenant/Access` o `Platform/Tenancy`) debe usar series `A001`/`T001`.
