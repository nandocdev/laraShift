<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Listeners;

use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class FulfillSubscription
{
    /**
     * Handle the event.
     */
    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;
        $result = $event->result;
        $metadata = $payment->attempts()->latest()->first()?->payload ?? [];

        // Check if this payment was for a subscription
        if (($metadata['customFieldValues']['type'] ?? '') !== 'subscription') {
            return;
        }

        try {
            $tenantId = $metadata['customFieldValues']['tenant_id'] ?? $payment->tenant_id;
            $planId = $metadata['customFieldValues']['plan_id'] ?? null;

            if (! $planId) {
                Log::error('Subscription fulfillment failed: Plan ID missing in metadata', ['payment' => $payment->id]);

                return;
            }

            $tenant = Tenant::findOrFail($tenantId);
            $plan = Plan::findOrFail($planId);

            // Saved card reference (dLocal recurring). Stored so the
            // engine-managed recurring charges can reuse it each period.
            $cardId = $result->raw['card_id'] ?? null;
            $periodEnd = $this->periodEnd($plan);

            // Create or update subscription record
            Subscription::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'provider_subscription_id' => $result->gatewayReference,
                ],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'gateway' => $payment->gateway,
                    'current_period_end' => $periodEnd,
                    'next_payment_at' => $periodEnd,
                    'pm_card_id' => $cardId,
                    'failed_attempts' => 0,
                ]
            );

            // Update tenant's current plan and activate if pending
            $tenant->update([
                'plan_id' => $plan->slug,
                'status' => 'active',
            ]);

            Log::info('Subscription fulfilled via Payments engine', [
                'tenant' => $tenant->id,
                'plan' => $plan->slug,
                'payment_id' => $payment->id,
                'pm_card_id' => $cardId,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fulfilling subscription from approved payment: '.$e->getMessage());
        }
    }

    private function periodEnd(Plan $plan): CarbonInterface
    {
        $interval = $plan->interval ?? 'month';
        $count = max(1, (int) ($plan->interval_count ?? 1));

        return $interval === 'year' ? now()->addYears($count) : now()->addMonths($count);
    }
}
