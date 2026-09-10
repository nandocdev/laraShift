<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use App\Modules\Platform\Contracts\TenantContract;

interface CheckoutProvider
{
    public function createCheckout(TenantContract $tenant, PlanRef $plan): CheckoutSessionData;
}
