# ANÁLISIS DE BOUNDED CONTEXT: `Central`

**Proyecto:** LaraShift · **Fecha:** 2026-07-01  
**Alcance:** `app/Modules/Central/` (14 submódulos: Analytics, Auth, Billing, Features, Infrastructure, Landings, Marketing, Monitoring, Payments, Provisioning, Security, Settings, Support)  
**Template:** `docs/Auditoria.md`

---

## 1. VALIDACIÓN DE PRECONDICIONES

### 🔴 1.1 Documentación faltante — `03_UseCases.md` y `06_CurrentState.md`

Ambos archivos referenciados en `docs/Auditoria.md` **NO EXISTEN** en el repositorio.

| Referencia                              | Archivo esperado                                | Realidad    |
| --------------------------------------- | ----------------------------------------------- | ----------- |
| `Auditoria.md:7` → `06_CurrentState.md` | Debería describir el estado actual del stack UI | ❌ No existe |
| `Auditoria.md:11` → `03_UseCases.md`    | Debería listar casos de uso por BC              | ❌ No existe |

**Implicancia:** No es posible validar ownership contra use cases documentados ni stack UI contra un settled state documentado. Auditoría debe realizarse con la documentación existente (`BASE.md`, `ARCHITECTURE.md`, `AGENTS.md`, código fuente).

### 🔴 1.2 Conflicto de stack UI en `BASE.md`

| Fuente      | Línea | Stack declarado                           |
| ----------- | ----- | ----------------------------------------- |
| `BASE.md`   | 176   | **Livewire 4**                            |
| `BASE.md`   | 393   | **Shadcn + React + Tailwind**             |
| `AGENTS.md` | —     | **Livewire 4 + Flux UI**                  |
| Código real | —     | **Livewire 4 + Flux UI + Tailwind CSS 4** |

El documento `BASE.md` contiene **dos declaraciones contradictorias** sobre el stack UI oficial. La línea 393 (`Shadcn + React`) entra en conflicto con la línea 176 (`Livewire 4`) y con la implementación real. **No hay código React/Shadcn en el proyecto.**

**Fix requerido:** Unificar `BASE.md` línea 393 para reflejar Livewire 4 + Flux UI + Tailwind, y eliminar la referencia a Shadcn + React.

---

## 2. OWNERSHIP / BOUNDED CONTEXT

### 2.1 Submódulos — ¿Central o Tenant?

| Módulo             | BC asignado | ¿Correcto?  | Evidencia                                                                                                                                                |
| ------------------ | ----------- | ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Analytics**      | Central     | ✅           | Métricas globales de plataforma (MRR, churn, tenants totales). Sin tenant_id.                                                                            |
| **Auth**           | Central     | ✅           | Autenticación de operadores centrales (SaaS platform admins).                                                                                            |
| **Billing**        | Central     | ✅           | Planes, suscripciones, facturación, dunning. Modelos en BD central.                                                                                      |
| **Features**       | Central     | ✅           | Catálogo de features global. Overrides por tenant desde Central.                                                                                         |
| **Infrastructure** | Central     | ✅           | Health checks, Horizon, Railway. Sin tenant-scope.                                                                                                       |
| **Landings**       | Central     | ⚠️ **Mixed** | Builder y gestión son Central. Pero `ServeTenantLandingController` sirve contenido tenant-scoped y `Landing` usa `BelongsToTenant`.                      |
| **Marketing**      | Central     | ✅           | Legal docs globales, landing page pública.                                                                                                               |
| **Monitoring**     | Central     | ✅           | Health checks globales.                                                                                                                                  |
| **Payments**       | Central     | ⚠️ **Mixed** | Gateway settings, webhooks, payouts son Central. Pero `CheckoutController` sirve a tenant context (`routes/payments.php` grupo con middleware `tenant`). |
| **Provisioning**   | Central     | ✅           | Ciclo de vida de tenants (creación, suspensión, archive, purge).                                                                                         |
| **Security**       | Central     | ✅           | Encryption keys, API key rotation.                                                                                                                       |
| **Settings**       | Central     | ✅           | Branding global de plataforma.                                                                                                                           |
| **Support**        | Central     | ✅           | Tickets, broadcasts, impersonación.                                                                                                                      |

### 🟡 Hallazgos de Ownership

1. **Payments/CheckoutController** (`app/Modules/Central/Payments/Http/Controllers/CheckoutController.php`): Vive en Central pero sus rutas están en grupo `['web', 'tenant', 'auth', 'verified']`. Depende de `tenant('id')`. Esto mezcla contextos: el dominio de pagos debe estar en Central (webhooks, gateways) o en Tenant (checkout), no en ambos.

