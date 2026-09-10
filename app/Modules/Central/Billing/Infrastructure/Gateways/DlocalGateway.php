<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Billing\Domain\Enums\BillingEventType;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Exceptions\RecurringBillingNotSupported;
use App\Modules\Central\Billing\Domain\Exceptions\WebhookVerificationFailed;
use App\Modules\Central\Billing\Domain\Models\PaymentReference;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Billing\Infrastructure\Gateways\Dlocal\DlocalApiException;
use App\Modules\Central\Billing\Infrastructure\Gateways\Dlocal\DlocalHttpClient;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Platform\Contracts\Billing\BillingCapability;
use App\Modules\Platform\Contracts\Billing\BillingEventData;
use App\Modules\Platform\Contracts\Billing\BillingProvider;
use App\Modules\Platform\Contracts\Billing\CheckoutProvider;
use App\Modules\Platform\Contracts\Billing\CheckoutSessionData;
use App\Modules\Platform\Contracts\Billing\DirectPaymentData;
use App\Modules\Platform\Contracts\Billing\PaymentMethodType;
use App\Modules\Platform\Contracts\Billing\PaymentProvider;
use App\Modules\Platform\Contracts\Billing\PlanRef;
use App\Modules\Platform\Contracts\Billing\ProviderPaymentRef;
use App\Modules\Platform\Contracts\Billing\ProviderSubscriptionRef;
use App\Modules\Platform\Contracts\Billing\SubscriptionProvider;
use App\Modules\Platform\Contracts\Billing\WebhookProvider;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Support\Facades\Log;

