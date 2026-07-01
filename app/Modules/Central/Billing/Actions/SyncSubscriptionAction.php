<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Support\BillingManager;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class SyncSubscriptionAction
{
    public function execute(Tenant $tenant): void
    {
        app(BillingManager::class)->syncSubscription($tenant);

        activity('billing')
            ->performedOn($tenant)
            ->log('subscription_synced');
    }
}