2. **Billing Livewire components**: `ManageBilling`, `SelectPlan`, `HostedCheckout`, `UpdatePaymentMethod`, `TenantInvoiceList` viven en `Central/Billing/Livewire/` pero se renderizan en contexto tenant (ver `routes/tenant.php:144-147`). Aunque son *vistas* para el tenant, el *dominio* (la lógica de facturación) pertenece a Central — esto es una decisión arquitectónica deliberada, pero debe documentarse explícitamente.

3. **Landings/ServeTenantLandingController**: Sirve landing pages en el dominio tenant. La entidad `Landing` usa `BelongsToTenant`. Esto sugiere que el builder/authorship es Central pero el hosting/serving debería estar en Tenant. Ownership mixto.

---

## 3. ESTRUCTURA INTERNA

### 3.1 Convención de directorios por submódulo

| Módulo         | Actions | DTOs | Models | Policies | Livewire | Events | Jobs | Tests | Providers | Routes | Services |
| -------------- | ------- | ---- | ------ | -------- | -------- | ------ | ---- | ----- | --------- | ------ | -------- |
| Analytics      | ✅       | ✅    | ✅      | ❌        | ✅        | ❌      | ✅    | ❌     | ✅         | ❌      | ❌        |
| Auth           | ✅       | ✅    | ✅      | ❌        | ✅        | ❌      | ❌    | ❌     | ✅         | ✅      | ❌        |
| Billing        | ✅       | ✅    | ✅      | ❌        | ✅        | ❌      | ✅    | ❌     | ✅         | ✅      | ✅        |
| Features       | ✅       | ✅    | ✅      | ❌        | ✅        | ❌      | ❌    | ❌     | ✅         | ❌      | ❌        |
| Infrastructure | ✅       | ❌    | ❌      | ❌        | ❌        | ❌      | ❌    | ❌     | ✅         | ✅      | ✅        |
| Landings       | ✅       | ✅    | ✅      | ❌        | ✅        | ❌      | ❌    | ❌     | ✅         | ✅      | ❌        |
| Marketing      | ✅       | ❌    | ✅      | ❌        | ✅        | ❌      | ❌    | ❌     | ✅         | ❌      | ❌        |
| Monitoring     | ✅       | ❌    | ✅      | ❌        | ✅        | ❌      | ✅    | ❌     | ✅         | ❌      | ❌        |
| Payments       | ✅       | ✅    | ✅      | ❌        | ✅        | ✅      | ✅    | ✅     | ✅         | ✅      | ✅        |
| Provisioning   | ✅       | ✅    | ✅      | ❌        | ✅        | ❌      | ✅    | ❌     | ✅         | ✅      | ✅        |
| Security       | ✅       | ❌    | ✅      | ❌        | ✅        | ❌      | ✅    | ❌     | ✅         | ❌      | ❌        |
| Settings       | ✅       | ❌    | ✅      | ❌        | ✅        | ❌      | ❌    | ❌     | ✅         | ✅      | ❌        |
| Support        | ✅       | ✅    | ✅      | ❌        | ✅        | ❌      | ✅    | ❌     | ✅         | ❌      | ❌        |

**Observaciones:**
- `Policies/` ausente en todos los submódulos. `BASE.md` lista `Policies` en estructura típica de módulo.
- `Events/` solo en Payments. Los demás módulos emiten eventos de `Shared/Events/` directamente.
- Tests in-module (`Modules/Central/Payments/Tests/`) solo existen en Payments.
- `Infrastructure` no tiene Models (correcto, es infra/ops pura).

### 🔴 3.2 Lógica de negocio fuera de Actions

1. **`PaguelofacilCallbackController`** (`app/Modules/Central/Billing/Http/Controllers/PaguelofacilCallbackController.php`): Contiene lógica de negocio pesada:
   - Creación de `Subscription` con `updateOrCreate`
   - Actualización de `plan_id` en tenant
   - Construcción de URL de redirect con dominio
   - Manejo de `PaymentResultData` inline
   
   **Viola el patrón** Controller → Action → Response.

2. **`StripeWebhookController`** contenedores de eventos:
   - `handleCustomerSubscriptionCreated`, `handleCustomerSubscriptionUpdated`, etc. contienen lógica de negocio inline.

3. **`ManageTenant` Livewire** (`app/Modules/Central/Provisioning/Livewire/ManageTenant.php:37`):
   - Llama a `ResolveTenantFeaturesAction` directamente después de `UpdateTenantAction`. La invalidación de cache de features debería estar dentro del Action de actualización.

---

## 4. AISLAMIENTO DE TENANT

### 4.1 tenant_id + RLS

