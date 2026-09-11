# Billing Core adaptado a openSaaS (v3 — rebuild desde cero)

> Estado: propuesta `docs/BILLING.md` v1 (domain-first, capabilities, scheduler) adaptada al
> código real de este repo. La rama `feature/rebuild-billing` eliminó Billing/Catalog/Metering;
> este documento es la especificación del rebuild. Lo que contradice al proyecto se rechaza
> explícitamente en cada sección.
> Stack verificado: Laravel 13.7, PHP 8.5, Livewire 4.1, Flux 2.13, `stancl/tenancy 3.10`,
> `spatie/laravel-data 4.23`, Pest sobre SQLite `:memory:`, PG + RLS en local/prod, Redis.

## 0. TL;DR

Billing Core agnóstico al proveedor, **domain-first, no gateway-first**. Clave (PagueloFacil),
dLocal y —solo cuando haya demanda real— Stripe son adapters intercambiables detrás de
contratos segregados por capacidad.

Orden de implementación invertido respecto a v1: **Clave primero** (redirect; es lo que ya
operó en producción), **dLocal segundo** (Smart Fields directo), **Stripe/Cashier diferido**
(cero SDKs instalados hoy; reintroducir Cashier como dependencia transversal sería repetir
el error que motivó el rebuild).

```text
                         ┌─────────────────────┐
                         │     Application      │
                         │                      │
                         │ CreateCheckout       │
                         │ SubscribeTenant      │
                         │ ChangePlan           │
                         │ CancelSubscription   │
                         │ RefundPayment        │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │    Billing Core      │
                         │                      │
                         │ Subscription         │
                         │ Invoice              │
                         │ Payment              │
                         │ Plan (Catalog)       │
                         │ BillingScheduler     │  ← reloj propio + dunning
                         └──────────┬───────────┘
                                    │
                             BillingManager
                                    │
              ┌─────────────────────┼─────────────────────┐
              ▼                     ▼                     ▼
        ┌───────────┐        ┌─────────────┐       ┌───────────┐
        │   Clave   │        │   dLocal    │       │  Stripe   │
        │  Adapter  │        │   Adapter   │       │ diferido  │
        └─────┬─────┘        └──────┬──────┘       └───────────┘
              ▼                     ▼
     PagueloFacil API        dLocal API
```

Diferencia clave heredada de v1 y vigente: **no se asume que todos los proveedores poseen
un ciclo de vida de suscripción**. La recurrencia es propiedad de la combinación
proveedor + método de pago, nunca del proveedor solo.

---

## 1. El objetivo

No es un clon de Laravel Cashier. Cashier es Stripe-first y transversal; este sistema es
domain-first y vive dentro del monolito modular.

El dominio conoce:

```text
Subscription
Plan
Payment
Invoice
Refund
Checkout
PaymentMethod
```

pero **no conoce Clave ni dLocal**.

```php
$billing->for($tenant)->subscribe($plan);
```

Nunca:

```php
ClaveGateway::charge(...);
DlocalGateway::createPayment(...);
```

Reglas de `ARCHITECTURE_RULES.md` aplicables sin excepción: lógica en Actions
(`final readonly class` + `execute(DTO)`), DTOs con `spatie/laravel-data` (nunca arrays),
ninguna Action toca Models de otro módulo, `Tenant` solo vía `TenantContract`.

---

## 2. La asimetría que hay que modelar desde el día uno

| Método de pago                | Recurrencia nativa del proveedor               | Quién dispara el cobro             |
| ----------------------------- | ---------------------------------------------- | ---------------------------------- |
| Clave (redirect PagueloFacil) | Ninguna — checkout hosted por evento           | **Nosotros** (scheduler + dunning) |
| Tarjeta (dLocal, tokenizada)  | Parcial — MIT (Merchant Initiated Transaction) | **Nosotros**                       |
| Efectivo/voucher (dLocal)     | Ninguna — pago único                           | Nadie. No hay recurrencia posible  |
| Tarjeta (Stripe)              | Sí — Stripe posee el ciclo completo            | Stripe (diferido a fase 2)         |

Consecuencia: `supports(Subscriptions)` se resuelve con **proveedor + método de pago**.
La UI consulta capacidades **antes** de prometer auto-renovación; si el método no soporta
recurrencia se degrada a recordatorio de pago manual, nunca se finge una suscripción activa.

