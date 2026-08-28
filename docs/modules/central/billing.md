# Auditoría — Central/Billing

> Fecha: 2026-08-27 | Estado: 🔴 Requiere intervención (2× P0 críticos, 4× P1 altos — no ocultar)

## 1. Resumen ejecutivo (Riesgos principales)

El módulo **Central/Billing** gestiona el perímetro de dinero: checkout, webhooks, suscripciones y cobro recurrente. La inspección integral (Rutas → Controllers → Actions/Gateways → Models/DB) confirma una base sólida (RLS en `payments`, idempotencia en webhooks, `TenantAware` + `RehydrateTenantContext` en jobs) pero **2 vulnerabilidades P0 bloquean el pase a producción sin parche**.

- **P0 — Activación de suscripción falsificable vía callback del navegador** (`PaguelofacilCallbackController::handleReturn`). El redirect `GET /central/billing/paguelofacil/callback` crea/actualiza `subscriptions` y `tenants.plan_id` sin verificar firma HMAC ni monto, solo leyendo `PARM_1`/`PARM_2`/`Estado` del query string controlado por el usuario. Un atacante puede activar cualquier plan sin pagar.
- **P0 — Falta de constraint de integridad a nivel DB y ventana de doble-cobro**: `payments.slug` es único pero `payments(display_id)` no tiene unicidad por tenant; `CheckoutManager::initiate` crea `Payment`+`PaymentAttempt` sin `unique(tenant_id,display_id)` ni lock, y `ChargeSubscriptionAction` valida idempotencia con `exists()` fuera de transacción con lock → carrera que permite doble `Invoice`/`Payment` en retry concurrente o reintento del scheduler.
- **P1 — Riesgo sistémico**: `billing:process-recurring` sin lock distribuido dispara `ChargeSubscriptionJob` duplicado si dos workers/crons se solapan; `BillingServiceProvider` resuelve `PaymentGateway` vía `tenant('billing_gateway')` en momento de resolución del contenedor (null en CLI/cola → fallback silencioso a `clave` para suscripciones `dlocal`); N+1 leve en listados; falta de `CrossTenantLeakTest` específico del dominio Billing pese a ser tenant-aware.

**Salud global: 3/8 dominios en rojo, 3 en amarillo.** No penaliza ausencia de Repository/CQRS: el CRUD con Actions + Gateways es adecuado. Los fixes son quirúrgicos (ver Ruta de trabajo).

## 2. Alcance (Áreas inspeccionadas)

- **Rutas**: `app/Modules/Central/Billing/Interface/Routes/{web,payments,tenant}.php:1`, `app/Modules/Platform/Integrations/Dlocal/Interface/Routes/*` — middleware `auth:central`, `tenant`, `throttle:webhooks`, `withoutMiddleware` en webhooks.
- **Controllers/Livewire**: `CheckoutController.php:24`, `WebhookController.php:26`, `StripeWebhookController.php:15`, `PaguelofacilCallbackController.php:24`, `BillingApiController.php:21`, `CheckoutComponent.php:93`, `ManagePlan.php:70`, `GlobalInvoiceList.php:18`, `SubscriptionList.php:18`, `TenantInvoiceList.php:18`, `HostedCheckout.php:15`.
- **Dominio/Aplicación**: `Application/Actions/{ChargeSubscriptionAction,InitiateCheckout,UpsertPlan,CancelSubscription,HandleWebhook,CreateCheckoutSession}.php`, `Application/Jobs/{ChargeSubscriptionJob,ProcessPaymentWebhookJob}.php`, `Application/Services/{BillingExportService,PaymentAmountResolver}.php`, `Domain/Models/{Payment,Invoice,Subscription,PaymentAttempt,PaymentWebhook,PaymentGatewayEvent}.php`.
- **Infraestructura**: `Infrastructure/Gateways/{CheckoutManager,PaymentVerifier,DlocalGateway,ClaveGateway,BillingManager,StripeBillingProvider,PaymentGateway}.php`, `Infrastructure/Console/{ProcessRecurringChargesCommand,ReconcileSubscriptionsCommand}.php`.
- **DB**: `database/migrations/2026_06_07_010000_create_payments_tables.php:14`, `2026_06_02_131559_create_central_invoices_table.php`, `2026_06_08_204816_create_subscriptions_table.php`, `2026_08_16_200000_add_recurring_columns_to_subscriptions_table.php`, `2019_09_15_000010_create_tenants_table.php:15`.
- **Tests**: `tests/Feature/{BillingTest,BillingFlowTest,RecurringBillingTest,PaymentsIntegrationTest,ClaveGatewayTest,Dlocal*}.php`, `tests/Feature/RLSIsolationTest.php:20`, `tests/Feature/Metering/CrossTenantLeakTest.php:10`.
- **Dependencias externas**: `Platform/Tenancy` (`ScopedToTenant`, `TenantScope`, `RehydrateTenantContext.php:15`), `Platform/Contracts/TenantAware`, `Platform/Integrations/Dlocal/Client/DlocalHttpClient`, `PagueloFacil` HTTP, `Stripe` (Cashier).
- **No inspeccionado** (fuera de alcance Billing): `Central/Catalog` pricing, `Central/Provisioning` lifecycle completo, `Tenant/Workspace` billing UI tenant.

## 3. Arquitectura actual (Flujo de funcionamiento)

