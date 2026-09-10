# Billing Core — Arquitectura agnóstica a proveedor (Stripe / dLocal / PayPal)

## 0. TL;DR

Billing Core agnóstico al proveedor, inspirado en Cashier pero **domain-first, no Stripe-first**. Stripe, dLocal y PayPal son adapters intercambiables detrás de contratos segregados por capacidad.

Diferencia clave respecto a la primera versión de este documento: **no se asume que todos los proveedores poseen un ciclo de vida de suscripción**. dLocal no lo tiene de forma uniforme — la recurrencia depende del método de pago (tarjeta/Pix sí, efectivo/vouchers no). El diseño modela esa asimetría explícitamente en vez de esconderla detrás de una interfaz común que después se rompe en producción.

```text
                         ┌─────────────────────┐
                         │     Application      │
                         │                      │
                         │ SubscribeTenant      │
                         │ ChangePlan           │
                         │ CancelSubscription   │
                         │ CreateCheckout       │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌─────────────────────┐
                         │    Billing Core      │
                         │                      │
                         │ Customer             │
                         │ Subscription         │
                         │ Invoice              │
                         │ Payment              │
                         │ Plan / Price         │
                         │ BillingScheduler     │  ← nuevo: reloj propio
                         └──────────┬───────────┘
                                    │
                             BillingProvider
                                    │
              ┌─────────────────────┼─────────────────────┐
              ▼                     ▼                     ▼
        ┌───────────┐        ┌─────────────┐       ┌───────────┐
        │  Stripe   │        │   dLocal    │       │  PayPal   │
        │  Adapter  │        │   Adapter   │       │  Adapter  │
        └─────┬─────┘        └──────┬──────┘       └─────┬─────┘
              ▼                     ▼                    ▼
           Stripe                 dLocal               PayPal
```

---

## 1. El objetivo

No es un clon de Laravel Cashier.

> **Cashier es Stripe-first. Este sistema es Domain-first.**

El dominio conoce conceptos de billing:

```text
Customer
Subscription
Plan
Price
Payment
Invoice
Refund
Checkout
PaymentMethod
```

pero **no conoce Stripe ni dLocal**.

```php
$billing->subscribe(tenant: $tenant, plan: $plan);
```

Nunca:

```php
Stripe::createSubscription(...);
Dlocal::createSubscription(...);
```

---

## 2. La asimetría que hay que modelar desde el día uno

Antes de tocar código: la razón de que este documento no sea "wrapper de Stripe y dLocal" es esta tabla. Todo lo demás se deriva de aceptarla.

| Método de pago | Recurrencia nativa del proveedor | Quién dispara el cobro |
|---|---|---|
| Tarjeta (Stripe) | Sí — Stripe posee el ciclo completo | Stripe |
| Tarjeta (dLocal, tokenizada) | Parcial — MIT (Merchant Initiated Transaction) | **Nosotros** |
| Pix (dLocal, Brasil) | Parcial — mandato + token | **Nosotros** |
| OXXO / boleto / efectivo (dLocal) | Ninguna — voucher de un solo uso | Nadie. No hay recurrencia posible |

Consecuencia directa: `BillingCapability::Subscriptions` no puede ser una propiedad del *proveedor*. Es una propiedad de la combinación **proveedor + método de pago**. Esto se resuelve en la sección 8.

---

## 3. Billing Manager — punto de entrada único

```php
interface BillingManager
{
    public function providerFor(Tenant $tenant): BillingProvider;
}

final class DefaultBillingManager implements BillingManager
{
    public function providerFor(Tenant $tenant): BillingProvider
    {
        return match ($tenant->billing_provider) {
            'stripe' => app(StripeBillingProvider::class),
            'dlocal' => app(DlocalBillingProvider::class),
            default => throw new UnsupportedBillingProvider(),
        };
    }
}
```

**El proveedor no es dueño del estado de la aplicación.** El estado vive en el Billing Core.

---

## 4. Modelo de dominio propio

### 4.1 `billing_accounts`

```text
billing_accounts

id
tenant_id
provider
provider_customer_id
currency
country
status
created_at
updated_at
```

```text
tenant_id             = 123
provider              = dlocal
provider_customer_id  = ABC-83929
currency              = USD
country               = PA
```

### 4.2 `plans`

```text
plans

id
name
slug
description
active
```

```text
Starter
Professional
Business
Enterprise
```

### 4.3 `prices`

