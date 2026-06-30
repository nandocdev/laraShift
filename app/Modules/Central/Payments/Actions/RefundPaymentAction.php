<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Actions;

use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\Services\PaymentVerifier;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\PaymentRefunded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class RefundPaymentAction
{
    public function __construct(
        private PaymentVerifier $verifier,
    ) {}

    /**
     * Process a manual refund for a payment.
     */
    public function execute(Payment $payment, Tenant $tenant, string $reason, string $refundedBy): array
    {
        return DB::transaction(function () use ($payment, $tenant, $reason, $refundedBy) {
            if ($payment->refunded_at) {
                throw new \RuntimeException('Payment has already been refunded.');
            }

            if ($payment->status !== 'approved') {
                throw new \RuntimeException('Only approved payments can be refunded.');
            }

            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_reason' => $reason,
                'refunded_by' => $refundedBy,
            ]);

            activity('payments')
                ->performedOn($payment)
                ->withProperties([
                    'refunded_by' => $refundedBy,
                    'amount' => $payment->amount,
                    'reason' => $reason,
                    'gateway_reference' => $payment->gateway_reference,
                ])
                ->log('payment_refunded');

            Log::info('Payment refunded', [
                'payment_id' => $payment->id,
                'tenant_id' => $tenant->id,
                'amount' => $payment->amount,
                'reason' => $reason,
                'refunded_by' => $refundedBy,
            ]);

            PaymentRefunded::dispatch(
                tenantId: $tenant->id,
                displayId: $payment->display_id,
                amount: $payment->amount,
                reason: $reason,
            );

            return [
                'payment_id' => $payment->id,
                'status' => 'refunded',
                'refunded_at' => $payment->refresh()->refunded_at,
            ];
        });
    }
}
