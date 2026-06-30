<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Services;

use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\Events\CheckoutSessionCreated;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\Models\PaymentAttempt;
use App\Modules\Shared\Contracts\PaymentGatewayContract;
use Illuminate\Support\Facades\DB;

final readonly class CheckoutManager
{
    public function __construct(
        private PaymentGatewayContract $gateway,
    ) {}

    /**
     * Create a new Payment record and return a ready-to-embed checkout URL.
     *
     * Wraps in a transaction: if anything fails, no partial records survive.
     */
    public function initiate(PaymentData $data, string $tenantId, string $apiKey): CheckoutSession
    {
        return DB::transaction(function () use ($data, $tenantId, $apiKey): CheckoutSession {
            $slug = $data->resolvedSlug();

            $payment = Payment::create([
                'tenant_id' => $tenantId,
                'context' => $data->context->value,
                'display_id' => $data->displayId,
                'slug' => $slug,
                'amount' => $data->amount,
                'tax_amount' => $data->taxAmount,
                'discount' => $data->discount,
                'description' => $data->description,
                'email' => $data->email,
                'currency' => 'USD',
                'status' => 'pending',
                'gateway' => $this->gateway->identifier(),
            ]);

            $attempt = PaymentAttempt::create([
                'tenant_id' => $tenantId,
                'payment_id' => $payment->id,
                'slug' => $slug,
                'status' => 'initiated',
                'payload' => $data->toArray(),
            ]);

            $checkoutUrl = $this->gateway->buildCheckoutUrl($data, $apiKey);

            DB::afterCommit(function () use ($payment, $attempt) {
                CheckoutSessionCreated::dispatch($payment, $attempt);
            });

            return new CheckoutSession(
                payment: $payment,
                attempt: $attempt,
                checkoutUrl: $checkoutUrl,
                slug: $slug,
            );
        });
    }
}
