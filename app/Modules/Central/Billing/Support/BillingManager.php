<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support;

use App\Modules\Central\Billing\Support\Drivers\PaguelofacilBillingProvider;
use App\Modules\Central\Billing\Support\Drivers\StripeBillingProvider;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Contracts\BillingProvider;
use Illuminate\Support\Manager;

class BillingManager extends Manager implements BillingProvider
{
    public function getDefaultDriver(): string
    {
        if (config('cashier.key') && config('cashier.secret')) {
            return config('cashier.driver', 'stripe');
        }

        return 'paguelofacil';
    }

    public function createStripeDriver(): StripeBillingProvider
    {
        return new StripeBillingProvider();
    }

    public function createPaguelofacilDriver(): PaguelofacilBillingProvider
    {
        return new PaguelofacilBillingProvider();
    }

    public function forTenant(Tenant $tenant): BillingProvider
    {
        return $this->driver($tenant->billing_gateway ?? $this->getDefaultDriver());
    }

    public function createCheckoutSession(Tenant $tenant, string $planId): string
    {
        return $this->forTenant($tenant)->createCheckoutSession($tenant, $planId);
    }

    public function cancelSubscription(Tenant $tenant, string $subscriptionId, bool $immediately = false): void
    {
        $this->forTenant($tenant)->cancelSubscription($tenant, $subscriptionId, $immediately);
    }

    public function syncSubscription(Tenant $tenant): void
    {
        $this->forTenant($tenant)->syncSubscription($tenant);
    }

    public function getInvoices(Tenant $tenant): array
    {
        return $this->forTenant($tenant)->getInvoices($tenant);
    }
}
