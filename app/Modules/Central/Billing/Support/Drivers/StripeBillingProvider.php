<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support\Drivers;

use App\Modules\Central\Billing\Actions\SyncInvoicesAction;
use App\Modules\Central\Billing\Support\PlanManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Contracts\BillingProvider;

class StripeBillingProvider implements BillingProvider
{
    public function createCheckoutSession(Tenant $tenant, string $planId): string
    {
        $stripeId = PlanManager::getStripeId($planId);

        if (! $stripeId) {
            throw new \InvalidArgumentException("Plan [{$planId}] has no Stripe ID configured.");
        }

        $tenantDomain = $tenant->domains()->first()?->domain ?? $tenant->slug . '.' . config('tenancy.central_domain');
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

    public function cancelSubscription(Tenant $tenant, string $subscriptionId, bool $immediately = false): void
    {
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

    public function syncSubscription(Tenant $tenant): void
    {
        $tenant->updateStripeCustomer();
        
        $subscription = $tenant->subscription('default');
        if ($subscription) {
            $subscription->syncStripeStatus();
        }

        // Sync invoices as well
        app(SyncInvoicesAction::class)->execute($tenant);
    }

    public function getSubscriptionData(Tenant $tenant, string $subscriptionId): ?array
    {
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

    public function getInvoices(Tenant $tenant): array
    {
        return $tenant->invoices()->toArray();
    }
}