Rechazado de v1: ejemplos OXXO/Pix como casos centrales (realidad PA: Clave + tarjeta).
Se mantienen como filas genéricas de la matriz, no como flujos a implementar en v1.

---

## 3. Billing Manager — punto de entrada único

```php
// app/Modules/Platform/Contracts/Billing/BillingManager.php
interface BillingManager
{
    public function providerFor(TenantContract $tenant): BillingProvider;
}
```

Resolución por columna `tenants.billing_gateway` (`clave|dlocal|stripe`), reintroducida por
migración del rebuild con default `clave`. Firma con `TenantContract`, nunca con el modelo
concreto (el `instanceof Tenant` del diseño anterior violaba el aislamiento entre módulos
y no se repite).

**El proveedor no es dueño del estado.** El estado vive en las tablas del Billing Core.

---

## 4. Modelo de dominio propio

Rechazado de v1 con justificación (decisión heredada del diseño anterior, vigente):

- Sin tabla `billing_accounts` → el mapeo cliente vive en `tenants.billing_gateway` +
  `payment_references`. Tercera fuente sin caso de uso: vetado.
- Sin tabla `prices` → `Catalog/Plan` es source-of-truth (`price_monthly/yearly`,
  `currency`, `interval`, `features jsonb`). Multi-precio por gateway/moneda se resuelve
  extendiendo `Plan.features.gateway_ids = {clave: service_id, dlocal: PLAN-*, stripe: price_*}`,
  no con fork. Crear `prices` hoy es YAGNI (un precio mensual/anual por plan en uso).
- Sin tabla `billing_webhook_events` nueva → ya definidos `payment_webhooks`
  (idempotencia por `gateway_reference`) + `payment_gateway_events`
  (`UNIQUE(gateway, gateway_event_id)`).

Tablas del rebuild (frescas; la rama eliminó las anteriores):

```text
subscriptions
  id uuid PK, tenant_id uuid NOT NULL, plan_id uuid FK plans,
  provider_subscription_id nullable, status, gateway,
  current_period_start/end, next_payment_at nullable,
  failed_attempts int default 0, pm_card_id nullable,
  renewal_link_sent_at nullable, renewal_reminder_sent_at nullable,
  renewal_link_expires_at nullable,
  cancel_at_period_end bool, canceled_at nullable, timestamps
  -- RLS FORCE, índice (tenant_id), UNIQUE(provider_subscription_id) parcial NOT NULL
  -- failed_attempts: solo track MIT. Columnas renewal_*: solo track link (Fase 0).

payments
  id uuid PK, tenant_id NOT NULL, slug UNIQUE, display_id,
  amount_cents int NOT NULL, currency char(3),
  status, gateway, gateway_reference nullable, subscription_id nullable FK,
  provider_metadata jsonb default '{}', timestamps
  -- RLS FORCE, índice (tenant_id)

payment_attempts
  id uuid PK, tenant_id NOT NULL, payment_id FK, slug, status, payload jsonb, timestamps
  -- RLS FORCE

payment_webhooks
  id uuid PK, tenant_id NOT NULL, gateway, gateway_reference UNIQUE,
  display_id, status, amount_cents, payload jsonb, processed_at nullable, timestamps
  -- RLS FORCE; el gateway_reference es la clave de idempotencia

payment_gateway_events
  id uuid PK, gateway, gateway_event_id, event_type, payload jsonb,
  processed_at nullable, failed_at nullable, attempts int default 0, timestamps
  -- UNIQUE(gateway, gateway_event_id); tabla central (sin tenant_id: el tenant se
  -- resuelve vía provider_customer_id → payment_references → tenant)

payment_references
  id uuid PK, tenant_id NOT NULL, external_reference UNIQUE, order_id,
  context, owner_type/id nullable, timestamps
  -- RLS FORCE; es el customer-mapping real

invoices
  id uuid PK, tenant_id NOT NULL, subscription_id nullable FK,
  provider_invoice_id nullable, amount_cents int, currency char(3),
  status, issued_at, paid_at nullable, timestamps
  -- RLS FORCE
```

Montos en **centavos enteros** (`amount_cents int`), no `numeric` + casts: portable entre
SQLite (tests) y PG (prod), sin redondeo flotante. La convención `MoneyCast` anterior se
sustituye; el formateo vive en un único `PriceFormatter` en `Platform/Data`.

```php
// app/Modules/Central/Billing/Domain/Enums/SubscriptionStatus.php
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Incomplete = 'incomplete';
}
```

