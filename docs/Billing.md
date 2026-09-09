# Billing Core adaptado a openSaaS (v2 — evolutivo, no greenfield)

> Estado: propuesta original `docs/Billing.md` (domain-first, capabilities, eventos normalizados)
> adaptada al código real verificado el 2026-09-09. No se reescribe Billing; se evoluciona.
> Stack: Laravel 13.15, PHP 8.5, Cashier 16.5, `stancl/tenancy 3.10`, pgSQL + RLS, Redis.

## 0. Estado real verificado (no asumir)

Tablas existentes (schema pgSQL real):

- `plans` (Catalog, source-of-truth): `slug, name, price_monthly/yearly MoneyCast, amount float legacy, currency, interval, interval_count, provider_plan_id, is_active, features jsonb` + pivot `plan_features` + `SoftDeletes`.
- `tenants`: `plan_id string slug, billing_gateway (clave|dlocal|stripe), stripe_id, pm_*, status`.
- `payments`: `tenant_id, display_id, slug UNIQUE, amount/tax/discount numeric, status, gateway, gateway_reference` + `ScopedToTenant` + RLS.
- `payment_attempts`: `tenant_id, payment_id FK, slug, status, payload jsonb`.
- `payment_webhooks`: `tenant_id, gateway_reference, display_id, status, amount` (idempotencia por `gateway_reference`).
- `payment_gateway_events`: `gateway_event_id, gateway, event_type, payload jsonb, processed_at` (idempotencia Stripe).
- `payment_references` (Platform/Dlocal): `external_reference, order_id, context, tenant_id, owner_type/id` — es el customer-mapping real.
- `invoices`: `tenant_id, subscription_id, provider_invoice_id, amount MoneyCast, status` + `ScopedToTenant`.
- `subscriptions`: `id uuid, tenant_id, plan_id uuid FK Plan, provider_subscription_id, status, gateway, current_period_end, next_payment_at, pm_card_id, failed_attempts` (extiende Cashier, ya migrada a uuid).
- `subscription_items`, `ledger_entries`, `usage_events/rollups` (metering).

Código existente:

- `Platform/Contracts/BillingProvider` (5 métodos: `createCheckoutSession, cancelSubscription, syncSubscription, getSubscriptionData, getInvoices`) + `BillingManager extends Manager` con `forTenant(TenantContract)` → drivers `clave|dlocal|stripe`.
- `Billing/Infrastructure/Gateways/PaymentGateway` (monolito de 8 métodos: `loadMerchant, buildCheckoutUrl, verifyWebhook, parseWebhookPayload, listTransactions, chargeSubscription, supportsDirectPayment, processDirectPayment`).
- `CheckoutManager::initiate` con `lockForUpdate + recovery 23505` (B002 mitigado en checkout).
- `PaymentVerifier::handleWebhook` con `Cache::lock 30s + txn + lockForUpdate + no-regresión terminal + INSUFFICIENT_AMOUNT`.
- `WebhookController::handle`: verifica firma sync, resuelve tenant, valida `display_id → tenant_id` antes de encolar (B003 mitigado).
- `PaguelofacilCallbackController::handleReturn`: UX-only, no muta (B001 cerrado).
- `ChargeSubscriptionAction`: engine-managed solo `dlocal`, `displayId = sub_{id}_Y-m`, `lockForUpdate` + `catch 23505`, dunning 3 intentos → `suspended` + `TenantSuspendedByDunning`.
- `FulfillSubscription` (listener `PaymentApproved`): crea `Subscription` + activa `tenant.plan_id/status`.
- `StripeBillingProvider`: envuelve Cashier (`newSubscription()->checkout()`), pero exige `instanceof Tenant` concreto y resuelve dominio inline.
- `PlanManager::all()` sin caché deliberado (rompía con `cache.serializable_classes=false`).

Eventos reales (no renombrar): `CheckoutSessionCreated, PaymentApproved, PaymentDeclined, PaymentWebhookReceived` + `TenantSuspendedByDunning`, `TenantReactivatedAfterPayment`.

## 1. Decisión: qué se adopta, qué se rechaza

Adoptar de la propuesta:

1. Segregar `PaymentGateway` monolito en capacidades pequeñas.
2. `BillingEvent` normalizado como mapper, no como reemplazo de eventos.
3. `BillingCapability::supports()` para gap Stripe vs dLocal/Clave.
4. Cashier encapsulado en `Infrastructure/Gateways/Stripe/`, prohibido fuera.
5. `provider_metadata` con disciplina (columnas propias primero).
6. API dominio `for($tenant)->checkout/subscribe/cancel` sobre `TenantContract`.

