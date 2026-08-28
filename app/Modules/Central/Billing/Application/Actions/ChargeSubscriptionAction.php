<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Application\DTO\PaymentResultData;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentAttempt;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Billing\Infrastructure\Gateways\BillingManager;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Events\TenantSuspendedByDunning;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes a single engine-managed recurring charge for a subscription.
 *
 * Supports subscriptions whose gateway manages recurrence locally (dLocal via
 * a saved card). Gateways that manage recurrence on their own side (Clave) are
 * skipped here and kept in sync by the reconciliation engine.
 */
final readonly class ChargeSubscriptionAction
{
    public const MAX_FAILED_ATTEMPTS = 3;

    public function execute(string $subscriptionId): void
    {
        $subscription = Subscription::find($subscriptionId);

        if (! $subscription || $subscription->status !== 'active') {
            return;
        }

        if ($subscription->gateway !== 'dlocal') {
            Log::info("Subscription {$subscriptionId} is gateway-managed; engine charge skipped.");

            return;
        }

        $tenant = $subscription->tenant;
        $plan = $subscription->plan;

        if (! $tenant || ! $plan) {
            Log::warning("Recurring charge skipped: tenant or plan missing for subscription {$subscriptionId}");

            return;
        }

        // Idempotency guard: never double-charge a period that already has an
        // approved payment. Declined attempts do NOT block retries.
        // Use lockForUpdate inside transaction to close race between exists() and Payment::create in handleSuccess.
        $displayId = $this->displayId($subscription, now());

        $alreadyCharged = DB::transaction(function () use ($displayId) {
            return Payment::where('display_id', $displayId)
                ->where('status', PaymentStatus::Approved->value)
                ->lockForUpdate()
                ->exists();
        });

        if ($alreadyCharged) {
            return;
        }

        $amount = (int) $plan->price_monthly->getAmount();

        if ($amount <= 0) {
            $this->rollPeriod($subscription, $plan);

            return;
        }

        // Resolve gateway via BillingManager for tenant (not hardcoded helper) — B005 alignment
        $gateway = app(BillingManager::class)->forTenant($tenant);
        // For engine-managed dlocal, we still use DlocalGateway directly but via container resolved for tenant
        $result = app(DlocalGateway::class)->chargeSubscription($subscription, $amount);

        match ($result->status) {
            PaymentStatus::Approved => $this->handleSuccess($tenant, $subscription, $plan, $result),
            PaymentStatus::Declined => $this->handleDecline($tenant, $subscription, $result),
            default => $this->scheduleRetry($subscription),
        };
    }

    private function handleSuccess(Tenant $tenant, Subscription $subscription, Plan $plan, PaymentResultData $result): void
    {
        $slug = $this->attemptSlug($result->displayId);

        try {
            DB::transaction(function () use ($tenant, $subscription, $plan, $result, $slug) {
                // Re-check idempotency inside transaction with lock to prevent double Invoice/Payment
                $exists = Payment::where('tenant_id', $tenant->id)
                    ->where('display_id', $result->displayId)
                    ->where('status', PaymentStatus::Approved->value)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    Log::info('ChargeSubscriptionAction: duplicate approved payment ignored', [
                        'tenant_id' => $tenant->id,
                        'display_id' => $result->displayId,
                    ]);

                    return;
                }

                $payment = Payment::create([
                    'tenant_id' => $tenant->id,
                    'display_id' => $result->displayId,
                    'slug' => $slug,
                    'amount' => $result->amount,
                    'tax_amount' => 0.0,
                    'discount' => 0.0,
                    'description' => __('Recurring charge for :plan', ['plan' => $plan->slug]),
                    'email' => $tenant->email,
                    'currency' => 'USD',
                    'status' => PaymentStatus::Approved->value,
                    'gateway' => 'dlocal',
                    'gateway_reference' => $result->gatewayReference,
                ]);

                PaymentAttempt::create([
                    'tenant_id' => $tenant->id,
                    'payment_id' => $payment->id,
                    'slug' => $slug,
                    'status' => PaymentStatus::Approved->value,
                    'payload' => ['type' => 'recurring', 'subscription_id' => $subscription->id, 'plan_id' => $plan->id],
                ]);

                Invoice::create([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'provider_invoice_id' => $result->gatewayReference,
                    'amount' => $result->amount,
                    'currency' => 'USD',
                    'status' => 'paid',
                    'issued_at' => now(),
                ]);

                $this->rollPeriod($subscription, $plan);
                $subscription->update(['failed_attempts' => 0]);

                activity('billing')
                    ->performedOn($tenant)
                    ->withProperties(['subscription_id' => $subscription->id, 'amount' => $result->amount])
                    ->log('subscription_renewed');
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'unique')) {
                Log::warning('ChargeSubscriptionAction: unique violation on handleSuccess — already processed', [
                    'tenant_id' => $tenant->id,
                    'display_id' => $result->displayId,
                ]);

                return;
            }

            throw $e;
        }
    }

    private function handleDecline(Tenant $tenant, Subscription $subscription, PaymentResultData $result): void
    {
        $slug = $this->attemptSlug($result->displayId);
        // Declined attempts must not collide on unique(tenant_id, display_id) — suffix per attempt
        $declinedDisplayId = $result->displayId.'_retry_'.($subscription->failed_attempts + 1).'_'.Str::lower(Str::random(4));

        DB::transaction(function () use ($tenant, $subscription, $result, $slug, $declinedDisplayId) {
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'display_id' => $declinedDisplayId,
                'slug' => $slug,
                'amount' => $result->amount,
                'tax_amount' => 0.0,
                'discount' => 0.0,
                'description' => 'Recurring charge declined',
                'email' => $tenant->email,
                'currency' => 'USD',
                'status' => PaymentStatus::Declined->value,
                'gateway' => 'dlocal',
                'gateway_reference' => $result->gatewayReference,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
            ]);

            PaymentAttempt::create([
                'tenant_id' => $tenant->id,
                'payment_id' => $payment->id,
                'slug' => $slug,
                'status' => PaymentStatus::Declined->value,
                'payload' => ['type' => 'recurring', 'subscription_id' => $subscription->id],
            ]);

            $failedAttempts = $subscription->failed_attempts + 1;
            $subscription->update([
                'failed_attempts' => $failedAttempts,
                'next_payment_at' => now()->addDay(),
            ]);

            activity('billing')
                ->performedOn($tenant)
                ->withProperties(['subscription_id' => $subscription->id, 'attempts' => $failedAttempts])
                ->log('subscription_renewal_failed');

            if ($failedAttempts >= self::MAX_FAILED_ATTEMPTS) {
                $this->suspendAfterDunning($tenant, $subscription);
            }
        });
    }

    private function suspendAfterDunning(Tenant $tenant, Subscription $subscription): void
    {
        $tenant->update(['status' => 'suspended', 'suspended_at' => now()]);

        TenantSuspendedByDunning::dispatch($tenant->id, $subscription->id);

        activity('billing')
            ->performedOn($tenant)
            ->withProperties(['subscription_id' => $subscription->id, 'attempts' => $subscription->failed_attempts])
            ->log('tenant_suspended_by_dunning');
    }

    private function scheduleRetry(Subscription $subscription): void
    {
        $subscription->update(['next_payment_at' => now()->addHours(12)]);
    }

    private function rollPeriod(Subscription $subscription, Plan $plan): void
    {
        $next = $this->nextPeriodEnd($plan);

        $subscription->update([
            'current_period_end' => $next,
            'next_payment_at' => $next,
        ]);
    }

    private function nextPeriodEnd(Plan $plan): CarbonInterface
    {
        $interval = $plan->interval ?? 'month';
        $count = max(1, (int) ($plan->interval_count ?? 1));

        return $interval === 'year' ? now()->addYears($count) : now()->addMonths($count);
    }

    private function displayId(Subscription $subscription, CarbonInterface $date): string
    {
        return 'sub_'.$subscription->id.'_'.$date->format('Y-m');
    }

    /**
     * Unique slug per attempt (payments.slug is unique). display_id stays
     * period-based for the approved/idempotency guard.
     */
    private function attemptSlug(string $displayId): string
    {
        return $displayId.'_'.Str::lower(Str::random(8));
    }
}