Sin estado `Paused` (v1 lo proponía; ningún proveedor de fase 1 lo soporta: YAGNI).
El adapter traduce estados del proveedor **solo cuando el proveedor tiene ese concepto**;
efectivo/voucher no genera transiciones por evento (ver §10).

---

## 5. Contratos segregados por capacidad

Ubicación: `app/Modules/Platform/Contracts/Billing/`. DTOs con `spatie/laravel-data`.
`TenantContract` siempre; ningún contrato nombra un modelo de `Central`.

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

    /**
     * Cobra una suscripción MIT-based (dLocal tarjeta). Clave no lo implementa
     * (sin recurrencia: LogicException); Stripe lo ignora (reloj propio, fase 2).
     */
    public function chargeRecurring(TenantContract $tenant, string $providerSubscriptionId, int $amountCents): ProviderPaymentRef;
}

// app/Modules/Platform/Contracts/Billing/PaymentProvider.php
interface PaymentProvider
{
    public function chargeDirect(DirectPaymentData $payment): ProviderPaymentRef;

    public function refund(TenantContract $tenant, string $providerPaymentId, ?int $amountCents = null): void;
}

// app/Modules/Platform/Contracts/Billing/WebhookProvider.php
interface WebhookProvider
{
    public function verify(string $rawPayload, string $signature): bool;

    public function normalize(array $payload): BillingEventData;
}
```

`PlanRef` es un DTO (`slug`, `amountCents`, `currency`, `gatewayIds`), no el modelo `Plan`:
`Plan` se resuelve vía servicio de Catalog (`PlanManager::find()`), nunca importando el
modelo en Billing.

---

## 6. Eventos — dos capas, no una

Integración (cross-module, viven en `Platform/Events`, payload con IDs/DTOs, nunca Models):

```text
PaymentWebhookReceived(provider, providerEventId, payload)
```

Dominio (internos de Billing, consumidos por sus listeners):

```text
CheckoutSessionCreated
PaymentApproved(displayId, tenantId, amountCents, gateway)
PaymentDeclined(displayId, tenantId, reason)
```

```php
// app/Modules/Platform/Contracts/Billing/BillingEventType.php
enum BillingEventType: string
{
    case CheckoutCompleted = 'checkout.completed';
    case PaymentSucceeded = 'payment.succeeded';
    case PaymentFailed = 'payment.failed';
    case SubscriptionCreated = 'subscription.created';
    case SubscriptionUpdated = 'subscription.updated';
    case SubscriptionCanceled = 'subscription.canceled';
    case InvoicePaid = 'invoice.paid';
    case InvoiceFailed = 'invoice.failed';
    case RefundCreated = 'refund.created';
}
```

Sin eventos `TenantSuspendedByDunning` / `TenantReactivatedAfterPayment` como eventos de
plataforma: la suspensión por dunning es una transición de estado del tenant ejecutada por
el scheduler (§9), con `activity('billing')` como rastro. Evento cross-module solo si un
segundo consumidor real aparece.

---

## 7. Webhook pipeline

```text
CLAVE / DLOCAL
  │ webhook o callback
  ▼
verify() sync (firma HMAC / secreto; 401 ante mismatch, sin tocar DB)
  │
  ▼
resolución de tenant — NUNCA user_id del frontend ni del payload:
  provider_customer_id → payment_references → tenant_id
  display_id → payments(display_id, tenant_id) con match estricto de tenant
  │
  ▼
idempotencia: payment_gateway_events UNIQUE(gateway, gateway_event_id);
  duplicado o fuera de orden → ack 200 sin procesar
  │
  ▼
dispatch Job TenantAware (TenantAware + RehydrateTenantContext) → normalize → BillingEvent
  │
  ▼
