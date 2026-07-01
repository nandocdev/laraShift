<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Listeners;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Payments\Events\PaymentApproved;
use App\Modules\Central\Provisioning\Models\Tenant;
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

            // Create or update subscription record
            Subscription::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'provider_subscription_id' => $result->gatewayReference,
                ],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'gateway' => 'paguelofacil',
                    'current_period_end' => now()->addMonth(), // Assuming monthly for this flow
                ]
            );

            // Update tenant's current plan
            $tenant->update(['plan_id' => $plan->slug]);

            Log::info('Subscription fulfilled via Payments engine', [
                'tenant' => $tenant->id,
                'plan' => $plan->slug,
                'payment_id' => $payment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fulfilling subscription from approved payment: '.$e->getMessage());
        }
    }
}
