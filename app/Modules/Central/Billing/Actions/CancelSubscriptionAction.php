<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class CancelSubscriptionAction
{
    public function execute(Tenant $tenant, bool $immediately = false): void
    {
        $subscription = $tenant->subscription('default');

        if (! $subscription) {
            return;
        }

        if ($immediately) {
            $subscription->cancelNow();
        } else {
            $subscription->cancel();
        }

        activity('billing')
            ->performedOn($tenant)
            ->withProperties(['immediately' => $immediately])
            ->log('subscription_cancelled');
    }
}