BillingEventHandler → PaymentApproved / PaymentDeclined → listeners de dominio
```

### Regla de resolución exacta (normativo, Fase 0)

`payment_references.external_reference` hace doble función (order ref + customer ref),
distinguida por la columna `context` (`'order'` | `'customer'`). No hay columna dedicada
para `provider_customer_id`: un registro por referencia externa, con `context` como
discriminador.

Dado un payload de webhook, el orden de resolución es:

1. Si el payload trae `display_id` u `order_id` → lookup en `payments` por
   `(display_id, gateway)`. El `tenant_id` sale de esa fila. Si no existe la fila, no
   se crea nada: se continúa al paso 2 (el checkout puede no existir aún si el pago
   se originó fuera de `initiate`).
2. Si el payload trae `provider_customer_id` → lookup en `payment_references` por
   `(external_reference, context = 'customer')` → `tenant_id`.
3. `PARM_1` (campo eco nuestro: enviamos el tenant id al construir el checkout) →
   se acepta **solo si existe una fila en `tenants` con ese id**. Nunca se confía
   a ciegas: un `PARM_1` inexistente se ignora y se continúa.
4. Fallback: `payment_references` por `external_reference` sin filtrar `context`
   (último registro). Solo como red de seguridad; se loguea `warning` con
   `billing.resolution_fallback` porque indica datos inconsistentes.
5. Sin match en ningún paso → se persiste el evento crudo en `payment_gateway_events`
   con `processed_at = null`, se responde ack 200 al gateway y se emite alerta para
   revisión manual. Nunca 500, nunca se escribe en tablas tenant-scoped sin tenant.

Reglas heredadas del diseño anterior (incidentes reales, no teoría):

- Callback de retorno Clave (`handleReturn`): **UX-only, no muta**. Solo el webhook/server-side
  cambia estado.
- Checkout con `lockForUpdate` + recuperación de `23505` (doble submit / retry de red no
  duplica `payments.slug`).
- `PaymentVerifier`: `Cache::lock 30s` + transacción + `lockForUpdate`, sin regresión desde
  estados terminales, rechazo por monto insuficiente (`INSUFFICIENT_AMOUNT`).
- El dominio nunca contiene `if ($provider === 'clave')`. Eso es diseño podrido.

---

## 8. Capacidades por proveedor **y por método de pago**

```php
// app/Modules/Platform/Contracts/Billing/BillingCapability.php
enum BillingCapability: string
{
    case Checkout = 'checkout';
    case DirectPayment = 'direct_payment';
    case Subscriptions = 'subscriptions';
    case Refunds = 'refunds';
    case Trials = 'trials';
}

