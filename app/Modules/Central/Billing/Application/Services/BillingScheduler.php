<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Services;

use App\Modules\Central\Billing\Application\Actions\GenerateRenewalCheckoutAction;
use App\Modules\Central\Billing\Application\Actions\IssueInvoiceAction;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Exceptions\RecurringBillingNotSupported;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Central\Provisioning\Actions\SuspendTenantForNonPayment;
use App\Modules\Platform\Contracts\Billing\BillingCapability;
use App\Modules\Platform\Contracts\Billing\BillingManager;
use App\Modules\Platform\Contracts\Billing\BillingProvider;
use App\Modules\Platform\Contracts\Billing\PaymentMethodType;
use App\Modules\Platform\Contracts\Billing\SubscriptionProvider;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Support\Facades\Log;

final readonly class BillingScheduler
{
    private const MIT_MAX_ATTEMPTS = 3;

    private const RENEWAL_LINK_DAYS_BEFORE = 7;

    private const LINK_SUSPEND_DAYS_AFTER_EXPIRY = 4;

    public function __construct(
        private BillingManager $billing,
        private SuspendTenantForNonPayment $suspend,
        private GenerateRenewalCheckoutAction $renewals,
        private PlanManager $plans,
        private IssueInvoiceAction $invoices,
    ) {}

    /**
     * billing:process-recurring — daily 04:00.
     * MIT track: charge silently. Link track: generate renewal checkouts.
     */
    public function processRecurring(): void
    {
        Subscription::whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    try {
                        $this->processSubscription($subscription);
                    } catch (\Throwable $e) {
                        Log::error('billing.scheduler_failed', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    /**
     * billing:reconcile — daily 03:00. Timeout-based PastDue ("Fuente 2"):
     * subscriptions past period end with no approved payment for the period.
     */
    public function reconcileTimeouts(): void
    {
        Subscription::whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->chunkById(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    if ($this->hasPaymentForCurrentPeriod($subscription)) {
                        continue;
                    }

                    if ($subscription->status !== SubscriptionStatus::PastDue) {
                        $subscription->update(['status' => SubscriptionStatus::PastDue]);

                        Log::info('billing.reconcile_past_due', ['subscription_id' => $subscription->id]);
                    }

                    $this->applyLinkDeadlines($subscription);
                }
            });
    }

    private function processSubscription(Subscription $subscription): void
    {
        $tenant = $this->resolveTenant((string) $subscription->tenant_id);
        $gateway = $this->billing->providerFor($tenant);

        if ($this->isLinkTrack($subscription, $gateway)) {
            $this->processLinkTrack($subscription);
            $this->applyLinkDeadlines($subscription->fresh());

            return;
        }

        $this->processMitTrack($subscription, $tenant, $gateway);
    }

    private function isLinkTrack(Subscription $subscription, BillingProvider $gateway): bool
    {
        // No native subscription support, or no saved card to charge
        // silently: the renewal requires customer action (checkout link).
        return ! $gateway->supports(BillingCapability::Subscriptions, PaymentMethodType::Card)
            || ! $subscription->pm_card_id;
    }

    private function processLinkTrack(Subscription $subscription): void
    {
        if ($subscription->current_period_end === null) {
            return;
        }

        $daysLeft = now()->diffInDays($subscription->current_period_end, false);

        if ($daysLeft <= self::RENEWAL_LINK_DAYS_BEFORE && ! $subscription->renewal_link_sent_at) {
            $this->renewals->execute($subscription->fresh());
        }
    }

    private function applyLinkDeadlines(Subscription $subscription): void
    {
        $expiresAt = $subscription->renewal_link_expires_at;

        if (! $expiresAt) {
            return;
        }

        if (now()->greaterThan($expiresAt) && $subscription->status === SubscriptionStatus::Active) {
            $subscription->update(['status' => SubscriptionStatus::PastDue]);
        }

        if (now()->greaterThan($expiresAt->copy()->addDays(self::LINK_SUSPEND_DAYS_AFTER_EXPIRY))) {
            $this->suspend->execute((string) $subscription->tenant_id, 'renewal_link_expired');
        }
    }

    private function processMitTrack(Subscription $subscription, TenantContract $tenant, BillingProvider $gateway): void
    {
        $dueAt = $subscription->next_payment_at ?? $subscription->current_period_end;

        if ($dueAt && now()->lessThan($dueAt) && $subscription->status === SubscriptionStatus::Active) {
            return; // Not due yet.
        }

        if (! $gateway instanceof SubscriptionProvider) {
            return;
        }

        $displayId = "sub_{$subscription->id}_".now()->format('Ym');

        $payment = Payment::firstOrCreate(
            ['tenant_id' => $subscription->tenant_id, 'display_id' => $displayId],
            [
                'slug' => 'pay_'.$displayId,
                'amount_cents' => $this->periodAmount($subscription),
                'currency' => 'USD',
                'status' => PaymentStatus::Pending,
                'gateway' => $gateway->identifier(),
                'subscription_id' => $subscription->id,
            ]
        );

        try {
            $ref = $gateway->chargeRecurring($tenant, $subscription->id, $payment->amount_cents);
        } catch (RecurringBillingNotSupported $e) {
            Log::info('billing.mit_not_supported', ['subscription_id' => $subscription->id]);

            return;
        } catch (\Throwable $e) {
            $this->registerMitFailure($subscription, $payment, $e->getMessage());

            return;
        }

        if ($ref->status !== 'approved') {
            $this->registerMitFailure($subscription, $payment, "gateway status: {$ref->status}");

            return;
        }

        $payment->update(['status' => PaymentStatus::Approved, 'gateway_reference' => $ref->providerPaymentId]);
        $this->invoices->execute($payment->refresh(), (string) $subscription->id);

        $periodEnd = ($subscription->current_period_end ?? now())->copy()->addMonth();
        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'failed_attempts' => 0,
            'current_period_start' => $subscription->current_period_end ?? now(),
            'current_period_end' => $periodEnd,
            'next_payment_at' => $periodEnd,
        ]);

        Log::info('billing.mit_charge_approved', ['subscription_id' => $subscription->id]);
    }

    private function registerMitFailure(Subscription $subscription, Payment $payment, string $reason): void
    {
        $payment->update(['status' => PaymentStatus::Declined]);

        $attempts = $subscription->failed_attempts + 1;

        $subscription->update([
            'failed_attempts' => $attempts,
            'status' => SubscriptionStatus::PastDue,
        ]);

        Log::warning('billing.mit_charge_failed', [
            'subscription_id' => $subscription->id,
            'attempt' => $attempts,
            'reason' => $reason,
        ]);

        if ($attempts >= self::MIT_MAX_ATTEMPTS) {
            $this->suspend->execute((string) $subscription->tenant_id, 'mit_attempts_exhausted');
        }
    }

    private function hasPaymentForCurrentPeriod(Subscription $subscription): bool
    {
        if ($subscription->current_period_start === null) {
            return false;
        }

        return Payment::where('tenant_id', $subscription->tenant_id)
            ->where('subscription_id', $subscription->id)
            ->where('status', PaymentStatus::Approved)
            ->where('created_at', '>=', $subscription->current_period_start)
            ->exists();
    }

    private function periodAmount(Subscription $subscription): int
    {
        if ($subscription->plan_id) {
            try {
                return $this->plans->findById($subscription->plan_id)->price_monthly;
            } catch (\Throwable) {
                // Fall through to default.
            }
        }

        return 0;
    }

    private function resolveTenant(string $tenantId): TenantContract
    {
        $model = config('tenancy.tenant_model');

        return $model::findOrFail($tenantId);
    }
}
