<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use App\Modules\Platform\Contracts\TenantContract;

interface BillingManager
{
    public function providerFor(TenantContract $tenant): BillingProvider;
}