| Modelo                             | Tabla                      | ¿tenant_id?                 | RLS | Scope                       |
| ---------------------------------- | -------------------------- | --------------------------- | --- | --------------------------- |
| `Tenant` (Provisioning)            | `tenants`                  | N/A (es el tenant root)     | ❌   | —                           |
| `Feature` (Features)               | `features`                 | ❌ (catálogo global)         | ❌   | —                           |
| `TenantFeatureOverride` (Features) | `tenant_feature_overrides` | ✅ `tenant_id`               | ❌   | ❌                           |
| `Plan` (Billing)                   | `plans`                    | ❌ (catálogo global)         | ❌   | —                           |
| `Subscription` (Billing)           | `subscriptions`            | ✅ `tenant_id` (en fillable) | ❌   | ❌                           |
| `Invoice` (Billing)                | `invoices`                 | ✅ `tenant_id`               | ❌   | ❌                           |
| `PlatformMetric` (Analytics)       | `platform_metrics`         | ❌ (global)                  | ❌   | —                           |
| `Landing` (Landings)               | `landings`                 | ✅ `BelongsToTenant` trait   | ❌   | ✅ GlobalScope `TenantScope` |
| `LegalDocument` (Marketing)        | `legal_documents`          | ❌ (global)                  | ❌   | —                           |
| `TenantHealthCheck` (Monitoring)   | `tenant_health_checks`     | ✅ `tenant_id`               | ❌   | ❌                           |
| `TenantEncryptionKey` (Security)   | `tenant_encryption_keys`   | ✅ `tenant_id`               | ❌   | ❌                           |
| `SupportTicket` (Support)          | `support_tickets`          | ✅ `tenant_id`               | ❌   | ❌                           |

### 🟡 4.2 TenantScope como única defensa

El trait `BelongsToTenant` aplica un **Global Scope** (`TenantScope`) que filtra por `tenant_id` cuando `tenancy()->initialized` es true. Sin embargo:

1. `app/Modules/Shared/Tenancy/Bootstrappers/PostgresRlsBootstrapper.php` existe y configura `app.tenant_id` en PostgreSQL, pero es un bootstrapper de stancl/tenancy que solo se ejecuta en `pgsql` driver.
2. **No se encontraron migraciones que ejecuten `ALTER TABLE ... ENABLE ROW LEVEL SECURITY`** para las tablas tenant-scoped. El comando `EnableRlsCommand` existe pero no hay evidencia de que esté siendo llamado durante provisioning.
3. La defensa contra cross-tenant es **únicamente Eloquent Scope** para la mayoría de modelos — sin RLS a nivel BD, una query directa o un error en el Scope puede filtrar datos.

**Riesgo:** Si un desarrollador usa `DB::table('invoices')->get()` o `Invoice::withoutGlobalScopes()->get()`, el aislamiento se pierde por completo.

### ✅ 4.3 Cross-tenant → 404

- `CentralFallback::ensureTenant()` → `abort(404)` si no hay tenant ✅
- `CentralFallback::ensureCentral()` → `abort(404)` si tenancy está inicializado ✅

### 🟡 4.4 tenant_id confiado desde request/sesión

- `PaguelofacilCallbackController` recibe `PARM_1` (tenant_id) y `PARM_2` (plan_id) desde el request del callback. No se revalida que el subscription creado pertenezca al tenant correcto.
- `BillingApiController::checkout()` recibe `tenant_id` del request y hace `Tenant::findOrFail($request->tenant_id)` — la validación de ownership es correcta (bind implícito), pero confía en el ID enviado.

---

## 5. MIDDLEWARE / ACCESO

### 5.1 Orden en rutas tenant

`routes/tenant.php:67-74`:

```php
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,       // 1. Tenancy
    PreventAccessFromCentralDomains::class, // 2. Prevención acceso central
    EnsureTenantIsActive::class,            // 3. Estado tenant + subscription check
    ApplyTenantRateLimits::class,           // 4. Rate limits
    AuditImpersonationActions::class,       // 5. Impersonación audit
])->group(function () { ... });
```

Esto cumple con el orden: **Tenancy → Scopes/RLS → Auth → Subscription**

El middleware `CheckSubscription` (`Central/Billing/Http/Middleware/CheckSubscription.php`) aplica dentro del grupo auth. El `EnsureTenantIsActive` ya verifica subscription activa para authenticated users.

### 🟡 5.2 Rutas que bypassean la cadena

- `/central/webhooks/stripe` → **Sin middleware** (público, correcto para webhooks)
- `/central/billing/paguelofacil/callback` → **Sin middleware** (público, correcto para callbacks)
- Las rutas de webhooks en `payments.php` usan `->withoutMiddleware(['web', 'auth', 'tenant'])` — esto remueve el `web` middleware global, lo cual es intencional para webhooks raw, pero **también remueve middleware de sesión**.

### 🔴 5.3 Middleware CheckSubscription ubicación inconsistente

`CheckSubscription` está en `app/Modules/Central/Billing/Http/Middleware/` pero **no se aplica en ninguna ruta Central**. Las rutas que deberían usarlo son las de tenant (en `routes/tenant.php`), pero el chequeo de subscription se hace dentro de `EnsureTenantIsActive`. Esto sugiere que `CheckSubscription` podría ser middleware huérfano o usado fuera de lo esperado.

