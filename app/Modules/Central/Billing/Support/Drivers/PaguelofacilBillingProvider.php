<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support\Drivers;

use App\Modules\Central\Billing\Support\PagueloFacilClient;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Contracts\BillingProvider;

class PaguelofacilBillingProvider implements BillingProvider
{
    public function createCheckoutSession(Tenant $tenant, string $planId): string
    {
        // Resolve the correct route based on context (Tenant vs Central)
        $routeName = \Route::has('tenant.billing.checkout.paguelofacil') 
            ? 'tenant.billing.checkout.paguelofacil' 
            : 'central.billing.checkout.paguelofacil';

        return route($routeName, [
            'tenant_uuid' => $tenant->id,
            'plan_uuid' => $planId,
        ]);
    }

    public function cancelSubscription(Tenant $tenant, string $subscriptionId, bool $immediately = false): void
    {
        $client = new PagueloFacilClient();
        
        try {
            $client->cancelSubscription($subscriptionId);
            
            // Local update of subscription status
            $tenant->subscriptions()
                ->where('provider_subscription_id', $subscriptionId)
                ->update(['status' => 'cancelled']);
                
        } catch (\Exception $e) {
            \Log::error("Failed to cancel PagueloFacil subscription: {$e->getMessage()}");
            throw $e;
        }
    }

    public function syncSubscription(Tenant $tenant): void
    {
        // Implement if PagueloFacil provides a status sync endpoint
    }

    public function getInvoices(Tenant $tenant): array
    {
        // Implement if PagueloFacil provides an invoice list endpoint
        return [];
    }
}