enum PaymentMethodType: string
{
    case Card = 'card';
    case Cash = 'cash';
    case Wallet = 'wallet';
    case BankTransfer = 'bank_transfer';
}
```

Sin `CustomerPortal`, `Coupons`, `Invoices` como capabilities v1 (sin UI/consumidor real).

```text
ClaveProvider->supports(Checkout)                        → true (redirect)
ClaveProvider->supports(DirectPayment)                   → false
ClaveProvider->supports(Subscriptions)                   → false (recurrencia vía scheduler, §9)
DlocalProvider->supports(Checkout)                       → true (redirect)
DlocalProvider->supports(DirectPayment, forMethod: Card) → true (Smart Fields + charge server-side)
DlocalProvider->supports(Subscriptions, forMethod: Card) → true (vía MIT)
DlocalProvider->supports(Subscriptions, forMethod: Cash) → false
```

La UI (`SelectPlan`, `ManageBilling`) consulta `supports()` **antes** de ofrecer
auto-renovación o pago directo.

---

## 9. Billing Scheduler — reloj + dunning

Dos comandos, ya previstos en el scheduler (`routes/console.php`):

```text
billing:process-recurring  → diario 04:00
billing:reconcile          → diario 03:00
```

```php
// app/Modules/Central/Billing/Application/Services/BillingScheduler.php
final readonly class BillingScheduler
{
    /**
     * 1. Suscripciones MIT vencidas (dLocal tarjeta): chargeRecurring.
     *    Stripe-managed se ignoran (fase 2; Stripe cobra solo).
     *    Clave no tiene suscripciones nativas: sus ciclos se cobran como
     *    checkout recurrente iniciado por nosotros (ver dunning).
     * 2. Fallo de cobro → failed_attempts++ → PastDue.
     * 3. 3 intentos fallidos → tenant status 'suspended' + activity('billing').
     *    TenantQueueManager degrada sus workers a cola 'low' automáticamente.
     */
}
```

Dunning (3 intentos, backoff diario): `past_due` → reintento → `suspended`.
La suspensión por impago reutiliza el camino de `EnsureTenantIsActive` ya existente
(§19); no hay eventos ni máquinas de estado paralelas.

### Renovación Clave por link (normativo, Fase 0)

Clave no tiene recurrencia nativa: cada ciclo se cobra con un checkout nuevo generado por
nosotros. Mecanismo:

- `GenerateRenewalCheckoutAction` (Action, `execute(Subscription): CheckoutSessionData`):
  crea un `payments` row nuevo (nuevo `display_id`) con `subscription_id` apuntando a la
  suscripción vigente, y fija `subscriptions.renewal_link_sent_at = now()` y
  `subscriptions.renewal_link_expires_at = current_period_end + 3 días`.
- Se dispara desde `billing:process-recurring` cuando `current_period_end - now() <= 7 días`
  y no hay `renewal_link_sent_at` en el período vigente.
- `RenewalCheckoutLinkNotification` (mail enqueued al email del tenant): contiene la URL
  del checkout + fecha límite (`renewal_link_expires_at`). Recordatorio único si a
  `current_period_end - 2 días` no hay pago (`renewal_reminder_sent_at`).
- Pago aprobado → `PaymentApproved` → la suscripción renueva `current_period_start/end`;
  el link expira implícitamente (período ya pagado).

### Dunning separado por tipo de renovación (normativo, Fase 0)

`failed_attempts int` es **exclusivo del track MIT** (dLocal tarjeta, silencioso):

- Cada `chargeRecurring` fallido → `failed_attempts++`. Primer fallo → `past_due`.
- Reintento diario. `failed_attempts >= 3` → `tenant.status = suspended`.

El track link-based (Clave) **nunca incrementa `failed_attempts`** — el silencio del
cliente no es un fallo de cobro. Sus umbrales usan las columnas de link:

- Sin pago al llegar `renewal_link_expires_at` (`current_period_end + 3 días`) → `past_due`.
- Sin pago 4 días después (`current_period_end + 7 días`) → `suspended`.
- Columnas nuevas en `subscriptions` (§4): `renewal_link_sent_at nullable`,
  `renewal_reminder_sent_at nullable`, `renewal_link_expires_at nullable`.

Los números (3 intentos MIT vs. 3+4 días link) son deliberadamente distintos y se verifican
como valores literales diferentes en los tests de Fase 4.

---

## 10. Lifecycle — dual trigger

```text
             ┌───────────┐
             │  Pending  │  (registro en plan pago; expira vía provisioning:reconcile)
             │  Payment  │
             └─────┬─────┘
                   │ PaymentApproved (FulfillSubscription)
                   ▼
             ┌───────────┐
             │  Active   │
             └─────┬─────┘
                   │
          ┌────────┼────────┐
          │        │        │
          ▼        ▼        ▼
      PastDue    Cancel   Upgrade
       (evento   (fin de    (ChangePlan:
       o timeout) ciclo)    prorrateo manual
          │                 vía soporte v1)
          ▼
      Suspended (3er intento fallido)
          │
          ▼ (pago regularizado)
        Active
```

Dos fuentes para `PastDue`, no una:

```php
// Fuente 1: evento (dLocal card)
// BillingEventHandler: PaymentFailed → past_due + failed_attempts++

// Fuente 2: timeout (Clave, efectivo, red de seguridad general)
// billing:reconcile, diario:
if ($subscription->current_period_end->isPast()
    && ! $subscription->hasPaymentForCurrentPeriod()) {
    $subscription->transitionTo(SubscriptionStatus::PastDue);
}
```

`Upgrade` v1 es manual vía soporte (prorrateo automático vetado por PO/SCOPE hasta
demanda real, misma conclusión que el diseño anterior).

---

## 11. Idempotencia (no negociable)

- `payment_gateway_events UNIQUE(gateway, gateway_event_id)`: webhook duplicado o fuera
  de orden → ack sin reprocesar. Sin esto, un retry a las 3 a.m. cobra dos veces.
- `payment_webhooks.gateway_reference UNIQUE`: idempotencia a nivel de pago.
- Checkout: `lockForUpdate` + recuperación de `UniqueConstraintViolationException (23505)`
  mapeada a error de campo, nunca 500.
- `Cache::lock` 30s en verificación de webhook: doble entrega concurrente no duplica
  transiciones.

---

## 12. Checkout — dos flujos

```php
// app/Modules/Platform/Contracts/Billing/CheckoutSessionData.php
final class CheckoutSessionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $provider,
    ) {}
}
```

**Clave (redirect, fase 1):** `buildCheckoutUrl` server-side → `redirect()->away($url)`.
Callback de retorno UX-only; la verdad la pone el webhook (§7). Rutas
`payments.checkout.initiate` en whitelist de `EnsureTenantIsActive` para `pending_payment`.

**dLocal directo (Smart Fields, fase 1):** tokenización en browser (`dlocal.js`) →
`chargeDirect` server-side con el token. Nunca el PAN toca el backend. Clave publica
separada (`DLOCAL_JS_API_KEY`, fallback a `DLOCAL_LOGIN`) — la lección del diseño
anterior: la credencial de browser no es la credencial de API.

`CheckoutSession` **no implica** `Subscription`: un checkout puede terminar en pago único
sin recurrencia.

---

## 13. Feature system (Catalog reconstruido, no duplicado)

```text
plans (Catalog, source-of-truth)
  ├── features (jsonb: display_features, gateway_ids, quotas)
  └── is_active, SoftDeletes
