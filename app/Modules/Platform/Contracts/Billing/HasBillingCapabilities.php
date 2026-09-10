<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

interface HasBillingCapabilities
{
    public function supports(BillingCapability $capability): bool;
}
