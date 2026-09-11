<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use App\Modules\Platform\Contracts\TenantContract;

interface PaymentProvider
{
    public function chargeDirect(DirectPaymentData $payment): ProviderPaymentRef;

    public function refund(TenantContract $tenant, string $providerPaymentId, ?int $amountCents = null): void;
}
