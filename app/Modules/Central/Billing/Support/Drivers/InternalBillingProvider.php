<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support\Drivers;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Support\PagueloFacilClient;
use App\Modules\Central\Payments\Contracts\PaymentGateway;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Contracts\BillingProvider;

class InternalBillingProvider implements BillingProvider
{
    public function __construct(
        private PagueloFacilClient $client
    ) {}

    public function createCheckoutSession(Tenant $tenant, string $planId): string
    {
        return route('tenant.billing.checkout.hosted', [
            'tenant_uuid' => $tenant->id,
            'plan_uuid' => $planId,
        ]);
    }

    public function cancelSubscription(Tenant $tenant, string $subscriptionId, bool $immediately = false): void
    {
        try {
            $this->client->cancelSubscription($subscriptionId);
            
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
        // Implement status sync
    }

    public function getInvoices(Tenant $tenant): array
    {
        $gateway = app(PaymentGateway::class);
        $apiKey = config("payments.{$gateway->identifier()}.api_key") 
               ?? config("payments.{$gateway->identifier()}.login");

        $transactions = $gateway->listTransactions((string) $apiKey, [
            'PARM_1' => $tenant->id, // For Clave
            'tenant_id' => $tenant->id, // For dLocal fallback
        ]);

        // Map gateway transactions to standard Invoice format if needed, 
        // or just return the raw data for now. 
        // The SyncInvoicesAction will handle the persistence.
        return $transactions;
    }
}