---

## 6. QUEUE SAFETY

### 6.1 Análisis de Jobs

| Job                         | Módulo       | tenant_id en constructor             | Inicializa tenancy                 | Cleanup (end)                    | Issues                                             |
| --------------------------- | ------------ | ------------------------------------ | ---------------------------------- | -------------------------------- | -------------------------------------------------- |
| `ProcessPaymentWebhookJob`  | Payments     | ✅ `string $tenantId`                 | ✅ `tenancy()->initialize()`        | ✅ `finally { tenancy()->end() }` | ✅ Correcto                                         |
| `SyncTenantInvoicesJob`     | Billing      | ✅ `string $tenantId`                 | ❌ No inicializa                    | ❌                                | 🟡 No inicializa tenancy                            |
| `ProvisionTenantJob`        | Provisioning | ⚠️ `Tenant $tenant` (modelo completo) | ❌ No aplica (BD central)           | ❌                                | 🔴 Modelo serializado                               |
| `ProvisioningJob`           | Provisioning | ❓ Nuevo job basado en steps          | —                                  | —                                | ⚪ No auditado (migración desde ProvisionTenantJob) |
| `RefreshPlatformMetricsJob` | Analytics    | ❌ No aplica (plataforma global)      | ❌                                  | ❌                                | ✅ Correcto (job global)                            |
| `RunTenantHealthChecksJob`  | Monitoring   | ❌ No tiene                           | ❌ No inicializa por tenant         | ❌                                | 🔴 No hay isla por tenant                           |
| `RotateTenantSecretsJob`    | Security     | ❌ No tiene                           | ✅ `tenancy()->initialize($tenant)` | ✅ `tenancy()->end()`             | ✅ Correcto                                         |
| `EscalateOverdueTicketsJob` | Support      | ❌ No aplica (BD central)             | ❌                                  | ❌                                | ✅ Correcto (central query)                         |
| `SendBulkBroadcastJob`      | Support      | ❓                                    | ❓                                  | ❓                                | ⚪ No auditado                                      |

### 🔴 6.2 Hallazgos críticos

1. **`ProvisionTenantJob`** almacena `Tenant $tenant` completo en el payload serializado. Esto puede causar:
   - Payloads grandes en la cola
   - Stale data si el tenant se modifica antes de ejecutarse
   - Problemas de serialización con `SoftDeletes` / relaciones

   El docblock del mismo job lo marca como `@deprecated` y recomienda `ProvisioningJob`.

2. **`RunTenantHealthChecksJob`** itera todos los tenants llamando `$action->execute($tenant)` sin jamás inicializar tenancy. Esto significa que el health check se ejecuta en el contexto de la BD central, no en la BD del tenant. Si `RunTenantHealthCheckAction` espera `tenancy()->initialized`, fallará silenciosamente o dará resultados incorrectos.

3. **`SyncTenantInvoicesJob`** recibe `$tenantId` pero nunca inicializa tenancy. `SyncInvoicesAction` podría operar sobre la BD incorrecta.

### 🟡 6.3 Graceful Handover

- `ProcessPaymentWebhookJob` es el único job que implementa correctamente el cleanup con `try/finally`.
- `RotateTenantSecretsJob` llama `tenancy()->end()` después de cada tenant en el loop.
- Los jobs que iteran múltiples tenants (como `RunTenantHealthChecksJob`) **no limpian estado** entre iteraciones, lo que puede causar residual state.

---

## 7. CALIDAD DE CAPA

### 7.1 Actions

Patrón esperado: `final readonly class`, single responsibility, `execute()`, sin dependencia de Request/sesión.

| Action                             | `final readonly` | `execute()`                            | Sin Request                                  | Issues                                          |
| ---------------------------------- | ---------------- | -------------------------------------- | -------------------------------------------- | ----------------------------------------------- |
| `LoginCentralUserAction`           | ✅                | ✅ (y `completeLogin`, `recordSession`) | ✅                                            | ⚪ Múltiples métodos públicos (2 extra)          |
| `EnrollCentral2FAAction`           | ✅                | ✅ (`initiate`, `confirm`)              | ✅                                            | ⚪ Múltiples métodos públicos (2 en vez de 1)    |
| `CreateTenantAction`               | ✅                | ✅                                      | ✅                                            | 🔴 `throw new \Exception(...)` en 5 lugares      |
| `ApplyTenantFeatureOverrideAction` | ✅                | ✅                                      | ✅                                            | ✅ Correcto                                      |
| `ImpersonateTenantAction`          | ✅                | ✅                                      | ✅                                            | ⚪ Dependencia de `auth('central')->id()` inline |
| `CreateTicketAction`               | ✅                | ✅                                      | 🔴 **Dependencia de `auth('central')->id()`** | 🟡 Acopla autenticación al Action                |
| `FetchDashboardMetricsAction`      | ✅                | ✅                                      | ✅                                            | ✅ Correcto                                      |
| `CancelSubscriptionAction`         | ✅                | ✅                                      | ✅                                            | ✅ Correcto                                      |
| `DeleteTenantAction`               | ✅                | ✅                                      | ✅                                            | ✅ Correcto                                      |