final readonly class DlocalGateway implements BillingProvider, CheckoutProvider, PaymentProvider, SubscriptionProvider, WebhookProvider
{
    public function __construct(
        private DlocalHttpClient $client,
        private PlanManager $plans,
    ) {}

    public function supports(BillingCapability $capability, ?PaymentMethodType $forMethod = null): bool
    {
        return match ($capability) {
            BillingCapability::Checkout => true,
            // Direct requires an explicit method: bare capability is false
            // so the UI is forced to ask which method before promising.
            BillingCapability::DirectPayment => $forMethod === PaymentMethodType::Card,
            // Recurrence only via MIT on saved cards — never cash.
            BillingCapability::Subscriptions => $forMethod === PaymentMethodType::Card,
            default => false,
        };
    }

    public function identifier(): string
    {
        return 'dlocal';
    }

    public function createCheckout(TenantContract $tenant, PlanRef $plan, string $displayId): CheckoutSessionData
    {
        $response = $this->client->post('/payments', [
            'order_id' => $displayId,
            'amount' => $plan->amountCents / 100,
            'currency' => $plan->currency,
            'country' => config('dlocal.country_default', 'PA'),
            'payment_method_flow' => 'REDIRECT',
            'payer' => [
                'name' => $tenant->getName(),
                'email' => $this->resolveEmail($tenant),
            ],
            'description' => "Plan {$plan->slug}",
            'notification_url' => route('payments.webhooks.dlocal'),
            'metadata' => ['tenant_id' => (string) $tenant->getId(), 'plan_slug' => $plan->slug],
        ], $displayId);

        $url = $response['redirect_url'] ?? throw new DlocalApiException('No redirect URL returned by dLocal.');

        PaymentReference::firstOrCreate(
            ['external_reference' => (string) $response['id']],
            ['order_id' => $displayId, 'context' => PaymentReference::CONTEXT_ORDER, 'tenant_id' => (string) $tenant->getId()]
        );

        return new CheckoutSessionData(id: $displayId, url: (string) $url, provider: 'dlocal');
    }

    /**
     * Server-side charge with a Smart Fields token. The PAN never touches us.
     */
    public function chargeDirect(DirectPaymentData $payment): ProviderPaymentRef
    {
        if (! $payment->paymentToken) {
            throw new \InvalidArgumentException('A dLocal Smart Fields token is required for direct payments.');
        }

        $isSubscription = $payment->subscriptionId !== null;

        $body = [
            'order_id' => $payment->orderId,
            'amount' => $payment->amountCents / 100,
            'currency' => $payment->currency,
            'country' => config('dlocal.country_default', 'PA'),
            'payment_method_id' => 'CARD',
            'payment_method_flow' => 'DIRECT',
            'token' => $payment->paymentToken,
            'payer' => ['user_reference' => $payment->tenantId],
            'description' => $isSubscription ? 'Subscription first charge' : 'Direct charge',
            'notification_url' => route('payments.webhooks.dlocal'),
            'metadata' => array_merge($payment->metadata, [
                'tenant_id' => $payment->tenantId,
                'subscription_id' => $payment->subscriptionId,
            ]),
        ];

        if ($isSubscription) {
            $body['save'] = true;
            $body['stored_credential_type'] = 'SUBSCRIPTION';
            $body['stored_credential_usage'] = 'FIRST';
        }

        $response = $this->client->post('/payments', $body, $payment->orderId);

        PaymentReference::firstOrCreate(
            ['external_reference' => (string) $response['id']],
            ['order_id' => $payment->orderId, 'context' => PaymentReference::CONTEXT_ORDER, 'tenant_id' => $payment->tenantId]
        );

        return new ProviderPaymentRef(
            providerPaymentId: (string) $response['id'],
            gateway: 'dlocal',
            status: $this->mapStatus((string) ($response['status'] ?? '')),
            amountCents: $payment->amountCents,
            cardId: isset($response['card_id']) ? (string) $response['card_id'] : null,
        );
    }

    /**
     * MIT recurring charge on a saved card. The providerSubscriptionId is OUR
     * subscription UUID (dLocal holds no subscription object).
     */
    public function chargeRecurring(TenantContract $tenant, string $providerSubscriptionId, int $amountCents): ProviderPaymentRef
    {
        $subscription = Subscription::findOrFail($providerSubscriptionId);

        if ((string) $subscription->tenant_id !== (string) $tenant->getId()) {
            throw new \RuntimeException('Subscription does not belong to the tenant.');
        }

        if (! $subscription->pm_card_id) {
            throw new RecurringBillingNotSupported("Subscription {$subscription->id} has no saved card (pm_card_id).");
        }

        $orderId = "sub_{$subscription->id}_".now()->format('Ym');

        $response = $this->client->post('/payments', [
            'order_id' => $orderId,
            'amount' => $amountCents / 100,
            'currency' => $this->planCurrency($subscription),
            'country' => config('dlocal.country_default', 'PA'),
            'payment_method_id' => 'CARD',
            'payment_method_flow' => 'DIRECT',
            'card_id' => $subscription->pm_card_id,
            'stored_credential_type' => 'SUBSCRIPTION',
            'stored_credential_usage' => 'USED',
            'description' => "Recurring charge — {$subscription->id}",
            'notification_url' => route('payments.webhooks.dlocal'),
            'metadata' => ['tenant_id' => (string) $tenant->getId(), 'subscription_id' => $subscription->id, 'type' => 'recurring'],
        ], $orderId);

        return new ProviderPaymentRef(
            providerPaymentId: (string) $response['id'],
            gateway: 'dlocal',
            status: $this->mapStatus((string) ($response['status'] ?? '')),
            amountCents: $amountCents,
        );
    }

    public function refund(TenantContract $tenant, string $providerPaymentId, ?int $amountCents = null): void
    {
        $body = $amountCents !== null ? ['amount' => $amountCents / 100] : [];

        $this->client->post("/payments/{$providerPaymentId}/refunds", $body, "refund_{$providerPaymentId}");
    }

    /**
     * No gateway-side subscription object exists: creation happens through
     * direct-charge + FulfillSubscription, recurrence through chargeRecurring.
     */
    public function createSubscription(TenantContract $tenant, PlanRef $plan): ProviderSubscriptionRef
    {
        throw new RecurringBillingNotSupported('dLocal holds no subscription object; subscribe via direct charge, MIT via chargeRecurring.');
    }

    public function changePlan(TenantContract $tenant, string $providerSubscriptionId, PlanRef $plan): ProviderSubscriptionRef
    {
        throw new RecurringBillingNotSupported('dLocal holds no subscription object; plan changes apply to the local subscription row.');
    }

    /**
     * Nothing to cancel remotely. Documented no-op: the local subscription
     * row transition (cancel_at_period_end) is owned by the Action.
     */
    public function cancel(TenantContract $tenant, string $providerSubscriptionId, bool $immediately = false): void
    {
        Log::info('billing.dlocal_cancel_noop', ['subscription_id' => $providerSubscriptionId]);
    }

    public function verify(string $rawPayload, string $signature): bool
    {
        $secret = (string) config('dlocal.webhook_secret');

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawPayload, $secret), $signature);
    }

    /**
     * @throws WebhookVerificationFailed
     */
    public function verifyOrFail(string $rawPayload, string $signature): void
    {
        if (! $this->verify($rawPayload, $signature)) {
            throw new WebhookVerificationFailed('Invalid dLocal webhook signature.');
        }
    }

    public function normalize(array $payload): BillingEventData
    {
        $rawStatus = strtoupper((string) ($payload['status'] ?? ''));

        $status = match ($rawStatus) {
            'PAID', 'SUCCESS', 'APPROVED' => PaymentStatus::Approved,
            'REJECTED', 'CANCELLED', 'FAILED' => PaymentStatus::Declined,
            default => PaymentStatus::Pending,
        };

        $type = match ($status) {
            PaymentStatus::Approved => BillingEventType::PaymentSucceeded->value,
            PaymentStatus::Declined => BillingEventType::PaymentFailed->value,
            default => BillingEventType::CheckoutCompleted->value,
        };

        return new BillingEventData(
            type: $type,
            gateway: 'dlocal',
            gatewayEventId: (string) ($payload['payment_id'] ?? $payload['id'] ?? ''),
            displayId: (string) ($payload['order_id'] ?? ''),
            providerCustomerId: isset($payload['user_id']) ? (string) $payload['user_id'] : null,
            amountCents: isset($payload['amount']) ? (int) round((float) $payload['amount'] * 100) : null,
            currency: isset($payload['currency']) ? (string) $payload['currency'] : null,
            raw: $payload,
        );
    }

    private function mapStatus(string $rawStatus): string
    {
        return match (strtoupper($rawStatus)) {
            'PAID', 'SUCCESS', 'APPROVED' => 'approved',
            'REJECTED', 'CANCELLED', 'FAILED' => 'declined',
            default => 'pending',
        };
    }

    private function planCurrency(Subscription $subscription): string
    {
        if ($subscription->plan_id) {
            try {
                return $this->plans->findById($subscription->plan_id)->currency;
            } catch (\Throwable) {
                // Fall through to default.
            }
        }

        return 'USD';
    }

    private function resolveEmail(TenantContract $tenant): ?string
    {
        $model = config('tenancy.tenant_model');
        $row = $model::where('id', $tenant->getId())->first();

        return is_string($row?->email) ? $row->email : null;
    }
}