Rechazar con justificación:

- `billing_accounts` nueva → usar `tenants.billing_gateway + payment_references`. Nueva tabla = 3ª fuente sin caso de uso.
- `prices` separada → `Catalog/Plan` ya es source-of-truth. Multi-currency/multi-provider se resuelve extendiendo `Plan.features/provider_plan_id`, no con fork. Crear `prices` hoy es YAGNI (un solo precio mensual/anual por plan en uso).
- `billing_webhook_events` nueva → ya existen `payment_webhooks + payment_gateway_events + payment_references`. Unificar, no duplicar.
- PayPal / marketplace / split / orchestration / tax-engine / contabilidad doble → vetado PO/SCOPE (misma conclusión que §23 de la propuesta).
- Firmas con `Tenant $tenant` concreto → violarían aislamiento entre módulos. Siempre `TenantContract` + DTOs.

## 2. Arquitectura objetivo (evolutiva)

```
Application (Actions delgadas, una responsabilidad)
  CreateCheckoutSession / InitiateCheckout (ya existen)
  SubscribeTenant / ChangePlan / CancelSubscription (nuevas, delegan en Billing facade)
  ChargeSubscriptionAction / ReconcileSubscription / SyncInvoices (ya existen)
          │
          ▼
Billing facade (dominio, TenantContract, sin Stripe/dLocal)
  Billing::for(TenantContract)->checkout(PlanRef)/subscribe/changePlan/cancel/resume/active()
          │
  Capability contracts (Platform/Contracts)
  CheckoutProvider / SubscriptionProvider / PaymentProvider / WebhookProvider + BillingCapability
          │
  Infrastructure/Gateways/{Clave,Dlocal,Stripe}/
    ClaveGateway / DlocalGateway (ex PaymentGateway, partidos por capacidad)
    StripeBillingProvider → delega en Cashier interno, mapea estados
```

Reglas:

- `Platform` nunca importa `Central/*`. Contratos en `Platform/Contracts`, implementaciones en `Central/Billing`.
- Ninguna Action toca Models de otro módulo: `Plan` se resuelve vía `PlanManager::find()` (Catalog expuesto como servicio, no importando lógica), `Tenant` solo vía `TenantContract`.
- Cashier solo en `Infrastructure/Gateways/Stripe/*`. `grep -R "Cashier" Application/ Domain/` debe dar vacío (test de arquitectura).
- Toda escritura tenant-aware con `tenant_id` + RLS + `ScopedToTenant`. Jobs con `TenantAware + RehydrateTenantContext` (`SET LOCAL` en txn, nunca `SET` sesión).

## 3. Contratos objetivo (reemplazan `PaymentGateway` monolito)

Ubicación: `app/Modules/Platform/Contracts/Billing/` (nuevos; el monolito queda como deprecated hasta migrar callers).

```php
// app/Modules/Platform/Contracts/Billing/CheckoutProvider.php
interface CheckoutProvider
{
    public function createCheckout(TenantContract $tenant, PlanRef $plan): CheckoutSessionData;
}

// app/Modules/Platform/Contracts/Billing/SubscriptionProvider.php
interface SubscriptionProvider
{
    public function createSubscription(TenantContract $tenant, PlanRef $plan): ProviderSubscriptionRef;
    public function changePlan(TenantContract $tenant, string $providerSubscriptionId, PlanRef $plan): ProviderSubscriptionRef;
    public function cancel(TenantContract $tenant, string $providerSubscriptionId, bool $immediately = false): void;
}

// app/Modules/Platform/Contracts/Billing/PaymentProvider.php
interface PaymentProvider
{
    public function chargeRecurring(TenantContract $tenant, string $providerSubscriptionId, int $amountCents): ProviderPaymentRef;
    public function refund(TenantContract $tenant, string $providerPaymentId, ?int $amountCents = null): void;
}

// app/Modules/Platform/Contracts/Billing/WebhookProvider.php
interface WebhookProvider
{
    public function verify(string $rawPayload, string $signature, string $secret): bool;
    public function normalize(array $payload): BillingEventData;
}

// app/Modules/Platform/Contracts/Billing/BillingCapability.php
enum BillingCapability: string
{
    case Checkout = 'checkout';
    case DirectPayment = 'direct_payment';
    case Subscriptions = 'subscriptions';
    case RecurringCharge = 'recurring_charge';
    case Refunds = 'refunds';
    case CustomerPortal = 'customer_portal';
}
```

DTOs en `Platform` (spatie/laravel-data, sin Eloquent): `PlanRef{planId, slug}`, `CheckoutSessionData{id, url, provider}`, `BillingEventData{type: BillingEventType, providerReference, displayId, amountCents}`, `BillingEventType` (el enum de la propuesta §8, reutilizado como valores del mapper).