El precio vive separado del plan porque un mismo plan puede tener representaciones distintas por proveedor/moneda.

```text
prices

id
plan_id
currency
amount
interval
interval_count
provider
provider_price_id
active
```

```text
Professional / USD / $29 / monthly

provider = stripe   → provider_price_id = price_1ABC...
provider = dlocal   → provider_price_id = PLAN-83920
```

### 4.4 `subscriptions`

Corrección respecto a la v1: `subscriptions` ya no guarda `provider` de forma independiente. Se referencia a través de `billing_account_id`. Sin esto, un tenant que migra de dLocal a Stripe (caso real: cliente panameño que empieza con pagos locales y luego opera con tarjetas internacionales) deja suscripciones huérfanas con `provider` desincronizado de su `billing_account` real.

```text
subscriptions

id
tenant_id
billing_account_id     -- FK a billing_accounts (reemplaza el campo `provider` suelto)
plan_id
price_id
payment_method_type     -- nuevo: card | cash | wallet | bank_transfer

provider_subscription_id   -- nullable: no todo método de pago tiene uno

status

trial_ends_at
current_period_start
current_period_end

cancel_at_period_end
canceled_at

created_at
updated_at
```

```php
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Paused = 'paused';
    case Canceled = 'canceled';
    case Incomplete = 'incomplete';
}
```

El adapter traduce estados del proveedor **cuando el proveedor tiene ese concepto**:

```text
Stripe "active"           → SubscriptionStatus::Active
dLocal MIT "approved"     → SubscriptionStatus::Active   (solo para card/Pix)
dLocal cash (voucher)     → no aplica: no hay estado de suscripción que traducir
```

---

## 5. Contratos segregados por capacidad

No se construye una interfaz monolítica de 40 métodos. Se separan responsabilidades.

### 5.1 Checkout

```php
interface CheckoutProvider
{
    public function createCheckout(CheckoutRequest $request): CheckoutSession;
}
```

### 5.2 Customers

```php
interface CustomerProvider
{
    public function createCustomer(CustomerData $customer): ProviderCustomer;
}
```

### 5.3 Subscriptions

Corrección respecto a la v1: se añade `chargeRecurring()`. Es el método que falta cuando el proveedor no posee el reloj de facturación — algo que Stripe hace solo y dLocal (para card/Pix vía MIT) no.

```php
interface SubscriptionProvider
{
    public function createSubscription(SubscriptionRequest $request): ProviderSubscription;

    public function changeSubscription(
        ProviderSubscription $subscription,
        Price $price
    ): ProviderSubscription;

    public function cancelSubscription(ProviderSubscription $subscription): void;

    /**
     * Dispara un cobro sobre una suscripción MIT-based.
     * Stripe no necesita implementarlo (no-op o excepción):
     * Stripe es su propio reloj. dLocal (card/Pix) sí lo necesita.
     */
    public function chargeRecurring(
        ProviderSubscription $subscription,
        Money $amount
    ): ProviderPayment;
}
```

### 5.4 Payments

```php
interface PaymentProvider
{
    public function createPayment(PaymentRequest $request): ProviderPayment;

    public function refund(ProviderPayment $payment, ?Money $amount = null): Refund;
}
```

### 5.5 Webhooks

```php
interface WebhookProvider
{
    public function verify(WebhookRequest $request): VerifiedWebhook;

    public function normalize(VerifiedWebhook $webhook): BillingEvent;
}
```

---

## 6. Billing Events — normalización

Stripe produce:

```text
checkout.session.completed
invoice.payment_succeeded
customer.subscription.updated
customer.subscription.deleted
```

dLocal produce eventos distintos y, para métodos de pago único (efectivo), **puede no producir ningún evento de finalización** si el cliente nunca paga el voucher — no hay "declined", simplemente no llega nada. Esto se resuelve con el Scheduler (sección 9), no solo con el pipeline de eventos.

```text
Stripe  → StripeWebhookAdapter  → BillingEvent
dLocal  → DlocalWebhookAdapter  → BillingEvent
```

```php
enum BillingEventType: string
{
    case CheckoutCompleted = 'checkout.completed';

    case PaymentSucceeded = 'payment.succeeded';
    case PaymentFailed = 'payment.failed';

    case SubscriptionCreated = 'subscription.created';
    case SubscriptionUpdated = 'subscription.updated';
    case SubscriptionCanceled = 'subscription.canceled';

    case InvoiceCreated = 'invoice.created';
    case InvoicePaid = 'invoice.paid';
    case InvoiceFailed = 'invoice.failed';

    case RefundCreated = 'refund.created';
}
```