```
[Browser/Livewire] --POST /payments/checkout/initiate--> CheckoutController::initiate
        |  tenant() + PaymentAmountResolver -> PaymentData -> InitiateCheckout -> CheckoutManager::initiate (DB txn)
        |  ├─ DIRECT (dlocal+token): gateway->processDirectPayment -> Payment(pending->approved/declined) + PaymentAttempt + events CheckoutSessionCreated/PaymentApproved|Declined
        |  └─ REDIRECT (clave): gateway->buildCheckoutUrl -> Payment(pending) + PaymentAttempt, return checkoutUrl -> redirect gateway
        |
[Gateway Hosted] --redirect--> PaguelofacilCallbackController::handleReturn (GET, sin firma) --DIRECT DB update--> subscriptions + tenants.plan_id -> redirect tenant domain /billing/success|cancel
[Gateway Server] --POST /webhooks/clave|dlocal--> WebhookController::handle (verify HMAC sync, resolveTenantId, dispatch ProcessPaymentWebhookJob)
        |  ProcessPaymentWebhookJob (TenantAware, RehydrateTenantContext SET LOCAL + tenancy()->initialize)
        |  -> HandleWebhook -> PaymentVerifier::handleWebhook (verify again, lock cache, txn, recordWebhook idempotente, reconcilePayment lockForUpdate)
        |
[Scheduler] --billing:process-recurring--> ProcessRecurringChargesCommand chunkById -> ChargeSubscriptionJob(tenantId, subscriptionId) TenantAware
        |  -> ChargeSubscriptionAction::execute (find, gateway!=dlocal skip, idempotency exists, DlocalGateway::chargeSubscription, handleSuccess/Decline txn)
        |
[Stripe] --POST /central/webhooks/stripe--> StripeWebhookController::handleWebhook (PaymentGatewayEvent idempotency, parent Cashier webhook, TenantReactivatedAfterPayment)
```

Módulo sigue **Modular Monolith + Actions + Gateways + Events**; `BillingManager` (Manager) enruta por `tenant.billing_gateway` → `ClaveGateway`/`DlocalGateway`/`StripeBillingProvider`. RLS habilitado en `payments`, `payment_attempts`, `payment_webhooks` (`2026_06_07_010000:78`).

## 4. Dependencias (Acoplamiento interno/externo)

**Interno (permitido pero acoplado):**

- `Billing` → `Catalog` (Plan model directo `PlanManager::getStripeId`, `ManagePlan::render` carga `Feature::where`) — viola ARCHITECTURE_RULES.md “ningún módulo accede directamente a Models de otro módulo”; debería usar Contract/Actions públicas de Catalog. Riesgo bajo hoy pero acopla releases.
- `Billing` → `Provisioning` (Tenant model directo en Actions/Controllers/Jobs) — esperado como Bound Bounded Context Central, pero también acceso directo sin Contract `TenantContract`.
- `Subscription` extiende `Laravel\Cashier\Subscription` + `HasUuids` con `$fillable` mixto — acopla Cashier al dominio; migración original usa `id bigIncrements` pero modelo usa UUID (ver B008).
- `CheckoutComponent` y `BillingServiceProvider` leen `tenant('billing_gateway')` global helper (acoplamiento a Tenancy resolver global, frágil bajo Octane/cola).

**Externo:**

- `DlocalHttpClient` / `PagueloFacil` HTTP (`ClaveGateway::post` timeout 5s, retry 1), `Stripe` SDK vía Cashier, `barryvdh/laravel-dompdf` (invoices), `spatie/laravel-activitylog` (audit). Pesos: gateway timeouts sin circuit breaker → latencia cascada (ver B015).

**Dirección correcta:** `Platform/Tenancy` y `Platform/Contracts` como base; `Platform/Events` (`PaymentWebhookReceived`, `TenantSuspendedByDunning`) bien desacoplado vía Events.

## 5. Health Score (Tabla cualitativa por Dominio: 🟢/🟡/🔴)

| Dominio                | Score | Justificación                                                                                                                                                                       |
| ---------------------- | ----- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Arquitectura           | 🟡    | Actions pequeñas, Gateways con contrato, pero acceso directo a `Plan`/`Tenant` Models rompe regla de aislamiento; `BillingManager` Manager correcto.                                |
| Backend (Laravel)      | 🟡    | Eloquent scopes `ScopedToTenant` + RLS bien, pero `Payment::where exists` sin lock, `Subscription::find` sin scope explícito en job, `tenant()` helper en ServiceProvider.          |
| Base de Datos          | 🔴    | RLS ok en payments, pero falta FK `tenant_id→tenants`, falta `unique(tenant_id,display_id)`, `subscriptions` PK mismatch (auto-increment vs UUID), `invoices` sin RLS.              |
| Frontend (Livewire)    | 🟢    | `CheckoutComponent` Locked props, `WithPagination` leve, sin re-renders graves; payloads acotados.                                                                                  |
| Seguridad              | 🔴    | P0 callback sin firma (IDOR financiero), webhook tenant-resolution spoofeable, `GenerateInvoicePdf` sin Policy/IDOR check.                                                          |
| Performance            | 🟡    | Chunk 100 en recurring, cache lock 10s en webhooks bien; pero `BillingApiController::listInvoices` sin paginación acotada + `latest()->paginate` sin índice `status` compuesto.     |
| Testing                | 🟡    | Cobertura de checkout/Direct/dLocal/SmartFields + RLS genérico, pero **0 CrossTenantLeakTest específico Billing**, no test de doble-cobro concurrente, no test de callback forgery. |
| DevOps / Observability | 🟡    | `activity('billing')` + `Log::warning` en webhooks, jobs con `tries=3 backoff`, pero sin métricas de dunning/suspensión, sin DLQ, sin alerta `INSUFFICIENT_AMOUNT`.                 |

## 6. Hallazgos (Listado detallado con formato P0-P3)

### [ID: B001] [P0 Crítico] Activación de suscripción falsificable vía callback GET sin verificación de firma ni monto

