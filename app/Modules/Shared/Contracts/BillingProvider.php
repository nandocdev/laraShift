<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

use App\Modules\Central\Provisioning\Models\Tenant;

interface BillingProvider
{
    public function createCheckoutSession(Tenant $tenant, string $planId): string;
    
    public function cancelSubscription(Tenant $tenant, string $subscriptionId, bool $immediately = false): void;
    
    public function syncSubscription(Tenant $tenant): void;
    
    public function getInvoices(Tenant $tenant): array;
}
