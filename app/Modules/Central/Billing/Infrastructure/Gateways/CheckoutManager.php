<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Billing\Application\DTO\PaymentData;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Events\CheckoutSessionCreated;
use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Domain\Events\PaymentDeclined;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

final readonly class CheckoutManager
{
    public function __construct(
        private PaymentGateway $gateway,
    ) {}

    /**
     * Create a new Payment record and return a ready-to-embed checkout URL.
     *
     * For gateways supporting a DIRECT flow (e.g. dLocal Smart Fields) the
     * payment is processed synchronously and the CheckoutSession carries the
     * result instead of a redirect URL.
     *
     * Wraps in a transaction: if anything fails, no partial records survive.
     */
    public function initiate(PaymentData $data, string $tenantId, string $apiKey): CheckoutSession
    {
        return DB::transaction(function () use ($data, $tenantId, $apiKey): CheckoutSession {
            $slug = $data->resolvedSlug();

            $payment = Payment::create([
                'tenant_id' => $tenantId,
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

            // ── DIRECT flow (server-side charge, no redirect) ────────────────
            if ($this->gateway->supportsDirectPayment() && $data->token !== null) {
                $result = $this->gateway->processDirectPayment($data, $apiKey);

                $payment->update([
                    'status' => $result->status->value,
                    'gateway_reference' => $result->gatewayReference,
                    'authorization_code' => $result->authorizationCode,
                    'error_code' => $result->errorCode,
                    'error_message' => $result->errorMessage,
                ]);

                $attempt->update(['status' => $result->status->value]);

                DB::afterCommit(function () use ($payment, $attempt, $result) {
                    CheckoutSessionCreated::dispatch($payment, $attempt);

                    match ($result->status) {
                        PaymentStatus::Approved => PaymentApproved::dispatch($payment, $result),
                        PaymentStatus::Declined => PaymentDeclined::dispatch($payment, $result),
                        default => null,
                    };
                });

                return new CheckoutSession(
                    payment: $payment,
                    attempt: $attempt,
                    checkoutUrl: null,
                    slug: $slug,
                    result: $result,
                );
            }

            // ── REDIRECT flow (hosted checkout URL) ──────────────────────────
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