`BillingManager::forTenant()` se mantiene como único resolver. Se elimina `gatewayForTenant()` duplicado en `CheckoutManager` y `PaymentVerifier` (usan `BillingManager::forTenant` + capability check; cierra B005).

Matriz real (verificada dLocal enrollments):

```
Stripe: Checkout ✓ Direct ✗ Subscriptions ✓ Recurring ✓(gateway-managed) Refunds ✓ Portal ✓
dLocal: Checkout ✓(redirect) Direct ✓(Smart Fields token) Subscriptions ✓(enrollments ON_DEMAND/MERCHANT_SUBSCRIPTION) Recurring ✓(engine-managed chargeSubscription) Refunds ✓ Portal ✗
Clave/PagueloFácil: Checkout ✓(redirect) Direct ✗ Subscriptions ✗ Recurring ✗(gateway-managed, reconcile) Refunds ? Portal ✗
```

La app nunca hace `if ($provider === 'stripe')`; hace `$provider->supports(BillingCapability::Subscriptions)`.

## 4. Webhook pipeline (sin reescribir, solo endurecer)

Flujo vigente que se conserva:

```
Gateway → WebhookController (firma sync → resolveTenant → valida display_id↔tenant → dispatch 200 inmediato)
  → ProcessPaymentWebhookJob (TenantAware, RehydrateTenantContext, SET LOCAL)
  → PaymentVerifier (lock Cache 30s → txn → recordWebhook idempotente → reconcilePayment lockForUpdate)
  → PaymentApproved/Declined → FulfillSubscription / HandlePaymentFailure (dunning)
```

Ajustes pendientes (no nuevos componentes):

1. `resolveTenantId`: priorizar `Payment::where display_id → tenant_id` y `payment_references.external_reference → tenant_id` sobre `PARM_1/metadata` crudo. El payload solo propone, la DB dispone.
2. Callback navegador sigue UX-only (B001 cerrado, no reabrir). Toda mutación `Subscription/tenant.plan_id` solo en `FulfillSubscription` vía webhook verificado.
3. `BillingEventData::normalize` como capa fina sobre `parseWebhookPayload` actual por gateway; el dominio solo consume `BillingEventType` (`payment.succeeded/failed, subscription.created/updated/canceled, checkout.completed`).

## 5. Idempotencia y dinero (lo crítico, antes que elegancia)

Ya implementado, no duplicar:

- `payments.slug UNIQUE` + `lockForUpdate` en `CheckoutManager` + `catch 23505 → reuse`.
- `payment_webhooks UNIQUE(tenant_id, gateway_reference)` + `Cache::lock webhook_processing_*`.
- `payment_gateway_events UNIQUE(gateway, gateway_event_id)` para Stripe.
- `ChargeSubscriptionAction`: guard `displayId = sub_{id}_Y-m` con `lockForUpdate` + `catch 23505`; declines con `displayId` sufixado para no colisionar.
- Scheduler `billing:process-recurring` requiere `Cache::lock` / `withoutOverlapping` + job `ShouldBeUnique (tenantId:subscriptionId:period)` (B004 pendiente).

Pendiente DB (backlog, no bloquea): `UNIQUE(tenant_id, display_id)` parcial para `status=approved` si se quiere garantía a nivel constraint además de app; FKs `payments/invoices.tenant_id → tenants.id ON DELETE RESTRICT` (GDPR: anonimizar, no borrar en cascada); `ScopedToTenant` ya en `Payment/Invoice`, añadir a `Subscription` si se consulta desde tenant.

## 6. Multi-tenancy, jobs, caché (no negociable)

- `tenant_id` en toda entidad interna + RLS `FORCE` donde ya existe (`payments, payment_attempts, payment_webhooks`). `invoices` con `ScopedToTenant`; añadir RLS cuando se confirme política probada con `CrossTenantLeakTest`.
- Jobs (`ProcessPaymentWebhookJob, ChargeSubscriptionJob`): `TenantAware::tenantId()` + middleware `RehydrateTenantContext` (`SET LOCAL` en txn). Nunca heredar contexto de conexión reutilizada (Octane/PgBouncer).
- Caché: `tenant:{id}:*` para datos tenant, `central:*` para catálogo. `PlanManager::all()` se mantiene sin caché de modelos hidratados (causa `__PHP_Incomplete_Class` con `cache.serializable_classes=false`); si se cachea, guardar arrays/IDs, no Eloquent.
- Resolución webhook → tenant vía `BillingAccount` de la propuesta: rechazada; equivalencia real = `payment_references.external_reference → tenant_id`.