- **Categoría:** Security
- **Ubicación:** `app/Modules/Central/Billing/Interface/Http/Controllers/PaguelofacilCallbackController.php:24` (`handleReturn`), `:36-66` crea `Subscription::updateOrCreate` y `Tenant::update(['plan_id'])`
- **Problema y Evidencia:** Endpoint público `GET /central/billing/paguelofacil/callback` (definido en `Interface/Routes/web.php:21` sin `auth` ni `throttle`) confía en `PARM_1` (tenantId), `PARM_2` (planId) y `Estado`/`status` del query string del navegador. No llama a `PaymentGateway::verifyWebhook` ni valida `gateway_reference` ni `amount` contra `Payment` registrado. El atacante puede ejecutar `curl "…/callback?PARM_1=<victim-tenant-uuid>&PARM_2=pro&Estado=Aprobada&codOper=FAKE"` y obtener `status=active` + `plan_id=pro` sin pago. **Confirmado**: código solo hace `PaymentResultData::fromClavePayload($request->all())` (`:31`) y `if ($result->status !== Approved || amount<=0) redirect cancel` — `amount` viene del query, no de DB. No hay `Payment::where...->exists()` como en `PaymentVerifier`.
- **Impacto y Recomendación:** Pérdida financiera directa, escalada de privilegios de plan, bypass de billing. **Mitigación inmediata**: Hacer callback solo UX (redirect), mover creación de `Subscription` al webhook verificado (`PaymentVerifier::reconcilePayment`). Si se debe mantener callback síncrono, verificar vía `ClaveGateway::verifyWebhook` o consultar `listTransactions`/`getSubscriptionData` al gateway antes de mutar DB. Añadir `auth` o al menos `signed` URL y rate limit.
- **Complejidad / Prioridad:** Alta / Inmediata (Bloqueador de release)

### [ID: B002] [P0 Crítico] Ventana de carrera en idempotencia de pagos permite doble cobro / doble factura

- **Categoría:** Database | Backend
- **Ubicación:** `app/Modules/Central/Billing/Infrastructure/Gateways/CheckoutManager.php:34-55` (crea `Payment` sin unique por `display_id`), `app/Modules/Central/Billing/Application/Actions/ChargeSubscriptionAction.php:59` (`Payment::where('display_id')->where(Approved)->exists()` sin lock), `app/Modules/Central/Billing/Domain/Models/Payment.php:37` (`display_id` sin índice único)
- **Problema y Evidencia:** `payments.slug` es único, pero `display_id` (idempotency key de negocio) solo tiene `index(tenant_id,display_id)` (`migrations/2026_06_07_010000:34`), no `unique`. `CheckoutManager::initiate` dentro de `DB::transaction` crea `Payment` sin `firstOrCreate` ni `SELECT ... FOR UPDATE`. Si el usuario doble-click o el frontend reintenta, se insertan dos `payments` con distinto `slug` pero mismo `display_id` y dos `PaymentAttempt`. `ChargeSubscriptionAction` verifica `exists()` antes del `gateway->chargeSubscription` pero sin `lockForUpdate` ni `unique` constraint; entre `exists()` y `Payment::create` en `handleSuccess` otro worker puede insertar `Approved`. **Confirmado** con lectura de migrations y código.
- **Impacto y Recomendación:** Doble cobro al cliente, doble `Invoice::create` (`:108`), inconsistencia contable, disputas Stripe/dLocal. Añadir `unique(['tenant_id','display_id'])` donde display_id es periodal (`sub_<id>_YYYY-MM`) o idempotency global; envolver verificación+creación en `SELECT ... FOR UPDATE` o `INSERT ... ON CONFLICT DO NOTHING` con manejo. Considerar `PaymentAttempt` por retry pero `Payment` único.
- **Complejidad / Prioridad:** Media / Inmediata

### [ID: B003] [P1 Alto] Resolución de tenant en webhook confía en payload metadata manipulable pre-verificación completa

- **Categoría:** Security
- **Ubicación:** `app/Modules/Central/Billing/Interface/Http/Controllers/WebhookController.php:54-101` (`resolveTenantId` prioriza `payload['PARM_1'] / tenant_id / metadata.tenant_id`), `:40-52` verifica firma pero con secreto genérico `config("payments.{$gateway}.webhook_secret")` sin vincular a tenant
- **Problema y Evidencia:** `resolveTenantId` extrae `tenantId` del JSON antes de validar que ese `display_id` pertenece al tenant; aunque `PaymentVerifier` luego hace `Payment::withoutGlobalScopes()->where(tenant_id, tenantId)->where(displayId)->exists()` (`PaymentVerifier.php:65`), el `ProcessPaymentWebhookJob` ya fue encolado con `tenantId` spoofeable. Un payload firmado correctamente (si el gateway firma todo el body, el atacante no puede forjar firma) es seguro, pero si el gateway usa `tenant_id` de `customFieldValues` (en `DlocalGateway::buildCheckoutUrl:81` `metadata tenant_id`) el atacante puede iniciar checkout con `tenant_id` de otro tenant (si logra autenticarse como otro tenant? no, pero en flujo `CheckoutController` el `tenantId` viene de `tenant('id')` autenticado, mitigado). **Riesgo**: payloads `clave` con `PARM_1` hex pueden ser manipulados si el secreto de webhook es compartido o rotado incorrectamente. Además `webhookSecret = config("payments.{$gateway}.webhook_secret")` único global, no por tenant → si un tenant compromete el secret, afecta a todos.
- **Impacto y Recomendación:** Envenenamiento de logs `payment_webhooks` con `tenant_id` erróneo hasta que `exists()` lo descarte; cola `webhooks-priority` saturable. **Recomendación**: Resolver tenant vía `PaymentReference::where external_reference` o `Payment::where slug/display_id` antes de encolar, o validar que `tenantId` del payload coincide con `Payment.tenant_id` dentro del job y descartar si mismatch; migrar a secrets por tenant/gateway si multi-merchant.
- **Complejidad / Prioridad:** Media / Sprint

