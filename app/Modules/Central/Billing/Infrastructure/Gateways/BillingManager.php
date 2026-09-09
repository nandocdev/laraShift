<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\BillingProvider;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Support\Facades\Log;
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
        return $this->driver($this->gatewayFor($tenant));
    }

    /**
     * Resolve the gateway key for a tenant without trusting a concrete model.
     * Any TenantContract works: the gateway is looked up by tenant id.
     *
     * Note: billing_gateway lives in stancl's `data` JSON (it is not a
     * custom column), so it must be read through the model accessor —
     * a raw value('billing_gateway') query would hit the dead column.
     */
    public function gatewayFor(TenantContract $tenant): string
    {
        $model = $tenant instanceof Tenant ? $tenant : Tenant::find($tenant->getId());

        return $model?->billing_gateway ?? $this->getDefaultDriver();
    }

    /**
     * Single resolver for engine flows (checkout / direct / webhook verify).
     * Stripe has no PaymentGateway adapter (it flows through Cashier
     * controllers), so a stripe tenant falls back to the configured default
     * with a warning instead of silently charging through the wrong gateway.
     */
    public function paymentGatewayForTenant(TenantContract $tenant): PaymentGateway
    {
        return $this->paymentGatewayForTenantId((string) $tenant->getId());
    }

    public function paymentGatewayForTenantId(string $tenantId): PaymentGateway
    {
        // Read through the model: billing_gateway is a stancl `data` attribute.
        $gateway = Tenant::find($tenantId)?->billing_gateway ?? $this->getDefaultDriver();

        return match ($gateway) {
            'dlocal' => $this->container->make(DlocalGateway::class),
            'clave', 'paguelofacil' => $this->container->make(ClaveGateway::class),
            default => tap($this->container->make(ClaveGateway::class), function () use ($tenantId, $gateway) {
                Log::warning('BillingManager: no PaymentGateway adapter for gateway, using default', [
                    'tenant_id' => $tenantId,
                    'gateway' => $gateway,
                ]);
            }),
        };
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
