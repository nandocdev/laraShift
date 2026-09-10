<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use App\Modules\Platform\Contracts\TenantContract;

interface CheckoutProvider
{
    /**
     * The displayId is the idempotency key: the caller generates it once per
     * checkout intent so concurrent submits collapse to a single payment row.
     * Pure HTTP: writes nothing, creates no records.
     */
    public function createCheckout(TenantContract $tenant, PlanRef $plan, string $displayId): CheckoutSessionData;
}
