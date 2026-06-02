<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class SyncSubscriptionAction
{
    public function execute(Tenant $tenant): void
    {
        $tenant->updateStripeCustomer();
        
        // This is mainly for manual triggers or recovery
        activity('billing')
            ->performedOn($tenant)
            ->log('subscription_synced');
    }
}