---

## 7. Webhook Pipeline

```text
STRIPE
  │ webhook
  ▼
StripeWebhookAdapter
  │
  ▼
BillingEvent
  │
  ▼
BillingEventHandler
  │
  ├── Subscription update
  ├── Payment update
  └── Invoice update
```

```text
DLOCAL
  │ webhook (solo aplica a card/Pix; efectivo no genera este flujo)
  ▼
DlocalWebhookAdapter
  │
  ▼
BillingEvent
  │
  ▼
BillingEventHandler
```

El dominio nunca contiene:

```php
if ($provider === 'stripe') { ... }
```

Eso es una señal de diseño podrido.

---

## 8. Capacidades por proveedor **y por método de pago**

Corrección central respecto a la v1. La v1 definía capacidades a nivel de proveedor:

```php
$provider->supports(BillingCapability::Subscriptions); // ← insuficiente
```

Esto es falso para dLocal: "soporta suscripciones" depende de si el cliente paga con tarjeta o con OXXO. La capacidad se resuelve con una segunda dimensión:

```php
enum BillingCapability: string
{
    case Checkout = 'checkout';
    case OneTimePayments = 'one_time_payments';
    case Subscriptions = 'subscriptions';
    case Refunds = 'refunds';
    case Invoices = 'invoices';
    case CustomerPortal = 'customer_portal';
    case Coupons = 'coupons';
    case Trials = 'trials';
}

enum PaymentMethodType: string
{
    case Card = 'card';
    case Cash = 'cash';          // OXXO, boletos, efectivo
    case Wallet = 'wallet';
    case BankTransfer = 'bank_transfer';
    case Pix = 'pix';
}
```

```php
interface BillingProvider
{
    public function supports(
        BillingCapability $capability,
        ?PaymentMethodType $forMethod = null
    ): bool;
}
```

Resultado explícito, no implícito:

```text
StripeProvider->supports(Subscriptions)                          → true (no depende del método)
DlocalProvider->supports(Subscriptions, forMethod: Card)          → true (vía MIT)
DlocalProvider->supports(Subscriptions, forMethod: Pix)           → true (vía mandato)
DlocalProvider->supports(Subscriptions, forMethod: Cash)          → false
```

La aplicación consulta esto **antes** de ofrecer auto-renovación en la UI:

```php
if (!$billing->for($tenant)->supports(Subscriptions, forMethod: $selectedMethod)) {
    // degradar a "recordatorio de pago manual cada ciclo",
    // nunca fallar silenciosamente ni fingir que hay una suscripción activa
}
```

---

## 9. Billing Scheduler — el reloj que dLocal no tiene

Componente que no existía en la v1 y es obligatorio para cualquier proveedor sin ciclo de vida propio.

```php
final class BillingScheduler
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private BillingManager $billingManager,
    ) {}

    /**
     * Corre diariamente (scheduled job).
     * Busca suscripciones MIT-based cuyo período venció y dispara el cobro.
     * Las suscripciones Stripe-managed se ignoran: Stripe ya cobró solo.
     */
    public function run(): void
    {
        $due = $this->subscriptions->dueForMitCharge();

        foreach ($due as $subscription) {
            $provider = $this->billingManager->providerFor($subscription->tenant);

            try {
                $payment = $provider->chargeRecurring(
                    $subscription->providerSubscription(),
                    $subscription->price->amount
                );

                $this->handleChargeResult($subscription, $payment);
            } catch (ChargeFailedException $e) {
                $subscription->transitionTo(SubscriptionStatus::PastDue);
            }
        }
    }
}
```

Este componente es el que hace viable "Cashier-like para dLocal": Cashier no lo necesita porque Stripe es su propio scheduler.

---

## 10. Subscription lifecycle — dual trigger

Corrección respecto a la v1. El diagrama original asumía que todas las transiciones de estado vienen de un webhook (`payment.failed → past_due`). Eso es cierto para tarjeta/Pix. Es falso para efectivo: si el cliente nunca paga el voucher, **no llega ningún evento** — no hay "declined", simplemente silencio.

```text
             ┌───────────┐
             │   Trial   │
             └─────┬─────┘
                   │
                   ▼
             ┌───────────┐
             │  Active   │
             └─────┬─────┘
                   │
          ┌────────┼────────┐
          │        │        │
          ▼        ▼        ▼
      PastDue    Cancel   Upgrade
          │
          ▼
       Active
          │
          ▼
      Canceled
```