### 🟡 7.2 Dependencia de Request/Auth en Actions

- `CreateTicketAction` usa `auth('central')->id()` dentro de `execute()`, lo que lo acopla al contexto HTTP. Un Action debería recibir el `created_by` como parámetro.
- `ApplyTenantFeatureOverrideAction` usa `Gate::authorize()` y `auth('central')->id()` internamente.

### 7.3 DTOs

Todos los DTOs encontrados usan `Spatie\LaravelData\Data`:

- ✅ `LoginData` — tipado fuerte
- ✅ `CreateTenantData` — tipado fuerte
- ✅ `PlanData` — tipado fuerte
- ✅ `DashboardMetrics` — tipado fuerte con arrays de DTOs hijos
- ✅ `FeatureData` — tipado fuerte
- ✅ `CreateTicketData` — tipado fuerte
- ✅ Todos los DTOs de Payments — tipados fuertes con Enums

**No se encontraron arrays como payloads en Actions.** ✅

### 7.4 Controllers

| Controller                       | `authorize → validate → action → response` | Issues                                                                                         |
| -------------------------------- | ------------------------------------------ | ---------------------------------------------------------------------------------------------- |
| `LogoutController`               | ✅                                          | Perfecto, 1 método                                                                             |
| `BillingApiController`           | ⚠️                                          | `validate()` en método, catch de `\Exception` genérico (abarca todo)                           |
| `InvoicePdfController`           | ✅                                          | Perfecto                                                                                       |
| `PaguelofacilCallbackController` | 🔴                                          | **Business logic pesada inline: Subscriptions, Tenant updates, redirects**                     |
| `StripeWebhookController`        | ⚠️                                          | Event handlers con lógica inline. Caché de idempotencia con `PaymentGatewayEvent` es correcto. |
| `HealthCheckController`          | ✅                                          | Delegado                                                                                       |
| `ServeTenantLandingController`   | ✅                                          | Query directa a BD pero es operación de lectura (aceptable)                                    |
| `TenantImpersonationController`  | ⚪                                          | Múltiples responsabilidades (auth y logout en mismo controller)                                |
| `CheckoutController`             | ⚠️                                          | `validate` inline, `PaymentAmountResolverContract` inline                                      |
| `WebhookController`              | ✅                                          | Delega a Job                                                                                   |

---

## 8. EXCEPCIONES

### 8.1 Excepciones de dominio encontradas

| Excepción                             | Extiende                | Categoría             |
| ------------------------------------- | ----------------------- | --------------------- |
| `AuthenticationFailedException`       | `\RuntimeException`     | ✅ Dominio             |
| `AccountLockedException`              | `\RuntimeException`     | ✅ Dominio             |
| `Invalid2FACodeException`             | `\RuntimeException`     | ✅ Dominio             |
| `ProvisioningException`               | `\RuntimeException`     | ✅ Dominio (abstracta) |
| `ProvisioningFailedException`         | `ProvisioningException` | ✅ Dominio             |
| `SlugAlreadyExistsException`          | `ProvisioningException` | ✅ Dominio             |
| `TenantNotFoundException`             | `ProvisioningException` | ✅ Dominio             |
| `CheckoutFailedException`             | `\RuntimeException`     | ✅ Dominio             |
| `SubscriptionReconciliationException` | `\RuntimeException`     | ✅ Dominio             |
| `FeatureOverrideException`            | `\RuntimeException`     | ✅ Dominio             |
| `ExportFailedException`               | `\RuntimeException`     | ✅ Dominio             |
| `ClaveGatewayException`               | `\RuntimeException`     | ✅ Dominio             |
| `DlocalGatewayException`              | `\Exception`            | ✅ Dominio             |
| `InvalidMerchantException`            | `ClaveGatewayException` | ✅ Dominio             |
| `WebhookVerificationException`        | `ClaveGatewayException` | ✅ Dominio             |

### 🔴 8.2 Uso de `throw new \Exception()` genérico

En `CreateTenantAction.php:execute()`:

```php
throw new \Exception("Tenant with slug {$data->slug} already exists.");
throw new \Exception('Payment token is required for immediate payment.');
throw new \Exception('Payment token is required to start a trial with card verification.');
throw new \Exception('Payment failed: '.($result['message'] ?? 'Unknown error'));
```

Esto debería usar `SlugAlreadyExistsException`, `ProvisioningException`, etc.

### 🟡 8.3 Excepciones estándar no usadas