### [ID: B004] [P1 Alto] Scheduler de cobro recurrente sin lock distribuido — duplicación de jobs en despliegue multi-replica

- **Categoría:** Backend | DevOps
- **Ubicación:** `app/Modules/Central/Billing/Infrastructure/Console/ProcessRecurringChargesCommand.php:21` (`Subscription::query()->where(status active)->where next_payment_at ... chunkById 100`), `app/Modules/Central/Billing/Application/Jobs/ChargeSubscriptionJob.php:24` (`tries 3 backoff [300,1800]` sin `unique`)
- **Problema y Evidencia:** Command no adquiere `Cache::lock('billing:process-recurring', 300)` ni usa `ShouldBeUnique`. Si Horizon despliega 2 pods o cron se solapa, ambos `chunkById` despachan `ChargeSubscriptionJob` duplicado para la misma `subscriptionId`. `ChargeSubscriptionAction::exists()` mitiga parcialmente pero queda ventana de carrera entre `exists()` y `chargeSubscription` (llamada externa a dLocal) → doble cargo real al gateway. Logs muestran `tenant_suspended_by_dunning` sin correlación con `lock`.
- **Impacto y Recomendación:** Doble cobro mensual, soporte, reembolsos. Envolver command en `Cache::lock` o `withoutOverlapping()` en scheduler; hacer job `ShouldBeUnique` con `uniqueId = subscriptionId+period`; idempotency a nivel gateway (`orderId = sub_<id>_YYYY-MM` ya es determinista, bien, pero falta `PaymentReference::firstOrCreate` con `external_reference` único).
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: B005] [P1 Alto] Binding de `PaymentGateway` resuelve `tenant('billing_gateway')` fuera de contexto (CLI/Queue/Octane) → fallback silencioso a Clave para suscripciones dLocal

- **Categoría:** Architecture | Backend
- **Ubicación:** `app/Modules/Central/Billing/Providers/BillingServiceProvider.php:51` (`$gateway = tenant('billing_gateway') ?? config('payments.default', 'clave')`), `app/Modules/Central/Billing/Infrastructure/Gateways/BillingManager.php:14` (`forTenant` bien, pero provider binding no lo usa)
- **Problema y Evidencia:** `tenant()` helper depende de `TenancyServiceProvider` resolver dominio del request. En cola (`ChargeSubscriptionJob` con `tenantId` explícito), en `artisan billing:process-recurring`, y en Octane tras `tenancy()->end()`, `tenant()` retorna null → siempre `clave`. `ChargeSubscriptionAction:41` hace `if gateway !== dlocal skip` pero luego `app(DlocalGateway::class)->chargeSubscription` hardcoded, incoherente; si el tenant es `dlocal` pero el binding resolvió `ClaveGateway` para checkout, `CheckoutManager` cobrará por Clave. **Confirmado**: `BillingManager::forTenant` sí respeta `Tenant.billing_gateway`, pero `CheckoutManager` y `PaymentVerifier` inyectan `PaymentGateway` genérico del binding, no `forTenant`.
- **Impacto y Recomendación:** Cobros por gateway equivocado, `supportsDirectPayment` falso/verdadero erróneo, pérdida de `pm_card_id` de dLocal. Inyectar `BillingManager` y resolver `forTenant($tenant)` explícitamente en `CheckoutManager`, `PaymentVerifier`, `ChargeSubscriptionAction`; evitar `tenant()` global en providers.
- **Complejidad / Prioridad:** Media / Sprint

### [ID: B006] [P1 Alto] Falta de `CrossTenantLeakTest` específico del dominio Billing (violación Definition of Done)

- **Categoría:** Testing
- **Ubicación:** `tests/Feature/Metering/CrossTenantLeakTest.php:10` existe solo para Metering; `tests/Feature/RLSIsolationTest.php:20` genérico `tenant_api_keys`; **ausente** `tests/Feature/Billing/CrossTenantLeakTest.php`
- **Problema y Evidencia:** `payments` y `payment_webhooks` tienen RLS (`migrations 2026_06_07:78`) y `ScopedToTenant` (`Payment.php:35`, `PaymentWebhook.php:28`), pero no hay test que intente `Payment::where display_id` de otro tenant, ni `withoutGlobalScopes` bypass, ni “conexión reutilizada” con `SET LOCAL` previo. `ARCHITECTURE_RULES.md` y `PROJECT_DECISIONS.md §14` exigen `CrossTenantLeakTest` por módulo Tenant-aware como DoD. **Confirmado** vía `grep CrossTenant`.
- **Impacto y Recomendación:** Regresión silenciosa si se deshabilita RLS o se olvida `ScopedToTenant` en nuevo modelo `Invoice` (que **no** usa `ScopedToTenant` — ver B008). Crear `tests/Feature/Billing/CrossTenantLeakTest.php` con dataset tenant A/B y asserts de `assertDatabaseMissing` + RLS `current_setting`.
- **Complejidad / Prioridad:** Baja / Sprint

### [ID: B007] [P2 Medio] `Invoice` y `Subscription` sin `ScopedToTenant` ni RLS — aislamiento solo vía PHP