Dos fuentes de verdad para la transición a `PastDue`, no una:

```php
// Fuente 1: evento (card, Pix)
// BillingEventHandler
case BillingEventType::PaymentFailed:
    $subscription->transitionTo(SubscriptionStatus::PastDue);
    break;

// Fuente 2: timeout (efectivo, y como red de seguridad general)
// Job diario, independiente del webhook pipeline
if ($subscription->current_period_end->isPast()
    && !$subscription->hasPaymentForCurrentPeriod()) {
    $subscription->transitionTo(SubscriptionStatus::PastDue);
}
```

Es más complejo que "solo eventos", pero ignorarlo no es simplicidad — es negar que el efectivo existe en el mix de pagos LATAM.

---

## 11. Idempotencia

```text
billing_webhook_events

id
provider
provider_event_id
event_type
payload
processed_at
failed_at
attempts
created_at
```

```sql
UNIQUE(provider, provider_event_id)
```

```php
if ($eventRepository->alreadyProcessed($event)) {
    return;
}
```

Sin esto: un webhook duplicado o fuera de orden termina cobrando dos veces a las 3 a.m. Esto importa más que cualquier elegancia arquitectónica.

---

## 12. Checkout

```php
$checkout = $billing->checkout(tenant: $tenant, price: $price);
```

```php
final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
        public CheckoutStatus $status,
        public string $provider,
    ) {}
}
```

```text
Stripe → url = https://checkout.stripe.com/...
dLocal → url = https://checkout.dlocal.com/...
```

```php
return redirect()->away($checkout->url);
```

`CheckoutSession` **no implica** `Subscription`. Son conceptos distintos: un checkout puede terminar en un pago único (efectivo) sin generar ninguna suscripción recurrente.

---

## 13. Feature system

```text
plans
    ├── features
    └── limits
```

```text
Professional
users = 25
projects = 20
storage = 50GB
api = true
```

```php
$tenant->can('projects.create');
$tenant->limit('users');
```

Nunca:

```php
if ($stripeSubscription->items[0]->price->id === 'price_xxx') { ... }
```

---

## 14. API de aplicación (`Billing`)

```php
interface Billing
{
    public function checkout(Tenant $tenant, Price $price): CheckoutSession;

    public function subscribe(Tenant $tenant, Price $price): Subscription;

    public function changePlan(Tenant $tenant, Price $price): Subscription;

    public function cancel(Tenant $tenant): void;

    public function resume(Tenant $tenant): void;

    public function supports(
        BillingCapability $capability,
        ?PaymentMethodType $forMethod = null
    ): bool;
}
```

```php
$subscription = $billing->subscribe($tenant, $price);

if ($billing->for($tenant)->active()) {
    // ...
}
```

La aplicación no sabe quién procesa el dinero, pero sí puede preguntar qué es posible antes de prometerlo en la UI.

---

## 15. Provider-specific metadata

```text
provider_metadata JSONB
```

```json
{ "stripe": { "payment_intent": "pi_123", "invoice": "in_123" } }
{ "dlocal": { "payment_id": "123456", "transaction_id": "ABC" } }
```

**No convertir `metadata` en basurero.** Todo dato que el dominio consulta activamente (para lógica, no solo para debug) necesita su propia columna tipada.

---

## 16. Cashier como implementación de infraestructura

```text
                     Billing Core
                          │
                  BillingProvider
                          │
              ┌───────────┴───────────┐
              ▼                       ▼
      StripeBillingProvider    DlocalBillingProvider
              │
              ▼
       Laravel Cashier
              │
              ▼
           Stripe
```

```php
final class StripeSubscriptionProvider implements SubscriptionProvider
{
    public function createSubscription(SubscriptionRequest $request): ProviderSubscription
    {
        $subscription = $request->tenant
            ->billable()
            ->newSubscription('default', $request->price->provider_price_id)
            ->create();

        return StripeSubscriptionMapper::map($subscription);
    }

    public function chargeRecurring(ProviderSubscription $subscription, Money $amount): ProviderPayment
    {
        // No-op: Stripe cobra solo. Si se llama, es un bug de scheduling
        // (el scheduler no debería seleccionar suscripciones Stripe-managed).
        throw new LogicException('Stripe subscriptions are self-managed; chargeRecurring is not applicable.');
    }
}
```

