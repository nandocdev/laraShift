<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Listeners;

use App\Modules\Central\Billing\Application\Actions\IssueInvoiceAction;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Central\Provisioning\Actions\MarkTenantBillingActive;
use Illuminate\Support\Facades\Log;

class FulfillSubscription
{
    public function __construct(
        private PlanManager $plans,
        private MarkTenantBillingActive $activate,
        private IssueInvoiceAction $invoices,
    ) {}

    /**
     * The ONLY point where a Subscription is created for a Clave tenant.
     * Never via subscribe().
     */
    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;
        $metadata = $payment->provider_metadata ?? [];
        $planSlug = $metadata['plan_slug'] ?? null;

        if (! is_string($planSlug) || $planSlug === '') {
            Log::warning('billing.fulfill_missing_plan', ['payment_id' => $payment->id]);

            return;
        }

        try {
            $plan = $this->plans->find($planSlug);
        } catch (\Throwable $e) {
            Log::error('billing.fulfill_plan_not_found', ['payment_id' => $payment->id, 'plan_slug' => $planSlug]);

            return;
        }

        $periodEnd = $plan->interval === 'year' ? now()->addYear() : now()->addMonth();

        $subscription = Subscription::updateOrCreate(
            // Clave/dLocal redirect have no provider-side subscription: one row
            // per tenant+gateway. (MIT gateways match on provider_subscription_id — Fase 3.)
            ['tenant_id' => $payment->tenant_id, 'gateway' => $payment->gateway],
            [
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'next_payment_at' => $periodEnd,
                'failed_attempts' => 0,
            ]
        );

        $this->invoices->execute($payment, (string) $subscription->id);

        // Saved card from a direct/MIT charge (dLocal): enables chargeRecurring.
        $cardId = $event->event->raw['card_id'] ?? null;

        if (is_string($cardId) && $cardId !== '') {
            Subscription::where('tenant_id', $payment->tenant_id)
                ->where('gateway', $payment->gateway)
                ->update(['pm_card_id' => $cardId]);
        }

        $this->activate->execute((string) $payment->tenant_id, $plan->slug);

        Log::info('billing.fulfilled', ['payment_id' => $payment->id, 'plan' => $plan->slug]);
    }
}