Las excepciones definidas en `BASE.md:504-514`:
- `TenantSuspendedException` — No se encontró en el código de Central
- `QuotaExceededException` — No se encontró en Central
- `CrossTenantAccessAttempt` — No se encontró en Central
- `PlanFeatureAccessDenied` — No se encontró en Central

---

## 9. TESTS

### 9.1 Cobertura por módulo

| Módulo         | Tests | Isolation | Quota | Idempotency | Security    |
| -------------- | ----- | --------- | ----- | ----------- | ----------- |
| Analytics      | 1     | ❌         | ❌     | ❌           | ❌           |
| Auth           | 3     | ❌         | ❌     | ❌           | ✅ 1 archivo |
| Billing        | 7     | ❌         | ❌     | ❌           | ❌           |
| Features       | 3     | ❌         | ✅ 1   | ❌           | ❌           |
| Infrastructure | 1     | ❌         | ❌     | ❌           | ❌           |
| Landings       | 2     | ❌         | ❌     | ❌           | ❌           |
| Marketing      | 1     | ❌         | ❌     | ❌           | ❌           |
| Monitoring     | 1     | ❌         | ❌     | ❌           | ❌           |
| Payments       | 6     | ❌         | ❌     | ✅ 1         | ❌           |
| Provisioning   | 4     | ❌         | ❌     | ❌           | ❌           |
| Security       | 1     | ❌         | ❌     | ❌           | ❌           |
| Settings       | 1     | ❌         | ❌     | ❌           | ❌           |
| Support        | 3     | ❌         | ❌     | ❌           | ❌           |

### 🔴 9.2 Isolation Tests ausentes

No se encontró **ningún test de aislamiento** (Tenant A → recurso de Tenant B → 404) para los modelos tenant-scoped de Central:
- `TenantFeatureOverride`
- `Invoice`
- `TenantHealthCheck`
- `TenantEncryptionKey`
- `SupportTicket`
- `Landing`

### 🟡 9.3 Cobertura incompleta

- `ApplyTenantFeatureOverrideAction` no tiene tests ⚠️ (listed in codegraph as "no covering tests found")
- `RevokeOldestSessionAction` no tiene tests ⚠️
- Falta test de **quota enforcement** para módulo Central (el existente `FeatureAndQuotaMiddlewareTest` es para middleware tenant)

### ✅ 9.4 Buenos patrones

- `Central/PaymentsIdempotencyTest.php` — Idempotencia de webhooks ✅
- `Central/PaymentsIdempotencyTest.php` — Prueba de idempotency key en refunds ✅
- `Central/BillingStateMachineTest.php` — State machine transitions ✅
- `FeatureManagementTest.php` — Feature resolution con overrides ✅
- `CentralAuthSecurityTest.php` — Brute-force, lockout ✅

---

## 10. CLASIFICACIÓN DE HALLAZGOS

### 🔴 Blocking

| ID   | Hallazgo                                                       | Dónde                                                                             | Por qué                                                                                                   | Fix sugerido                                                               |
| ---- | -------------------------------------------------------------- | --------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| B-01 | Documentación `03_UseCases.md` / `06_CurrentState.md` faltante | `docs/`                                                                           | Imposible validar ownership contra casos de uso documentados. Auditoría opera con información incompleta. | Crear documentos de use cases y estado actual                              |
| B-02 | Stack UI contradictorio en `BASE.md`                           | `BASE.md:176` vs `393`                                                            | Confunde a desarrolladores sobre stack oficial. Riesgo de implementaciones en stack no soportado.         | Unificar línea 393 a `Livewire 4 + Flux UI + Tailwind`                     |
| B-03 | Lógica de negocio en `PaguelofacilCallbackController`          | `app/Modules/Central/Billing/Http/Controllers/PaguelofacilCallbackController.php` | Viola patrón Action. Subscription/Plan/Tenant update inline. No testeable aisladamente.                   | Extraer a `ProcessPaguelofacilCallbackAction`                              |
| B-04 | `CreateTenantAction` usa `throw new \Exception()`              | `app/Modules/Central/Provisioning/Actions/CreateTenantAction.php`                 | Excepciones genéricas no diferenciables. Imposible manejo específico en catch.                            | Reemplazar con `SlugAlreadyExistsException`, `ProvisioningException`, etc. |
| B-05 | `RunTenantHealthChecksJob` sin inicialización de tenancy       | `app/Modules/Central/Monitoring/Jobs/RunTenantHealthChecksJob.php`                | Health checks se ejecutan en contexto central, no en cada tenant. Resultados incorrectos.                 | Inicializar tenancy por tenant en el loop                                  |
| B-06 | Faltan tests de aislamiento (cross-tenant → 404)               | Todos los módulos                                                                 | No hay verificación de que tenant A no acceda datos de tenant B. Riesgo de fuga de datos no detectado.    | Agregar Isolation Tests para cada modelo tenant-scoped                     |
| B-07 | `ProvisionTenantJob` serializa modelo completo                 | `app/Modules/Central/Provisioning/Jobs/ProvisionTenantJob.php`                    | Payload grande, datos stale, problemas de serialización. Ya deprecado por su propio docblock.             | Migrar a `ProvisioningJob` (solo `tenantId`)                               |