- **Categoría:** Database | Security
- **Ubicación:** `app/Modules/Central/Billing/Domain/Models/Invoice.php:13` (`use HasUuids` sin `ScopedToTenant`), `app/Modules/Central/Billing/Domain/Models/Subscription.php:13` (`extends CashierSubscription` sin scope), `database/migrations/2026_06_02_131559_create_central_invoices_table.php:15`, `database/migrations/2026_06_08_204816_create_subscriptions_table.php:10`
- **Problema y Evidencia:** `Invoice` tiene `tenant_id` pero no aplica `TenantScope` ni RLS (migración no habilita `ENABLE ROW LEVEL SECURITY`). `Subscription` (Cashier) usa `user_id` legacy `unsignedBigInteger` y `stripe_id` sin FK a tenants UUID. Listados `GlobalInvoiceList::render:21` `Invoice::with('tenant')->latest()` y `BillingApiController::listInvoices:92` `Invoice::query()->where tenant_id if present` dependen de `where` manual. Si un developer olvida el filtro, fuga cross-tenant. `TenantInvoiceList` sí filtra (`TenantInvoiceList.php:22`).
- **Impacto y Recomendación:** Fuga de facturas entre tenants vía olvido de `where`. Añadir `ScopedToTenant` a `Invoice` (y evaluar `Subscription` con `TenantScope` + migrar `user_id` a `tenant_id uuid` o mantener compat Cashier vía accessor), habilitar RLS y `php artisan tenancy:enable-rls invoices subscriptions`.
- **Complejidad / Prioridad:** Media / Backlog (tras P0)

### [ID: B008] [P2 Medio] Integridad referencial delegada solo a PHP — sin FKs ni `ON DELETE` en `payments`/`invoices`

- **Categoría:** Database
- **Ubicación:** `database/migrations/2026_06_07_010000:32-36` índices sin `foreign('tenant_id')->references('id')->on('tenants')`, `database/migrations/2026_06_02_131559_create_central_invoices_table.php:15` `tenant_id` sin FK
- **Problema y Evidencia:** `payments.tenant_id` es `uuid` sin FK; `invoices.tenant_id` igual; solo `payment_attempts.payment_id` tiene FK. `Tenant::delete` soft-delete no cascada, pero `Payment::create` en `CheckoutManager:36` y `ChargeSubscriptionAction:85` no valida `Tenant::exists` con FK DB. Si se purga un tenant, quedan huérfanos. Postgres no impide `tenant_id` inexistente.
- **Impacto y Recomendación:** Datos huérfanos, reportes incorrectos. Añadir FKs con `nullOnDelete` o `cascade` según política de retención (GDPR: anonimizar, no borrar). No es bloqueador pero es deuda P2.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: B009] [P2 Medio] `GenerateInvoicePdf` y `BillingApiController::listInvoices` sin Policy/Gate — IDOR para staff central

- **Categoría:** Security
- **Ubicación:** `app/Modules/Central/Billing/Interface/Routes/web.php:39` (`Route::get('/central/billing/invoices/{invoice}/pdf', fn(Invoice $invoice, GenerateInvoicePdf $action) => $action->download($invoice))`), `app/Modules/Central/Billing/Application/Actions/GenerateInvoicePdf.php:18` sin `authorize`, `app/Modules/Central/Billing/Interface/Http/Controllers/BillingApiController.php:90` (`Invoice::query()` sin scope por rol)
- **Problema y Evidencia:** Ruta bajo `auth:central` pero sin `can:view,invoice` ni `TenantPolicy`. Cualquier `CentralUser` autenticado puede enumerar `invoice IDs` (UUID, pero paginable vía `listInvoices`) y descargar PDF de otro tenant. Aunque Central staff debe ver todo, falta `Gate::authorize('viewAny', Invoice::class)` para futuro RBAC central (Support vs Finance).
- **Impacto y Recomendación:** Bajo hoy (staff = trusted), pero viola `ARCHITECTURE_RULES.md` “validar autorización, nunca confiar solo en middleware”. Añadir `Gate` o `Policy` y test de `403` para rol no autorizado.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: B010] [P2 Medio] `PaymentResultData::fromClavePayload` parsing frágil — múltiples claves sin contrato, monto con `float`

- **Categoría:** Backend
- **Ubicación:** `app/Modules/Central/Billing/Application/DTO/PaymentResultData.php:31` (`match` sobre `status`, `codOper/Oper/transactionId`, `totalPay/TotalPagado/amount`), `app/Modules/Central/Billing/Domain/Models/Payment.php:55` (`amount` cast `float`)
- **Problema y Evidencia:** Payload de PagueloFacil tiene variantes `status` (1/0), `Estado` (Aprobada/Denegada), `approved` flag — parsing heurístico sin schema versionada. `amount` como `float` pierde precisión monetaria (debería ser `int cents` + `MoneyCast` como `Plan`). `Payment::amount` usa `float` mientras `Invoice::amount` usa `MoneyCast` (`Invoice.php:30`) — inconsistencia. Test `ClaveGatewayTest` no cubre todas las ramas heurísticas.
- **Impacto y Recomendación:** Redondeo, `PartialPayment` mal detectado (`PaymentVerifier:162` compara `payment->amount > result->amount` con floats). Migrar a `int` cents + `MoneyCast` y validar payload con `spatie/laravel-data` o JSON schema por versión de gateway.
- **Complejidad / Prioridad:** Media / Backlog

### [ID: B011] [P2 Medio] `CheckoutComponent` expone `amount`/`displayId` como `#[Locked]` pero `PaymentAmountResolver` no revalida tenant ownership

- **Categoría:** Backend | Frontend
- **Ubicación:** `app/Modules/Central/Billing/Interface/Livewire/CheckoutComponent.php:38` (`#[Locked] public float $amount`), `:102` (`PaymentData amount: $this->amount`), `app/Modules/Central/Billing/Interface/Http/Controllers/CheckoutController.php:36` (`$amountResolver->resolveAmount($data['display_id'])` solo valida existencia, no tenant)
- **Problema y Evidencia:** `#[Locked]` impide tampering de props en el wire payload (Livewire 4), mitigado. Pero `CheckoutController::initiate` acepta `display_id` arbitrario y resuelve monto vía `PaymentAmountResolverContract`. Si el resolver solo hace `Plan::find(display_id)->price` sin verificar `tenant_id`, un tenant podría pagar por `display_id` de otro tenant y contaminar `Payment.tenant_id` (que viene de `tenant('id')` autenticado, por lo que el pago quedaría a su nombre pero con precio de otro plan — no crítico). Riesgo real: `amount` enviado por Livewire (`$this->amount`) es usado directamente en `CheckoutManager:40` `Payment::create amount: $data->amount` sin re-derivar del plan en servidor para flujo DIRECT. Un usuario podría manipular `$amount` antes del `mount` (aunque `Locked` lo protege post-mount, el `mount` inicial viene de Blade `amount` prop controlado por servidor, pero el atacante puede re-renderizar con `amount=0.01` si el componente es invocado sin validación de plan).
- **Impacto y Recomendación:** Pago sub-valorado, `INSUFFICIENT_AMOUNT` solo detectado en webhook (`PaymentVerifier:162`), no en checkout síncrono. Derivar monto siempre en servidor desde `Plan`/`Invoice` por `displayId` y rechazar `amount` del cliente si difiere > epsilon.
- **Complejidad / Prioridad:** Media / Sprint

