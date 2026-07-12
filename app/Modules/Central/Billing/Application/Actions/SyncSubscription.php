<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Infrastructure\Gateways\BillingManager;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class SyncSubscription
{
    public function execute(Tenant $tenant): void
    {
        app(BillingManager::class)->syncSubscription($tenant);

        activity('billing')
            ->performedOn($tenant)
            ->log('subscription_synced');
    }
}