```

- Sin tabla `prices` hasta que un plan tenga 2+ precios reales por gateway/moneda.
- `Plan.features.gateway_ids = {clave: service_id, dlocal: PLAN-*, stripe: price_*}`;
  `PlanManager::getProviderRef($plan, $gateway)` con fallback a `provider_plan_id`.
  Decisión Fase 0 (opcional, cerrada): **no se renombra** a columna `provider_refs`;
  `gateway_ids` ya tiene consumidor real (`getProviderRef`) y el rename no aporta nada.
- Autorización por plan: `$tenant->hasFeature('x')` vía `ResolveTenantFeatures`
  (caché `tenant:{id}:features`); middleware `feature:` solo donde una ruta lo exija.
- Límites: `QuotaManager` existente (contadores; límites desde `features.quotas`).
- `PlanManager::all()` sin caché de modelos hidratados (rompe con
  `cache.serializable_classes=false`): cachear arrays/IDs, no Eloquent.

---

## 14. API de aplicación (`Billing`)

```php
// app/Modules/Central/Billing/Application/Services/Billing.php
interface Billing
{
    public function for(TenantContract $tenant): BillingTenantScope;
}

// app/Modules/Central/Billing/Application/Services/BillingTenantScope.php
final readonly class BillingTenantScope
{
    public function checkout(PlanRef $plan): CheckoutSessionData;

    public function subscribe(PlanRef $plan): Subscription;

    public function changePlan(PlanRef $plan): Subscription;

    public function cancel(bool $immediately = false): void;

    public function resume(): void;

    public function active(): bool;

    public function supports(BillingCapability $capability, ?PaymentMethodType $forMethod = null): bool;
}
```

`subscribe`/`changePlan` solo donde el adapter tenga `SubscriptionProvider` real; Clave
responde `supports(Subscriptions) === false` y el scope lanza `RecurringBillingNotSupported`
con mensaje accionable (redirect a pago manual), nunca falla en silencio.

---

## 15. Provider-specific metadata

```text
provider_metadata JSONB  →  { "clave": {...}, "dlocal": { "payment_id": "..." } }
```

**No convertir `metadata` en basurero.** Todo dato que el dominio consulta (montos, estados,
referencias de suscripción, `pm_card_id`) tiene columna propia tipada; `provider_metadata`
es solo debug/forense opaco del gateway.

---

## 16. Stripe/Cashier — diferido, con reglas de reingreso

Stripe no entra en v1 (cero demanda + cero SDKs instalados). Si entra en fase 2:

1. Cashier vive **solo** en `Central/Billing/Infrastructure/Gateways/Stripe/`.
   `grep -R "Cashier" Application/ Domain/` debe dar vacío.
2. El trait `Billable` **jamás** va en `Provisioning\Models\Tenant` (acopla identidad a
   Stripe). Adapter con composición + mapper a `ProviderSubscriptionRef`.
3. `chargeRecurring` en el adapter Stripe lanza `LogicException` (reloj propio; si el
   scheduler lo selecciona, es bug de scheduling).
4. Columnas `stripe_id/pm_*` en `tenants` solo cuando Stripe aterriza, no antes.
5. `SyncInvoices`-style: prohibido `instanceof Cashier\...` fuera de `Infrastructure`.

---

## 17. Estructura de carpetas

Estructura completa del monolito modular (este repo la exige en `ARCHITECTURE_RULES.md`;
el debate "condicionado" de v1 es nulo aquí). Módulos simples con estructura plana hasta
que la complejidad real pida subcapas.

```text
app/Modules/Central/Billing/
├── Domain/
│   ├── Models/       (Subscription, Payment, PaymentAttempt, PaymentWebhook,
│   │                    PaymentGatewayEvent, PaymentReference, Invoice)
│   ├── Enums/        (SubscriptionStatus, PaymentStatus, BillingEventType,
│   │                    BillingCapability, PaymentMethodType)
│   └── Exceptions/   (RecurringBillingNotSupported, WebhookVerification, ... )
├── Application/
│   ├── Actions/      (CreateCheckoutSession, SubscribeTenant, ChangePlan,
│   │                    CancelSubscription, RefundPayment, ChargeSubscriptionAction,
│   │                    ReconcileSubscription, SyncInvoices, FulfillSubscription)
│   ├── DTO/          (spatie-data: CheckoutData, SubscriptionData, PaymentData, ...)
│   ├── Jobs/         (ProcessPaymentWebhookJob, ChargeSubscriptionJob — TenantAware)
│   ├── Listeners/    (FulfillSubscription, HandlePaymentFailure)
│   └── Services/     (Billing, BillingTenantScope, BillingScheduler, PaymentAmountResolver)
├── Infrastructure/
│   ├── Gateways/     (BillingManager, ClaveGateway, DlocalGateway, CheckoutManager,
│   │                    PaymentVerifier — Stripe/ solo en fase 2)
│   ├── Console/      (ProcessRecurringChargesCommand, ReconcileSubscriptionsCommand)
│   └── Notifications/(PaymentFailedNotification, TenantSuspendedNotification)
├── Interface/
│   ├── Http/         (WebhookController, PaguelofacilCallbackController,
│   │                    DlocalWebhookController — dLocal fase 1)
│   ├── Livewire/     (ManageBilling, SelectPlan, HostedCheckout, UpdatePaymentMethod,
│   │                    CheckoutComponent, SubscriptionList, TenantInvoiceList)
│   ├── Routes/       (web.php central, tenant.php, payments.php)
│   └── Views/        (namespaces billing:: / payments::)
├── Database/
│   └── Factories/    (PaymentFactory, SubscriptionFactory, ...)
└── Providers/
    └── BillingServiceProvider.php  (rutas, vistas, listeners, bindings; sin lógica)
