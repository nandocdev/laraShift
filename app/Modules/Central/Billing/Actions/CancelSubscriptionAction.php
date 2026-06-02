<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Support\BillingManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\SubscriptionCancelled;

final readonly class CancelSubscriptionAction
{
    public function execute(Tenant $tenant, string $subscriptionId, bool $immediately = false): void
    {
        app(BillingManager::class)->cancelSubscription($tenant, $subscriptionId, $immediately);

        SubscriptionCancelled::dispatch($tenant->id, $subscriptionId, $immediately ? 'immediate_cancellation' : 'end_of_period');

        activity('billing')
            ->performedOn($tenant)
            ->withProperties(['immediately' => $immediately, 'subscription_id' => $subscriptionId])
            ->log('subscription_cancelled');
    }
}