### [ID: B012] [P2 Medio] `ChargeSubscriptionAction` mezcla gateway hardcoded `DlocalGateway` y `Subscription` con `HasUuids` vs Cashier `bigIncrements`

- **Categoría:** Architecture
- **Ubicación:** `app/Modules/Central/Billing/Application/Actions/ChargeSubscriptionAction.php:71` (`app(DlocalGateway::class)->chargeSubscription`), `:35` (`Subscription::find($subscriptionId)` usa `HasUuids` pero migración `subscriptions` usa `id()` bigInt), `app/Modules/Central/Billing/Domain/Models/Subscription.php:15` (`use HasUuids`)
- **Problema y Evidencia:** Inyección directa del gateway rompe Inversión de Dependencias (`PaymentGateway` contract debería resolver `forTenant`). Además `Subscription` modelo usa UUID pero la tabla `subscriptions` creada por `2026_06_08_204816` usa `id()` autoincremental — en SQLite tests funciona, pero en Postgres con `HasUuids` el `id` será UUID string vs bigInt, causando `invalid input syntax for type bigint`. **Confirmado** comparando modelo y migración.
- **Impacto y Recomendación:** Fallo en producción Postgres al crear `Subscription` vía `PaguelofacilCallbackController:63` `Subscription::updateOrCreate`. Unificar: o migrar tabla a `uuid` PK o remover `HasUuids` del modelo y usar `bigIncrements` + `tenant_id` UUID separado.
- **Complejidad / Prioridad:** Alta / Sprint

### [ID: B013] [P3 Bajo] `PaymentWebhook` inmutable vía `static::updating(fn()=>false)` pero `guarded=['updated_at']` incompleto — permite `delete`

- **Categoría:** Backend
- **Ubicación:** `app/Modules/Central/Billing/Domain/Models/PaymentWebhook.php:32` (`$guarded = ['updated_at']`), `:54` (`static::updating(fn()=>false)`)
- **Problema y Evidencia:** Intención “append-only” se implementa bloqueando `updating` pero no `deleting` ni `forceDelete`. Un `PaymentWebhook::where(...)->delete()` por error borraría logs de auditoría de pagos.
- **Impacto y Recomendación:** Bajo (requiere acceso DB/app). Añadir `static::deleting(fn()=>false)` o usar `DB` policy + test.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: B014] [P3 Bajo] Duplicación de lógica `bin2hex(route(...))` y resolución de `baseUrl` en callbacks

- **Categoría:** Architecture
- **Ubicación:** `app/Modules/Central/Billing/Infrastructure/Gateways/ClaveGateway.php:82` (`bin2hex(route('central.billing.paguelofacil.callback'))`), `app/Modules/Central/Billing/Interface/Http/Controllers/PaguelofacilCallbackController.php:36` y `app/Modules/Platform/Integrations/Dlocal/Interface/Http/Controllers/DlocalCallbackController.php:25` (resolución de `scheme://domain:port`)
- **Problema y Evidencia:** Tres sitios reconstruyen `baseUrl` con `parse_url(config('app.url'))` y `tenant->domains()->first()`. `ClaveGateway` hex-encodea `RETURN_URL` pero no documenta por qué `PARM_1` es `tenantId` en clair dentro de `PF_CF`.
- **Impacto y Recomendación:** Mantenimiento, bugs de puerto en local. Extraer `TenantDomainResolverContract` + `UrlBuilder` central.
- **Complejidad / Prioridad:** Baja / Backlog

### [ID: B015] [P3 Bajo] Observabilidad incompleta — sin métricas ni alerta en `dunning` y `PartialPayment`

- **Categoría:** DevOps
- **Ubicación:** `app/Modules/Central/Billing/Application/Actions/ChargeSubscriptionAction.php:164` (`Log::alert('Monto insuficiente...')`), `:179` (`TenantSuspendedByDunning::dispatch` sin listener de métrica), `app/Modules/Platform/Events/TenantSuspendedByDunning.php`
- **Problema y Evidencia:** `Log::alert` no genera métrica Prometheus/Horizon ni notificación a Slack/Ops. `failed_attempts` incrementado sin `Cache::increment` con TTL, sin dashboard.
- **Impacto y Recomendación:** Ops ciego ante churn por fallos de cobro. Añadir `Metrics::increment('billing.dunning.suspended')` y listener.
- **Complejidad / Prioridad:** Baja / Backlog

## 7. Matriz de riesgos (Tabla: ID | Severidad | Categoría | Impacto | Complejidad)