```

Migraciones centralizadas en `database/migrations/` (convención del proyecto: no viajan
en el módulo). Providers solo registran; `bootstrap/providers.php` los lista.

---

## 18. Integración multi-tenant (RLS)

- Todas las entidades con `tenant_id`, `BelongsToTenant`/`ScopedToTenant`, `ENABLE +
FORCE ROW LEVEL SECURITY`, políticas `tenant_id = app.current_tenant_id()`.
- Propagación: `TenantContext` binding `scoped()` (nunca `singleton` — Octane),
  `SET LOCAL app.tenant_id` **dentro de transacción explícita**; `SET` de sesión prohibido.
- Jobs: contrato `TenantAware` + middleware `RehydrateTenantContext`; sin contexto no se
  ejecuta (excepción explícita, nunca silencio).
- `payment_gateway_events` es central (resolución previa a conocer el tenant); todo lo
  demás es tenant-scoped.

---

## 19. Integración al ciclo de vida del tenant (lo que v1 no tenía)

Estados que Billing reintroduce en `tenants.status`: `pending_payment`, `past_due`.
El resto del ciclo (`provisioning/active/suspended/archived/expired/quarantine`,
`maintenance_mode`, `read_only`) ya existe y no se toca.

```text
RegisterTenant (plan pago) → CreateTenant status 'pending_payment'
  → redirect checkout → PaymentApproved → FulfillSubscription:
     Subscription + tenant.status 'active' + plan_id
  → sin pago en 24h → provisioning:reconcile → 'expired' + OnboardingExpiredNotification
```

- `EnsureTenantIsActive`: allowlist `tenant.billing.*` + `payments.checkout.initiate`
  para `pending_payment`; `suspended` permite solo login (+ billing en dunning).
- `TenantQueueManager`: `suspended/past_due/quarantine` → cola `low`.
- Fraude: scoring previo a `CreateTenantAction`; cuarentena no pasa por checkout
  (`RegisterTenant` muestra mensaje neutro, pipeline notifica SecOps).

---

## 20. UI — Livewire 4 + Flux 2

Convención del proyecto: `Route::livewire('path', 'pages::ns.view')`, vistas en
`resources/views/pages/`, lógica en `app/Livewire/` o `Interface/Livewire/` del módulo,
componentes Flux antes que Tailwind crudo, validación server-side, `render()` sin N+1.

```text
Central (dominio central, guard central):
  GET /billing/subscriptions  → SubscriptionList
  GET /billing/plans          → PlanList / ManagePlan
  GET /billing/invoices       → GlobalInvoiceList