## 7. Plan / Price sin fork

`Catalog/Plan` sigue siendo source-of-truth. Extensión permitida sin nueva tabla:

- `provider_plan_id` (string actual) → convención `features.gateway_ids = {stripe: price_*, dlocal: PLAN-*, clave: service_id}` o columnas `provider_refs jsonb` si un plan necesita N refs. Columnas propias para lo que el dominio filtra (`price_monthly/yearly, currency, interval`); `features/provider_refs` para opacos del gateway.
- `PlanManager::getStripeId()` se generaliza a `getProviderRef(plan, gateway)` leyendo ese mapa. Sin tabla `prices` hasta que un plan tenga 2+ precios reales por gateway y moneda (hoy no).

## 8. Plan de migración por fases

Fase 0 — cerrar P0s abiertos (antes de tocar kernel):

- B004: lock distribuido en `ProcessRecurringChargesCommand` + `ShouldBeUnique` en `ChargeSubscriptionJob`.
- B003 resto: `resolveTenantId` vía DB primero (ver §4.1).
- Tests: `CallbackForgeryTest` (callback aprobado falso no crea nada), `DuplicateDisplayIdTest` (doble initiate concurrente → un Payment), `Billing/CrossTenantLeakTest` (A no lee payments/invoices/subscriptions de B + conexión reutilizada).

Fase 1 — partir monolito (sin cambiar comportamiento):

- Extraer interfaces §3 en `Platform/Contracts/Billing/`.
- `ClaveGateway, DlocalGateway` implementan `CheckoutProvider+PaymentProvider+WebhookProvider` según matriz; `StripeBillingProvider` implementa `CheckoutProvider+SubscriptionProvider`.
- `CheckoutManager/PaymentVerifier/ChargeSubscriptionAction` resuelven vía `BillingManager::forTenant()`; borrar `gatewayForTenant()` locales. `StripeBillingProvider` deja de exigir `instanceof Tenant` (usa `TenantContract::getId()/...`).

Fase 2 — eventos y facade:

- `normalize(): BillingEventData` por gateway; listeners consumen `BillingEventType`.
- Facade `Billing::for(TenantContract)` (`checkout/subscribe/changePlan/cancel/active`) delegando en capabilities. Livewire (`CheckoutComponent, HostedCheckout, ManageBilling`) llama facade, nunca gateways.

Fase 3 — solo con 2+ casos reales:

- `prices` / `billing_accounts` / portal / cupones. Hasta entonces, YAGNI.

## 9. Tests DoD por fase

- Fase 0: forgery, doble-cobro concurrente, leak cross-tenant, `SET LOCAL` reutilizado.
- Fase 1: contrato — `Cashier` solo bajo `Infrastructure/Gateways/Stripe`, `forTenant(dlocal)` en cola sin `tenant()` helper usa `DlocalGateway`.
- Fase 2: `supports()` por gateway, `normalize` por evento, `FulfillSubscription` solo vía webhook verificado.

## 10. Lo que NO hacer

- No crear `billing_accounts / prices / billing_webhook_events`.
- No reintroducir mutación en callback navegador.
- No cachear modelos Eloquent hidratados.
- No usar `Tenant` concreto en firmas nuevas (`TenantContract`).
- No `if ($provider === ...)` en dominio; usar `supports()`.
- No microservicio / event-sourcing / CQRS / ledger doble para Billing MVP.
- No convertir `provider_metadata` en basurero: columnas propias para `amount, status, gateway_reference, display_id`.

## 11. Referencia rápida (paths reales)

- Contratos: `app/Modules/Platform/Contracts/BillingProvider.php`, `.../Billing/*.php` (nuevo).
- Facade: `app/Modules/Central/Billing/Infrastructure/Gateways/BillingManager.php`.
- Gateways: `.../Gateways/ClaveGateway.php`, `DlocalGateway.php`, `StripeBillingProvider.php`, `CheckoutManager.php`, `PaymentVerifier.php`.
- Acciones: `.../Application/Actions/{InitiateCheckout,CreateCheckoutSession,ChargeSubscriptionAction,CancelSubscription,HandleWebhook,SyncInvoices,GenerateInvoicePdf}.php`.
- Webhooks: `.../Interface/Http/Controllers/{WebhookController,StripeWebhookController,PaguelofacilCallbackController}.php`.
- Listeners: `.../Application/Listeners/{FulfillSubscription,HandlePaymentFailure,ProcessIncomingPaymentWebhook}.php`.
- Tablas: `payments, payment_attempts, payment_webhooks, payment_gateway_events, payment_references, invoices, subscriptions, plans`.