### 🟡 Medium

| ID   | Hallazgo                                                   | Dónde                                                               | Por qué                                                                                                                              | Fix sugerido                                                                                  |
| ---- | ---------------------------------------------------------- | ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------- |
| M-01 | `CheckSubscription` middleware no se aplica en rutas       | `app/Modules/Central/Billing/Http/Middleware/CheckSubscription.php` | Middleware existe pero no se usa (la subscription check está en `EnsureTenantIsActive`).                                             | Eliminar o integrar en pipeline                                                               |
| M-02 | `BelongsToTenant` scope como única defensa (sin RLS en BD) | `app/Modules/Shared/Tenancy/Models/Concerns/BelongsToTenant.php`    | Sin RLS, un `withoutGlobalScopes()` o query raw rompe aislamiento.                                                                   | Ejecutar `ENABLE ROW LEVEL SECURITY` en migraciones de tablas tenant-scoped                   |
| M-03 | `CreateTicketAction` acoplado a `auth('central')->id()`    | `app/Modules/Central/Support/Actions/CreateTicketAction.php`        | No invocable desde contexto sin-sesión (CLI, webhook).                                                                               | Recibir `created_by` como parámetro del DTO                                                   |
| M-04 | `SyncTenantInvoicesJob` no inicializa tenancy              | `app/Modules/Central/Billing/Jobs/SyncTenantInvoicesJob.php`        | `SyncInvoicesAction` puede operar sobre BD central en vez de BD tenant.                                                              | Agregar `tenancy()->initialize($this->tenantId)`                                              |
| M-05 | Ownership mixto en Payments y Landings                     | `Modules/Central/Payments/`, `Modules/Central/Landings/`            | Controllers tenant-scoped viviendo en Central. Confusión sobre dónde agregar nueva funcionalidad.                                    | Documentar la decisión. Mover `CheckoutController` a módulo Tenant de Payments o a `Shared/`. |
| M-06 | Controllers capturan `\Exception` genérico                 | `BillingApiController`, `PaguelofacilCallbackController`            | Oculta errores específicos, hace debugging difícil.                                                                                  | Capturar excepciones de dominio específicas                                                   |
| M-07 | Jobs sin cleanup de estado en loops                        | `RunTenantHealthChecksJob`, `RotateTenantSecretsJob` (parcial)      | Estado residual entre tenants en el mismo worker.                                                                                    | Agregar `tenancy()->end()` en finally de cada iteración                                       |
| M-08 | Excepciones estándar no implementadas                      | `BASE.md` lista 4 excepciones                                       | `TenantSuspendedException`, `QuotaExceededException`, `CrossTenantAccessAttempt`, `PlanFeatureAccessDenied` no existen en el código. | Crear clases de excepción                                                                     |

### ⚪ Cosmético

| ID   | Hallazgo                                                                       | Dónde                                                              | Fix sugerido                                                             |
| ---- | ------------------------------------------------------------------------------ | ------------------------------------------------------------------ | ------------------------------------------------------------------------ |
| C-01 | `CentralBranding` no es `final readonly`                                       | `app/Modules/Central/Settings/Support/CentralBranding.php`         | Añadir `final readonly`                                                  |
| C-02 | `CentralSession` no es `final`                                                 | `app/Modules/Central/Auth/Models/CentralSession.php`               | Añadir `final` a la clase                                                |
| C-03 | `GenerateDLocalSignature` es `final` pero no `readonly`                        | `app/Modules/Central/Payments/Actions/GenerateDLocalSignature.php` | Añadir `readonly`                                                        |
| C-04 | `GenerateDLocalSignature` usa `public function handle()` en vez de `execute()` | `app/Modules/Central/Payments/Actions/GenerateDLocalSignature.php` | Renombrar a `execute()` (consistencia)                                   |
| C-05 | Faltan Policies/ directorios en todos los submódulos                           | `app/Modules/Central/*/Policies/`                                  | Evaluar si Policies son necesarias (Central usa Gates y auth()->check()) |
| C-06 | Faltan Events/ en submódulos que emiten eventos                                | Billing central emite eventos de `Shared/Events/`                  | Crear Eventos locales o mantener uso de Shared (decisión documentada)    |
| C-07 | `ProvisionTenantJob` marcado como `@deprecated` pero aún se usa                | `app/Modules/Central/Provisioning/Jobs/ProvisionTenantJob.php`     | Completar migración a `ProvisioningJob`                                  |

