<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use App\Modules\Platform\Contracts\TenantContract;

interface SubscriptionProvider extends HasBillingCapabilities
{
    public function createSubscription(TenantContract $tenant, PlanRef $plan): ProviderSubscriptionRef;

    public function changePlan(TenantContract $tenant, string $providerSubscriptionId, PlanRef $plan): ProviderSubscriptionRef;

    public function cancel(TenantContract $tenant, string $providerSubscriptionId, bool $immediately = false): void;
}
