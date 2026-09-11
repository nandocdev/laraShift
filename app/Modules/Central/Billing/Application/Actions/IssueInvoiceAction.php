<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Actions;

use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;

/**
 * Issues the receipt row for an approved payment. Idempotent per payment:
 * duplicate webhooks or double submits resolve to the same invoice.
 */
final readonly class IssueInvoiceAction
{
    public function execute(Payment $payment, ?string $subscriptionId = null): Invoice
    {
        try {
            return Invoice::firstOrCreate(
                ['payment_id' => $payment->id],
                [
                    'tenant_id' => $payment->tenant_id,
                    'subscription_id' => $subscriptionId ?? $payment->subscription_id,
                    'provider_invoice_id' => $payment->gateway_reference,
                    'amount_cents' => $payment->amount_cents,
                    'currency' => $payment->currency,
                    'status' => 'paid',
                    'issued_at' => now(),
                    'paid_at' => now(),
                ]
            );
        } catch (QueryException) {
            // Concurrent duplicate webhook deliveries racing on payment_id UNIQUE.
            return Invoice::where('payment_id', $payment->id)->firstOrFail();
        }
    }
}
