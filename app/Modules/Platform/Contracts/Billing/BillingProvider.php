<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

interface BillingProvider
{
    public function identifier(): string;

    public function supports(BillingCapability $capability, ?PaymentMethodType $forMethod = null): bool;
}
