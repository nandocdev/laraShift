<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support;

use App\Modules\Central\Billing\Support\Drivers\InternalBillingProvider;
use App\Modules\Central\Billing\Support\Drivers\StripeBillingProvider;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Contracts\BillingProvider;
use Illuminate\Support\Manager;

class BillingManager extends Manager implements BillingProvider
{
    public function getDefaultDriver(): string
    {
        return config('payments.default', 'dlocal');
    }

    public function createPaguelofacilDriver(): InternalBillingProvider
    {
        return $this->container->make(InternalBillingProvider::class);
    }

    public function createStripeDriver(): StripeBillingProvider
    {
        return $this->container->make(StripeBillingProvider::class);
    }

    public function createDlocalDriver(): \App\Modules\Central\Billing\Support\Drivers\DlocalBillingProvider
    {
        return $this->container->make(\App\Modules\Central\Billing\Support\Drivers\DlocalBillingProvider::class);
    }

    public function createClaveDriver(): InternalBillingProvider
    {
        return $this->createPaguelofacilDriver();
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

    public function getSubscriptionData(Tenant $tenant, string $subscriptionId): ?array
    {
        return $this->forTenant($tenant)->getSubscriptionData($tenant, $subscriptionId);
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