| ID   | Severidad  | Categoría         | Impacto                                                 | Complejidad |
| ---- | ---------- | ----------------- | ------------------------------------------------------- | ----------- |
| B001 | P0 Crítico | Security          | Activación de plan sin pago, pérdida financiera directa | Alta        |
| B002 | P0 Crítico | Database/Backend  | Doble cobro, facturas duplicadas, disputas              | Media       |
| B003 | P1 Alto    | Security          | Envenenamiento de cola webhooks, spoofing tenant        | Media       |
| B004 | P1 Alto    | Backend/DevOps    | Jobs duplicados en multi-replica, doble cargo           | Baja        |
| B005 | P1 Alto    | Architecture      | Gateway equivocado, cobro fallido silencioso            | Media       |
| B006 | P1 Alto    | Testing           | Sin DoD CrossTenantLeak, regresión RLS silenciosa       | Baja        |
| B007 | P2 Medio   | Database/Security | Fuga de facturas/suscripciones sin scope                | Media       |
| B008 | P2 Medio   | Database          | Huérfanos tenant, integridad huérfana                   | Baja        |
| B009 | P2 Medio   | Security          | IDOR PDFs para staff sin Policy                         | Baja        |
| B010 | P2 Medio   | Backend           | Parsing frágil, redondeo monetario                      | Media       |
| B011 | P2 Medio   | Backend/Frontend  | Monto manipulable, sub-pago                             | Media       |
| B012 | P2 Medio   | Architecture      | PK mismatch UUID/BigInt, fallo Postgres                 | Alta        |
| B013 | P3 Bajo    | Backend           | Borrado de webhooks append-only                         | Baja        |
| B014 | P3 Bajo    | Architecture      | Duplicación URL builder                                 | Baja        |
| B015 | P3 Bajo    | DevOps            | Ciego ante dunning                                      | Baja        |

## 8. Ruta de trabajo (Fases ordenadas por dependencia: 0. Bloqueadores -> 1. Riesgos -> 2. Estabilización)

**Fase 0 — Bloqueadores (Semana 1, no desplegar sin esto)**

1.  **B001**: Convertir `PaguelofacilCallbackController::handleReturn` en solo-redirect UX; mover mutación `Subscription/Tenant.plan_id` a `PaymentVerifier::reconcilePayment` o validar con `ClaveGateway::verifyWebhook` + consulta al gateway. Añadir `throttle` + `signed` URL. Test: `curl` con `Estado=Aprobada` falsificado debe dar `302 /billing/cancel` sin crear registro.
2.  **B002**: Añadir `unique(['tenant_id','display_id'])` en `payments` (o `slug` periodal único) + `FOREIGN KEY` checks; envolver `CheckoutManager::initiate` con `firstOrCreate` + `lockForUpdate`; en `ChargeSubscriptionAction` usar `SELECT ... FOR UPDATE` antes de `exists()` y manejar `QueryException` 23505 como “already processed”. Test de carrera con `concurrently` + `DatabaseTransactions`.

**Fase 1 — Riesgos (Sprint siguiente, dependencias Fase 0)** 3. **B005**: Refactor `BillingServiceProvider` → `BillingManager::forTenant($tenant)` en `CheckoutManager`/`PaymentVerifier`/`ChargeSubscriptionAction`; eliminar `tenant()` helper del binding. 4. **B004**: `ProcessRecurringChargesCommand` con `Cache::lock('billing:recurring', 600)->block(5)` + `ShouldBeUnique` en `ChargeSubscriptionJob` (`uniqueId = tenantId:subscriptionId:period`). 5. **B003**: Resolver tenant en webhook vía `PaymentReference`/`Payment` antes de encolar; validar `tenantId` payload == `payment.tenant_id` y descartar mismatch; rotar a secrets por gateway. 6. **B006 + B007**: Añadir `ScopedToTenant` a `Invoice` + RLS + `CrossTenantLeakTest` Billing (incluir caso “conexión reutilizada” con `SET LOCAL` previo). 7. **B011**: Re-derivar `amount` en servidor desde `Plan`/`Invoice` y rechazar `PaymentData.amount` del cliente si difiere; añadir `assert amount == resolver` en `CheckoutManager`.

**Fase 2 — Estabilización (Backlog, sin dependencias críticas)** 8. **B008 + B012**: Migrar `subscriptions` PK a `uuid` o remover `HasUuids`; añadir FKs `payments.tenant_id → tenants.id`, `invoices.tenant_id → tenants.id` con `ON DELETE RESTRICT`. 9. **B010**: Migrar `payments.amount` a `MoneyCast`/cents, validar `PaymentResultData` con schema versionada. 10. **B009 + B013 + B015**: Policies para invoices PDFs, bloquear `deleting` en `PaymentWebhook`, métricas dunning + alertas.

## 9. Quick Wins (Bajo esfuerzo, alto impacto)

- **QW-1 — Throttle + firma en callback** (`PaguelofacilCallbackController.php:24`): Añadir `->middleware('throttle:30,1')` y `if (! $gateway->verifyWebhook(...)) abort(401)` en `handleReturn` (reusa `ClaveGateway::verifyWebhook` existente). **Esfuerzo: 30 min**, evita P0 inmediato sin reescribir flujo.
- **QW-2 — Índice único periodal** (`database/migrations/2026_06_07_010000:33`): `ALTER TABLE payments ADD CONSTRAINT uq_tenant_display UNIQUE (tenant_id, display_id)` + `try/catch QueryException` en `CheckoutManager`. **Esfuerzo: 1 h**, elimina doble cobro accidental.
- **QW-3 — Lock en scheduler** (`ProcessRecurringChargesCommand.php:21`): `Cache::lock('billing:process-recurring', 300)->get() ?: exit`. **Esfuerzo: 15 min**, evita duplicación multi-replica.
- **QW-4 — `Invoice::with tenant` ya está, pero añadir `::with('tenant')` en `BillingApiController::listInvoices` + `->paginate(20)` y añadir `index(tenant_id, status, created_at)` faltante. **Esfuerzo: 30 min**, mejora listados staff.
- **QW-5 — Test de callback forgery** (`tests/Feature/Billing/CallbackForgeryTest.php`): Un Pest test que `GET /central/billing/paguelofacil/callback?PARM_1=...&Estado=Aprobada` y `assertDatabaseMissing subscriptions`. **Esfuerzo: 45 min**, regression guard para P0.