---

## 11. PLAN DE MITIGACIÓN Y CORRECCIÓN

### Orden recomendado de ejecución

#### Fase 1: 🔥 HOTFIX — Seguridad y Bloqueos

| #   | ID   | Acción                                                                                 | Prioridad | Complejidad |
| --- | ---- | -------------------------------------------------------------------------------------- | --------- | ----------- |
| 1   | B-05 | `RunTenantHealthChecksJob`: inicializar tenancy por tenant                             | 🔥 Crítica | Baja        |
| 2   | M-04 | `SyncTenantInvoicesJob`: inicializar tenancy                                           | 🔥 Crítica | Baja        |
| 3   | B-07 | Migrar `ProvisionTenantJob` a `ProvisioningJob` (ID string)                            | 🔥 Crítica | Media       |
| 4   | B-04 | Reemplazar `throw new \Exception()` con excepciones de dominio en `CreateTenantAction` | 🔥 Crítica | Baja        |
| 5   | B-06 | Agregar Isolation Tests para modelos tenant-scoped                                     | 🔥 Crítica | Media       |
| 6   | M-07 | Agregar `tenancy()->end()` en finally de loops multi-tenant                            | 🔥 Crítica | Baja        |

#### Fase 2: 🛠 REFACTOR — Integridad arquitectónica

| #   | ID   | Acción                                                                                          | Prioridad | Complejidad |
| --- | ---- | ----------------------------------------------------------------------------------------------- | --------- | ----------- |
| 7   | B-03 | Extraer lógica de `PaguelofacilCallbackController` a `ProcessPaguelofacilCallbackAction`        | 🛠 Alta    | Media       |
| 8   | B-02 | Unificar stack UI en `BASE.md`                                                                  | 🛠 Alta    | Baja        |
| 9   | M-03 | Desacoplar `auth('central')->id()` de Actions (pasarlo como parámetro)                          | 🛠 Alta    | Baja        |
| 10  | M-01 | Decidir destino de `CheckSubscription` middleware (eliminar o aplicar)                          | 🛠 Media   | Baja        |
| 11  | M-05 | Documentar ownership mixto en Payments/Landings; mover `CheckoutController` a Tenant si procede | 🛠 Media   | Alta        |
| 12  | M-06 | Reemplazar `catch (\Exception $e)` con excepciones específicas en Controllers                   | 🛠 Media   | Baja        |

#### Fase 3: 📌 MEJORA — Calidad interna

| #   | ID            | Acción                                                                                                                  | Prioridad | Complejidad |
| --- | ------------- | ----------------------------------------------------------------------------------------------------------------------- | --------- | ----------- |
| 13  | M-02          | Agregar migraciones `ENABLE ROW LEVEL SECURITY` para tablas tenant-scoped                                               | 📌 Alta    | Media       |
| 14  | M-08          | Implementar `TenantSuspendedException`, `QuotaExceededException`, `CrossTenantAccessAttempt`, `PlanFeatureAccessDenied` | 📌 Media   | Baja        |
| 15  | B-01          | Crear `docs/03_UseCases.md` y `docs/06_CurrentState.md`                                                                 | 📌 Media   | Alta        |
| 16  | C-01/02/03/04 | Aplicar `final readonly` faltantes en Actions y Models                                                                  | 📌 Baja    | Baja        |
| 17  | C-07          | Completar migración a `ProvisioningJob`, eliminar `ProvisionTenantJob`                                                  | 📌 Media   | Media       |

### Riesgos de corrección

- **Migración de ProvisionTenantJob → ProvisioningJob**: Posibles trabajos encolados con modelo serializado. Requiere draining de cola o compatibilidad backward. Cambio de datos en `provisioning_logs` si cambia schema.
- **Agregar RLS a tablas existentes**: Puede afectar queries de Central que acceden a tablas tenant-scoped. Requiere revisar todos los accesos.
- **Mover CheckoutController**: Impacta rutas existentes (`payments/checkout/initiate`). Requiere redirects o mantener backward compatibility temporal.
- **Aislar Jobs con tenancy initialization**: Jobs actualmente sin inicialización pueden fallar si el Action espera tenancy initialized.

### Validación posterior

Cada corrección debe validar:

1. ✅ **Isolation test**: Tenant A no accede datos de Tenant B (404)
2. ✅ **Feature test**: Módulo funciona correctamente
3. ✅ **Authorization test**: Acceso no autorizado es bloqueado
4. ✅ **Queue test**: (si aplica) Job se ejecuta en contexto correcto
5. ✅ **Regression test**: No se rompen funcionalidades existentes

La corrección no se considera completa hasta validar que:
- No existe cross-tenant access
- El módulo respeta su bounded context
- Las reglas de arquitectura (`BASE.md`) siguen vigentes
- Los tests cubren los riesgos corregidos
