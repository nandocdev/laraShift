<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Billing\Application\Actions\SyncInvoices;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\BillingProvider;
use App\Modules\Platform\Contracts\TenantContract;

class StripeBillingProvider implements BillingProvider
{
    public function createCheckoutSession(TenantContract $tenant, string $planId): string
    {
        if (! $tenant instanceof Tenant) {
            throw new \InvalidArgumentException('Stripe billing requires a Tenant model.');
        }

        $stripeId = PlanManager::getStripeId($planId);

        if (! $stripeId) {
            throw new \InvalidArgumentException("Plan [{$planId}] has no Stripe ID configured.");
        }

        $tenantDomain = $tenant->domains()->first()?->domain ?? $tenant->slug.'.'.config('tenancy.central_domain');
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';
        $port = parse_url(config('app.url'), PHP_URL_PORT);
        $portSuffix = $port ? ":$port" : '';
        $baseUrl = "$scheme://$tenantDomain$portSuffix";

        return $tenant->newSubscription('default', $stripeId)
            ->checkout([
                'success_url' => "$baseUrl/billing/success",
                'cancel_url' => "$baseUrl/billing/cancel",
            ])->url;
    }

    public function cancelSubscription(TenantContract $tenant, string $subscriptionId, bool $immediately = false): void
    {
        if (! $tenant instanceof Tenant) {
            throw new \InvalidArgumentException('Stripe billing requires a Tenant model.');
        }

        $subscription = $tenant->subscriptions()->where('stripe_id', $subscriptionId)->first();

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
        if (! $tenant instanceof Tenant) {
            throw new \InvalidArgumentException('Stripe billing requires a Tenant model.');
        }

        $tenant->updateStripeCustomer();

        $subscription = $tenant->subscription('default');
        if ($subscription) {
            $subscription->syncStripeStatus();
        }

        // Sync invoices as well
        app(SyncInvoices::class)->execute($tenant);
    }

    public function getSubscriptionData(TenantContract $tenant, string $subscriptionId): ?array
    {
        if (! $tenant instanceof Tenant) {
            throw new \InvalidArgumentException('Stripe billing requires a Tenant model.');
        }

        $subscription = $tenant->subscriptions()->where('stripe_id', $subscriptionId)->first();

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
        if (! $tenant instanceof Tenant) {
            throw new \InvalidArgumentException('Stripe billing requires a Tenant model.');
        }

        return $tenant->invoices()->toArray();
    }
}
