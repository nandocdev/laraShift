<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Platform\Contracts\Billing\BillingManager;
use App\Modules\Platform\Contracts\Billing\SubscriptionProvider;

final readonly class CancelSubscriptionAction
{
    public function __construct(private BillingManager $billing) {}

    public function execute(Subscription $subscription, bool $immediately = false): void
    {
        $model = config('tenancy.tenant_model');
        $tenant = $model::findOrFail($subscription->tenant_id);

        $gateway = $this->billing->providerFor($tenant);

        if ($gateway instanceof SubscriptionProvider) {
            $gateway->cancel($tenant, $subscription->provider_subscription_id ?? $subscription->id, $immediately);
        }

        $subscription->update($immediately
            ? ['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()]
            : ['cancel_at_period_end' => true]);
    }

    public function resume(Subscription $subscription): void
    {
        if ($subscription->status === SubscriptionStatus::Canceled) {
            return;
        }

        $subscription->update(['cancel_at_period_end' => false]);
    }
}