El resto del sistema **jamás importa Cashier**.

---

## 17. Estructura de carpetas — con alcance condicionado

Corrección respecto a la v1: la estructura completa Domain/Application/Infrastructure/UI con `Actions/` separado de `Services/` se justifica **solo si este Billing Core se reutiliza entre varios productos** (WFM, HRIS, scheduling — los proyectos activos de Fernando). Si es para un único SaaS, es sobre-ingeniería en v1: la ceremonia de capas no se amortiza con un solo caso de uso.

### Si se reutiliza entre productos (vive en Plinth como boilerplate)

```text
Modules/
└── Billing/
    ├── Domain/
    │   ├── Models/          (BillingAccount, Plan, Price, Subscription, Payment, Invoice, Refund)
    │   ├── Enums/            (SubscriptionStatus, PaymentStatus, BillingEventType, BillingCapability, PaymentMethodType)
    │   └── Contracts/        (BillingProvider, CheckoutProvider, PaymentProvider, SubscriptionProvider, WebhookProvider)
    ├── Application/
    │   ├── Actions/          (CreateCheckout, SubscribeTenant, ChangePlan, CancelSubscription, RefundPayment)
    │   └── Services/         (BillingManager, BillingEventProcessor, BillingScheduler)
    ├── Infrastructure/
    │   ├── Stripe/           (StripeProvider, StripeCheckout, StripePayments, StripeSubscriptions, StripeWebhooks)
    │   └── Dlocal/           (DlocalProvider, DlocalCheckout, DlocalPayments, DlocalSubscriptions, DlocalWebhooks)
    └── UI/
        └── Livewire/         (Billing, Plans, Subscription)
```

### Si es para un único producto (v1 mínima)

```text
Modules/
└── Billing/
    ├── Domain/               (Models, Enums, Contracts — igual que arriba)
    ├── Infrastructure/
    │   ├── Stripe/
    │   └── Dlocal/
    └── Services/             (BillingManager, BillingEventProcessor, BillingScheduler — sin separar Actions)
```

Se separa `Application/Actions` de `Services` cuando un segundo caso de uso lo demuestre necesario, no antes.

---

## 18. Integración multi-tenant (RLS)

```text
                    Tenant
                       │
                       ▼
                BillingAccount
                       │
              ┌────────┴────────┐
              │                 │
          provider          provider_customer_id
              │                 │
              ▼                 ▼
           dlocal             ABC-83929
              │
              ▼
        Subscription (FK → billing_account_id)
              │
              ▼
             Plan
              │
              ▼
          Features
```

Todas las entidades internas con `tenant_id` bajo RLS.

Resolución de webhook — nunca confiar en datos no autenticados del payload:

```text
provider + provider_customer_id → BillingAccount → Tenant
```

Nunca:

```text
user_id enviado por el frontend
```

---

## 19. Fuera de alcance (v1)

No se implementa inicialmente:

- Marketplace / split payments
- Multi-provider simultáneo por transacción
- Payment orchestration / smart routing entre gateways
- Currency optimization
- Fraud engine propio
- Tax engine propio
- Sistema contable completo
- Event sourcing
- Microservicio de billing separado

Alcance v1:

```text
Stripe + dLocal
      │
      ▼
Billing Core
      │
      ├── Checkout
      ├── Payments
      ├── Subscriptions (con capability por método de pago)
      ├── BillingScheduler (para MIT/dLocal)
      ├── Webhooks
      └── Refunds
```

---

## 20. Decisión arquitectónica clave

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
     Stripe        dLocal       PayPal
     Adapter       Adapter      Adapter
        │            │            │
     Cashier        SDK/API      SDK/API
        │            │            │
        ▼            ▼            ▼
     Stripe        dLocal       PayPal
```

La abstracción central no es `PaymentGateway`. Es un Billing Core con:

1. Capacidades independientes **resueltas por proveedor y por método de pago**, no solo por proveedor.
2. Eventos normalizados vía adapters, con idempotencia obligatoria.
3. Un reloj propio (`BillingScheduler`) para proveedores que no poseen ciclo de vida de facturación — sin este componente, "Cashier-like para dLocal" es una promesa que no se puede cumplir.

Esto permite que Stripe/Cashier sea el primer proveedor sin volverse una dependencia transversal del SaaS, e incorporar dLocal (y su asimetría de capacidades por método de pago) sin reescribir `Tenant`, `Plan`, `Subscription`, autorización ni UI.
