<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentAttempt;
use App\Modules\Central\Billing\Domain\Models\PaymentReference;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Platform\Contracts\Billing\BillingManager;
use App\Modules\Platform\Contracts\Billing\DirectPaymentData;
use App\Modules\Platform\Contracts\Billing\PaymentProvider;
use App\Modules\Platform\Contracts\Billing\ProviderPaymentRef;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class ChargeDirectAction
{
    public function __construct(private BillingManager $billing) {}

    /**
     * Server-side direct charge (Smart Fields token). Idempotent on orderId.
     * On approved subscription-intent charges, stores the saved card id.
     */
    public function execute(DirectPaymentData $payment): ProviderPaymentRef
    {
        $model = config('tenancy.tenant_model');
        $tenant = $model::findOrFail($payment->tenantId);

        $gateway = $this->billing->providerFor($tenant);

        if (! $gateway instanceof PaymentProvider) {
            throw new RuntimeException('Billing provider does not support direct payments.');
        }

        $tenantId = (string) $tenant->getId();

        $record = DB::transaction(function () use ($tenantId, $payment) {
            $existing = Payment::where('tenant_id', $tenantId)
                ->where('display_id', $payment->orderId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            try {
                return Payment::create([
                    'tenant_id' => $tenantId,
                    'slug' => 'pay_'.$payment->orderId,
                    'display_id' => $payment->orderId,
                    'amount_cents' => $payment->amountCents,
                    'currency' => $payment->currency,
                    'status' => PaymentStatus::Pending,
                    'gateway' => $payment->gateway,
                ]);
            } catch (QueryException $e) {
                Log::info('billing.direct_race_recovered', ['tenant_id' => $tenantId, 'order_id' => $payment->orderId]);

                return Payment::where('tenant_id', $tenantId)->where('display_id', $payment->orderId)->firstOrFail();
            }
        });

        $ref = $gateway->chargeDirect($payment);

        $record->update([
            'status' => $ref->status === 'approved' ? PaymentStatus::Approved : PaymentStatus::Declined,
            'gateway_reference' => $ref->providerPaymentId,
        ]);

        PaymentAttempt::create([
            'tenant_id' => $tenantId,
            'payment_id' => $record->id,
            'slug' => 'att_'.$payment->orderId.'_'.now()->format('His'),
            'status' => $ref->status,
            'payload' => ['provider_payment_id' => $ref->providerPaymentId],
        ]);

        PaymentReference::firstOrCreate(
            ['external_reference' => $ref->providerPaymentId],
            ['order_id' => $payment->orderId, 'context' => PaymentReference::CONTEXT_ORDER, 'tenant_id' => $tenantId]
        );

        if ($ref->status === 'approved' && $payment->subscriptionId && $ref->cardId) {
            Subscription::where('id', $payment->subscriptionId)
                ->where('tenant_id', $tenantId)
                ->update(['pm_card_id' => $ref->cardId]);
        }

        return $ref;
    }
}
