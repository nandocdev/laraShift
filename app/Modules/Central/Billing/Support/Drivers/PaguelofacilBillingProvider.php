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
        $client = new PagueloFacilClient();
        $plan = \App\Modules\Central\Billing\Models\Plan::findOrFail($planId);

        // Resolve the correct callback route
        $returnUrl = route('central.billing.paguelofacil.callback');

        return $client->generatePaymentLink([
            'amount' => $plan->amount,
            'description' => "Subscription to plan: {$plan->name}",
            'return_url' => $returnUrl,
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
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