## 10. Qué NO hacer (Refactors tentadores pero innecesarios/sobreingeniería)

- **NO introducir Repository Pattern** sobre Eloquent para `Payment`/`Invoice`. CRUD via `Payment::create` + Actions es suficiente; añadir Repos solo añade indirección sin beneficio (regla “cero sobreingeniería”).
- **NO implementar CQRS/Event Sourcing** para billing. `PaymentWebhookReceived` + `PaymentApproved` events ya desacoplan; un event store completo es sobrecosto.
- **NO extraer “Billing Microservice”** ni mover `tenants` a DB separada. Estrategia oficial es Single DB + RLS; microservicio rompería `SET LOCAL` y transacciones.
- **NO reescribir `ClaveGateway`/`DlocalGateway` a Hexagonal con 6 capas**. El contrato `PaymentGateway` actual es adecuado; solo resolver `forTenant` correctamente (B005), no abstraer factories genéricas.
- **NO añadir DTOs para cada `where` de listados** (`GlobalInvoiceList`, `SubscriptionList` con `latest()->paginate(10)` están bien; no necesitan `Query Objects` hasta que haya filtros complejos/reporting.
- **NO unificar `CentralUser` y `Tenant User` en `type` field** para “simplificar” invoice access — viola `PROJECT_DECISIONS.md §15` y rompe aislamiento Central/Tenant.

## 11. Cobertura de pruebas (Flujos críticos expuestos)

**Cubierto (confirmado):**

- `PlanManager` matrix (`BillingTest.php:12` free/pro), `Tenant Billable` trait.
- `CheckoutManager` DIRECT vs REDIRECT (tests `DlocalDirectCheckoutTest`, `DlocalCallbackTest` — mocks `DlocalHttpClient`, `PaymentReference::firstOrCreate`).
- `PaymentVerifier` duplicate webhook `gateway_reference` (`payment_webhooks unique tenant+gateway_reference`), `lockForUpdate` y `Cache::lock`.
- `ChargeSubscriptionAction` engine recurrence con `dlocal` vs `clave` skip, `failed_attempts` + `TenantSuspendedByDunning`.
- `StripeWebhookController` idempotency `gateway_event_id` + `Cashier` parent handlers.

**No cubierto (huecos críticos):**

- **Callback forgery** (`PaguelofacilCallbackController`) — 0 tests. Riesgo P0 no detectado.
- **Carrera `displayId` duplicado** — no test concurrente con `parallel` + `unique constraint`.
- **Resolver `PaymentAmountResolver` tenant ownership** — no test que `display_id` de otro tenant sea rechazado.
- **Tenant billing_gateway fallback** — no test que `billing:process-recurring` con `tenant(dlocal)` use `DlocalGateway` en cola sin `tenant()` helper.
- **CrossTenantLeak Billing** — 0 tests (vs Metering sí tiene `Metering/CrossTenantLeakTest.php:13`).
- **RLS `invoices`/`subscriptions`** — `RLSIsolationTest` solo prueba `tenant_api_keys`, no `invoices`.
- **N+1 `SubscriptionList::with('subscriptions')`** — sin `assertDatabaseQueryCount`.

## 12. Riesgos pendientes (Observabilidad)

- **Gateway observability**: `ClaveGateway::post` loggea `error` con `body` pero no métrica `billing.gateway.latency` ni `billing.webhook.verification_failed` count. Si `dLocal` rate-limitea, reintentos `retry(1,100)` insuficientes sin backoff exponencial ni circuit breaker.
- **Dunning silo**: `ChargeSubscriptionAction::scheduleRetry` mueve `next_payment_at +12h` sin `failed_attempts` check de `MAX_FAILED_ATTEMPTS` idempotente si `handleDecline` ya incrementó; logs `Log::warning subscription_renewal_failed` sin correlación `subscription_id` en estructura uniforme (difícil grep en Pail).
- **Cache lock TTL**: `PaymentVerifier` lock 10s puede expirar antes de `DB::transaction` + `lockForUpdate` en webhooks lentos (>10s) → segunda entrega procesa duplicado; considerar `lock->restoreOwner` o aumentar a 30s.
- **Tenancy context leak**: `RehydrateTenantContext` (Platform `Tenancy/Infrastructure/Jobs/RehydrateTenantContext.php:15`) bien cierra con `tenancy()->end()` en `finally`, pero `BillingApiController` y `GenerateInvoicePdf` no usan `ApplyTenantRateLimits` ni `EnsureTenantIsActive` — un tenant suspendido puede seguir iniciando checkout.

## 13. Conclusión (Próxima acción accionable)

**Estado 🔴 requiere intervención.** No es deuda menor: **B001 permite robar planes** y **B002 permite cobrar doble** con evidencia en código. La arquitectura es rescatable sin reescritura.

**Próxima acción (48 h):**

1.  Asignar owner a `B001` (Security) y `B002` (DB). Implementar QW-1 + QW-2 en rama `hotfix/billing-p0` con tests de regresión (`CallbackForgeryTest` + `DuplicateDisplayIdTest`), pasar `composer lint && php artisan test --filter=Billing` en CI con Postgres no-superuser.
2.  Re-ejecutar esta auditoría (IDs B001-B002) y, si pasan, promover a 🟡 y planificar Fase 1 (B005/B004) en sprint.

> **Nota de mantenimiento**: Este informe preserva IDs `B001`–`B015` históricos. Próxima auditoría (ej. Tenant/Access o Platform/Tenancy) debe crear `docs/modules/access.md` o `tenancy.md` con IDs `A001`/`T001` sin reutilizar serie `B`.
