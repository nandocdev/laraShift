<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\BillingProvider;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Support\Manager;

class BillingManager extends Manager implements BillingProvider
{
    public function getDefaultDriver(): string
    {
        return config('payments.default', 'clave');
    }

    public function createPaguelofacilDriver(): InternalBillingProvider
    {
        return $this->container->make(InternalBillingProvider::class);
    }

    public function createStripeDriver(): StripeBillingProvider
    {
        return $this->container->make(StripeBillingProvider::class);
    }

    public function createDlocalDriver(): InternalBillingProvider
    {
        return $this->container->make(InternalBillingProvider::class);
    }

    public function createClaveDriver(): InternalBillingProvider
    {
        return $this->createPaguelofacilDriver();
    }

    public function forTenant(TenantContract $tenant): BillingProvider
    {
        $gateway = $tenant instanceof Tenant ? $tenant->billing_gateway : ($tenant->billing_gateway ?? null);

        return $this->driver($gateway ?? $this->getDefaultDriver());
    }

    public function createCheckoutSession(TenantContract $tenant, string $planId): string
    {
        return $this->forTenant($tenant)->createCheckoutSession($tenant, $planId);
    }

    public function cancelSubscription(TenantContract $tenant, string $subscriptionId, bool $immediately = false): void
    {
        $this->forTenant($tenant)->cancelSubscription($tenant, $subscriptionId, $immediately);
    }

    public function getSubscriptionData(TenantContract $tenant, string $subscriptionId): ?array
    {
        return $this->forTenant($tenant)->getSubscriptionData($tenant, $subscriptionId);
    }

    public function syncSubscription(TenantContract $tenant): void
    {
        $this->forTenant($tenant)->syncSubscription($tenant);
    }

    public function getInvoices(TenantContract $tenant): array
    {
        return $this->forTenant($tenant)->getInvoices($tenant);
    }
}
