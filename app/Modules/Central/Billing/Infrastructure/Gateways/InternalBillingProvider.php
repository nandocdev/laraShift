<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Infrastructure\Gateways\PagueloFacilClient;
use App\Modules\Central\Billing\Infrastructure\Gateways\PaymentGateway;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\BillingProvider;
use App\Modules\Platform\Contracts\TenantContract;

class InternalBillingProvider implements BillingProvider
{
    public function __construct(
        private PagueloFacilClient $client
    ) {}

    public function createCheckoutSession(TenantContract $tenant, string $planId): string
    {
        return route('tenant.billing.checkout.hosted', [
            'tenant_uuid' => $tenant->getId(),
            'plan_uuid' => $planId,
        ]);
    }

    public function cancelSubscription(TenantContract $tenant, string $subscriptionId, bool $immediately = false): void
    {
        try {
            $this->client->cancelSubscription($subscriptionId);
            
            if ($tenant instanceof Tenant) {
                $tenant->subscriptions()
                    ->where('provider_subscription_id', $subscriptionId)
                    ->update(['status' => 'cancelled']);
            }
                
        } catch (\Exception $e) {
            \Log::error("Failed to cancel PagueloFacil subscription: {$e->getMessage()}");
            throw $e;
        }
    }

    public function syncSubscription(TenantContract $tenant): void
    {
        if (! $tenant instanceof Tenant) {
            return;
        }

        $subscription = $tenant->subscriptions()->latest()->first();

        if ($subscription && $subscription->provider_subscription_id) {
            try {
                $data = $this->getSubscriptionData($tenant, $subscription->provider_subscription_id);

                if ($data) {
                    $subscription->update([
                        'status' => strtolower($data['status'] ?? $subscription->status),
                        'current_period_end' => isset($data['nextPaymentDate']) ? \Illuminate\Support\Carbon::parse($data['nextPaymentDate']) : $subscription->current_period_end,
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error("Failed to sync PagueloFacil subscription for tenant {$tenant->getId()}: {$e->getMessage()}");
            }
        }
    }

    public function getSubscriptionData(TenantContract $tenant, string $subscriptionId): ?array
    {
        try {
            $response = $this->client->getSubscription($subscriptionId);
            
            if ($response['success'] ?? false) {
                return $response['data'];
            }
            
            return null;
        } catch (\Exception $e) {
            \Log::error("PagueloFacil getSubscriptionData Error: " . $e->getMessage());
            return null;
        }
    }

    public function getInvoices(TenantContract $tenant): array
    {
        $gateway = app(PaymentGateway::class);
        $apiKey = config("payments.{$gateway->identifier()}.api_key") 
               ?? config("payments.{$gateway->identifier()}.login");

        $transactions = $gateway->listTransactions((string) $apiKey, [
            'PARM_1' => (string) $tenant->getId(), // For Clave
            'tenant_id' => (string) $tenant->getId(), // For dLocal fallback
        ]);

        // Map gateway transactions to standard Invoice format if needed, 
        // or just return the raw data for now. 
        // The SyncInvoices will handle the persistence.
        return $transactions;
    }
}
