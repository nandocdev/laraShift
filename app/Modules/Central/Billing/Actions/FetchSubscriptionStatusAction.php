<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class FetchSubscriptionStatusAction
{
    public function execute(string $tenantId): array
    {
        $tenant = Tenant::findOrFail($tenantId);
        $subscription = $tenant->subscription('default');

        return [
            'tenant_id' => $tenant->id,
            'plan_id' => $tenant->plan_id,
            'status' => $tenant->status,
            'subscription' => $subscription ? [
                'stripe_id' => $subscription->stripe_id,
                'stripe_status' => $subscription->stripe_status,
                'ends_at' => $subscription->ends_at,
                'on_grace_period' => $subscription->onGracePeriod(),
                'active' => $subscription->active(),
            ] : null,
        ];
    }
}
