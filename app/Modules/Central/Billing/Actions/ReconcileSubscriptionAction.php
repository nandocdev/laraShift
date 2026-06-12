<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Support\BillingManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ReconcileSubscriptionAction
{
    public function execute(Tenant $tenant): void
    {
        $localSubscription = $tenant->subscriptions()->latest()->first();

        if (! $localSubscription) {
            return;
        }

        $subscriptionId = $localSubscription->stripe_id ?? $localSubscription->provider_subscription_id;

        if (! $subscriptionId) {
            return;
        }

        try {
            $gatewayData = app(BillingManager::class)->getSubscriptionData($tenant, $subscriptionId);

            if (! $gatewayData) {
                Log::warning("Reconciliation: No gateway data found for tenant {$tenant->id} subscription {$subscriptionId}");
                return;
            }

            $this->reconcile($tenant, $localSubscription, $gatewayData);

        } catch (\Exception $e) {
            Log::error("Reconciliation Error for tenant {$tenant->id}: " . $e->getMessage());
        }
    }

    private function reconcile(Tenant $tenant, $localSubscription, array $gatewayData): void
    {
        $gatewayStatus = strtolower((string) ($gatewayData['status'] ?? ''));
        $localStatus = strtolower((string) $localSubscription->status);

        if ($gatewayStatus === '') {
            return;
        }

        // Standardize status for comparison if needed
        // Stripe uses: trialing, active, past_due, canceled, unpaid, paused
        // Our system should align or map them
        
        if ($gatewayStatus !== $localStatus) {
            DB::transaction(function () use ($tenant, $localSubscription, $gatewayStatus) {
                $localSubscription->update(['status' => $gatewayStatus]);

                // Update tenant status if subscription is no longer active
                $inactiveStatuses = ['canceled', 'unpaid', 'cancelled', 'expired'];
                
                if (in_array($gatewayStatus, $inactiveStatuses) && $tenant->status === 'active') {
                    $tenant->update(['status' => 'suspended']);
                }

                if (!in_array($gatewayStatus, $inactiveStatuses) && $tenant->status === 'suspended') {
                    $tenant->update(['status' => 'active']);
                }

                activity('billing')
                    ->performedOn($tenant)
                    ->withProperties([
                        'old_status' => $localSubscription->getOriginal('status'),
                        'new_status' => $gatewayStatus,
                        'source' => 'reconciliation_engine'
                    ])
                    ->log('subscription_reconciled');
            });

            Log::info("Reconciliation: Corrected status for tenant {$tenant->id} from {$localStatus} to {$gatewayStatus}");
        }
    }
}
