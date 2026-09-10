<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use App\Modules\Platform\Contracts\TenantContract;

interface PaymentProvider extends HasBillingCapabilities
{
    public function chargeRecurring(TenantContract $tenant, string $providerSubscriptionId, int $amountCents): ProviderPaymentRef;

    public function refund(TenantContract $tenant, string $providerPaymentId, ?int $amountCents = null): void;
}
