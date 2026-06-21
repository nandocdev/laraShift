<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support\Drivers;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Support\PagueloFacilClient;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Contracts\BillingProvider;
use App\Modules\Shared\Contracts\PaymentGatewayContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class InternalBillingProvider implements BillingProvider
{
    public function __construct(
        private PagueloFacilClient $client
    ) {}

    public function createCheckoutSession(Tenant $tenant, string $planId): string
    {
        $domain = $tenant->domains->first()?->domain ?? $tenant->id.'.'.config('tenancy.central_domain');

        return tenant_route($domain, 'tenant.billing.checkout.hosted', [
            'tenant_uuid' => $tenant->id,
            'plan_uuid' => $planId,
        ]);
    }

    public function cancelSubscription(Tenant $tenant, string $subscriptionId, bool $immediately = false): void
    {
        try {
            $this->client->cancelSubscription($subscriptionId);

            $subscription = $tenant->subscriptions()
                ->where('provider_subscription_id', $subscriptionId)
                ->first();

            if ($subscription) {
                if ($immediately) {
                    $subscription->update([
                        'status' => 'cancelled',
                        'ends_at' => now(),
                    ]);
                } else {
                    $subscription->update([
                        'ends_at' => $subscription->current_period_end ?? now()->addMonth(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            \Log::error("Failed to cancel PagueloFacil subscription: {$e->getMessage()}");
            throw $e;
        }
    }

    public function syncSubscription(Tenant $tenant): void
    {
        $subscription = $tenant->subscriptions()->latest()->first();

        if ($subscription && $subscription->provider_subscription_id) {
            try {
                $data = $this->getSubscriptionData($tenant, $subscription->provider_subscription_id);

                if ($data) {
                    $subscription->update([
                        'status' => strtolower($data['status'] ?? $subscription->status),
                        'current_period_end' => isset($data['nextPaymentDate']) ? Carbon::parse($data['nextPaymentDate']) : $subscription->current_period_end,
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error("Failed to sync PagueloFacil subscription for tenant {$tenant->id}: {$e->getMessage()}");
            }
        }
    }

    public function getSubscriptionData(Tenant $tenant, string $subscriptionId): ?array
    {
        try {
            $response = $this->client->getSubscription($subscriptionId);

            if ($response['success'] ?? false) {
                return $response['data'];
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('PagueloFacil getSubscriptionData Error: '.$e->getMessage());

            return null;
        }
    }

    public function getInvoices(Tenant $tenant): array
    {
        $gateway = app(PaymentGatewayContract::class);
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

    public function createTrialSubscription(Tenant $tenant, string $planId, ?string $paymentToken, bool $withCard): string
    {
        // Internal billing provider/PagueloFacil trial does not hit any gateway API,
        // it just registers a local trial subscription.
        return 'trial_'.Str::random(12);
    }
}