Tenant (subdominio, guard web):
  GET /billing                → ManageBilling
  GET /billing/plans          → SelectPlan (consulta supports() antes de ofrecer)
  GET /billing/checkout/hosted/{plan} → HostedCheckout (dLocal directo)
  GET /billing/update-payment → UpdatePaymentMethod
  GET /billing/success|/cancel → vistas estáticas
```

Componentes Livewire solo estado de UI: validan, llaman Actions, nunca crean modelos ni
queries complejas (eso vive en `Application/Queries/`).

---

## 21. Testing con Pest

Convenciones (`tests/Pest.php`): `TestCase + RefreshDatabase`, SQLite `:memory:`,
`CACHE_STORE=array`. Pipeline: `pint --dirty` → `phpstan analyse` → `pest`.

Mínimo por componente Billing (caso feliz + validación + permisos + aislamiento + error):

1. **Checkout**: redirect Clave con `display_id` único (doble submit → un `payments`);
   dLocal directo con token mock (nunca PAN real); callback Clave no muta.
2. **Webhooks**: firma inválida → 401 sin DB; duplicado → ack sin reprocesar;
   `display_id` de otro tenant → rechazo (resolución estricta).
3. **Scheduler**: MIT vencido cobra; fallo ×3 → `suspended`; timeout sin pago → `past_due`.
4. **Aislamiento**: `CrossTenantLeakTest` obligatorio (tenant A no lee/ejecuta nada de B,
   incluyendo jobs y `withoutGlobalScopes`). RLS con PG real solo en tests marcados
   `RLSEnforce` (no en la suite SQLite por defecto: CI debe seguir verde).
5. **Ciclo de vida**: `pending_payment` expira en 24h; `FulfillSubscription` activa;
   `EnsureTenantIsActive` bloquea/permiten según matriz de estados.
6. **Capacidades**: `supports()` por proveedor×método antes que cualquier promesa de UI.
7. **Arquitectura**: `Platform` no importa `Central`; sin `Cashier` fuera de
   `Infrastructure/Gateways/Stripe/` (cuando exista); sin `if ($provider === ...)` en dominio.

---

## 22. Fuera de alcance (v1 del rebuild)

No se implementa inicialmente (veto PO/SCOPE hasta 2+ casos reales):

- PayPal / marketplace / split payments / orchestration / smart routing
- Tax engine / contabilidad doble / sistema contable completo
- Coupons / trials / customer portal
- Prorrateo automático (upgrade manual vía soporte)
- Downgrades con validación de cuotas
- Factura fiscal electrónica (invoice PDF con dompdf: opcional, solo si lo exige PA)
- Event sourcing / CQRS / microservicio de billing
- Tabla `prices` (ver §4/§13)

Alcance v1:

```text
Clave redirect + callback ─┐
                           ├─→ Billing Core ─→ Scheduler + dunning ─→ RLS + tests
dLocal Smart Fields ───────┘         │
                                     ├── Webhooks + idempotencia
                                     └── Subscriptions (capability por método)
```

---

## 23. Decisión arquitectónica clave

```text
                YOUR DOMAIN
                     │
             ┌───────▼────────┐
             │  Billing Core  │
             │  + Scheduler   │
             └───────┬────────┘
                     │
             Capability APIs
          (provider × payment_method)
                     │
        ┌────────────┼────────────┐
        │            │            │
        ▼            ▼            ▼
      Clave        dLocal      Stripe
      Adapter      Adapter    (fase 2)
```

La abstracción central no es `PaymentGateway`. Es un Billing Core con:

1. Capacidades resueltas por **proveedor y método de pago**.
2. Eventos normalizados vía adapters, con idempotencia obligatoria.
3. Reloj propio (`BillingScheduler` + dunning) para proveedores sin ciclo de vida.
4. Integración nativa al ciclo de vida del tenant (estados, middleware, colas, fraude).
5. UI Livewire+Flux y tests Pest que sobreviven a `composer ci:check` (SQLite primero).

Esto permite que Clave opere en v1 sin volverse dependencia transversal, incorporar
dLocal (y su asimetría tarjeta/efectivo) sin reescribir `Tenant`, `Plan`, autorización
ni UI, y recibir Stripe/Cashier en fase 2 encapsulado en `Infrastructure` — si la
demanda real aparece, no antes.
