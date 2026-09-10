<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Billing\Application\Actions\SyncInvoices;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\Billing\BillingCapability;
use App\Modules\Platform\Contracts\Billing\BillingEventData;
use App\Modules\Platform\Contracts\Billing\BillingEventType;
use App\Modules\Platform\Contracts\Billing\WebhookProvider;
use App\Modules\Platform\Contracts\BillingProvider;
use App\Modules\Platform\Contracts\TenantContract;
use InvalidArgumentException;
use Stripe\Webhook as StripeWebhook;

class StripeBillingProvider implements BillingProvider, WebhookProvider
{
    public function supports(BillingCapability $capability): bool
    {
        // Stripe/Cashier: checkout + gateway-managed subscriptions. Refunds
        // and portal have no adapter yet → false until implemented.
        return in_array($capability, [
            BillingCapability::Checkout,
            BillingCapability::Subscriptions,
            BillingCapability::RecurringCharge,
        ], true);
    }

    public function verify(string $rawPayload, string $signature, string $secret): bool
    {
        try {
            StripeWebhook::constructEvent($rawPayload, $signature, $secret);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function normalize(array $payload): BillingEventData
    {
        $type = match ($payload['type'] ?? 'unknown') {
            'checkout.session.completed' => BillingEventType::CheckoutCompleted,
            'invoice.created' => BillingEventType::InvoiceCreated,
            'invoice.paid', 'invoice.payment_succeeded', 'payment_intent.succeeded' => BillingEventType::PaymentSucceeded,
            'invoice.payment_failed' => BillingEventType::InvoiceFailed,
            'payment_intent.payment_failed' => BillingEventType::PaymentFailed,
            'customer.subscription.created' => BillingEventType::SubscriptionCreated,
            'customer.subscription.updated' => BillingEventType::SubscriptionUpdated,
            'customer.subscription.deleted' => BillingEventType::SubscriptionCanceled,
            'charge.refunded' => BillingEventType::RefundCreated,
            default => BillingEventType::PaymentPending,
        };

        $object = $payload['data']['object'] ?? [];

        return new BillingEventData(
            type: $type,
            providerReference: (string) ($payload['id'] ?? $object['id'] ?? ''),
            displayId: (string) ($object['metadata']['displayId'] ?? $object['metadata']['display_id'] ?? $object['id'] ?? ''),
            amountCents: (int) ($object['amount_paid'] ?? $object['amount_total'] ?? $object['amount'] ?? 0),
        );
    }

    /**
     * Resolve the Billable Tenant model from any TenantContract.
     * The contract stays clean; the Cashier coupling lives here, in
     * Infrastructure, and never leaks into Application/Domain.
     */
    private function tenantModel(TenantContract $tenant): Tenant
    {
        $model = $tenant instanceof Tenant ? $tenant : Tenant::find($tenant->getId());

        if (! $model) {
            throw new InvalidArgumentException('Stripe billing requires a persisted Tenant.');
        }

        return $model;
    }

    public function createCheckoutSession(TenantContract $tenant, string $planId): string
    {
        $model = $this->tenantModel($tenant);

        $stripeId = PlanManager::getStripeId($planId);

        if (! $stripeId) {
            throw new InvalidArgumentException("Plan [{$planId}] has no Stripe ID configured.");
        }

        $tenantDomain = $model->getDomain() ?: $model->slug.'.'.config('tenancy.central_domain');
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';
        $port = parse_url(config('app.url'), PHP_URL_PORT);
        $portSuffix = $port ? ":$port" : '';
        $baseUrl = "$scheme://$tenantDomain$portSuffix";

        return $model->newSubscription('default', $stripeId)
            ->checkout([
                'success_url' => "$baseUrl/billing/success",
                'cancel_url' => "$baseUrl/billing/cancel",
            ])->url;
    }

    public function cancelSubscription(TenantContract $tenant, string $subscriptionId, bool $immediately = false): void
    {
        $model = $this->tenantModel($tenant);

        $subscription = $model->subscriptions()->where('stripe_id', $subscriptionId)->first();

        if (! $subscription) {
            return;
        }

        if ($immediately) {
            $subscription->cancelNow();
        } else {
            $subscription->cancel();
        }
    }

    public function syncSubscription(TenantContract $tenant): void
    {
        $model = $this->tenantModel($tenant);

        $model->updateStripeCustomer();

        $subscription = $model->subscription('default');
        if ($subscription) {
            $subscription->syncStripeStatus();
        }

        // Sync invoices as well
        app(SyncInvoices::class)->execute($model);
    }

    public function getSubscriptionData(TenantContract $tenant, string $subscriptionId): ?array
    {
        $model = $this->tenantModel($tenant);

        $subscription = $model->subscriptions()->where('stripe_id', $subscriptionId)->first();

        if (! $subscription) {
            return null;
        }

        $stripeSubscription = $subscription->asStripeSubscription();

        return [
            'status' => $stripeSubscription->status,
            'current_period_end' => $stripeSubscription->current_period_end,
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end,
        ];
    }

    public function getInvoices(TenantContract $tenant): array
    {
        return $this->tenantModel($tenant)->invoices()->toArray();
    }
}
