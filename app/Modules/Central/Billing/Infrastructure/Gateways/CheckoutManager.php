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
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class CheckoutManager
{
    public function __construct(
        private PaymentGateway $gateway,
        private ?BillingManager $billingManager = null,
    ) {}

    private function gatewayForTenant(string $tenantId): PaymentGateway
    {
        $tenant = Tenant::find($tenantId);

        if ($tenant && ($tenant->billing_gateway ?? null) === 'dlocal') {
            return app(DlocalGateway::class);
        }

        return $this->gateway;
    }

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
        $gateway = $this->gatewayForTenant($tenantId);

        return DB::transaction(function () use ($data, $tenantId, $apiKey, $gateway): CheckoutSession {
            $slug = $data->resolvedSlug();

            // Idempotency: same tenant+display_id must not create two pending payments
            // (double-click, frontend retry, or concurrent webhook). Unique constraint is the source of truth.
            $existing = Payment::where('tenant_id', $tenantId)
                ->where('display_id', $data->displayId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // Re-use existing pending payment and create a new attempt for this initiate
                $attempt = PaymentAttempt::create([
                    'tenant_id' => $tenantId,
                    'payment_id' => $existing->id,
                    'slug' => $slug,
                    'status' => 'initiated',
                    'payload' => $data->toArray(),
                ]);

                Log::info('CheckoutManager: reusing existing payment for idempotency', [
                    'tenant_id' => $tenantId,
                    'display_id' => $data->displayId,
                    'payment_id' => $existing->id,
                ]);

                // For reused payment, still honor DIRECT vs REDIRECT flow but without duplicating Payment row
                if ($gateway->supportsDirectPayment() && $data->token !== null) {
                    $result = $gateway->processDirectPayment($data, $apiKey);

                    $existing->update([
                        'status' => $result->status->value,
                        'gateway_reference' => $result->gatewayReference,
                        'authorization_code' => $result->authorizationCode,
                        'error_code' => $result->errorCode,
                        'error_message' => $result->errorMessage,
                    ]);

                    $attempt->update(['status' => $result->status->value]);

                    DB::afterCommit(function () use ($existing, $attempt, $result) {
                        CheckoutSessionCreated::dispatch($existing, $attempt);
                        match ($result->status) {
                            PaymentStatus::Approved => PaymentApproved::dispatch($existing, $result),
                            PaymentStatus::Declined => PaymentDeclined::dispatch($existing, $result),
                            default => null,
                        };
                    });

                    return new CheckoutSession(
                        payment: $existing,
                        attempt: $attempt,
                        checkoutUrl: null,
                        slug: $slug,
                        result: $result,
                    );
                }

                $checkoutUrl = $gateway->buildCheckoutUrl($data, $apiKey);

                DB::afterCommit(function () use ($existing, $attempt) {
                    CheckoutSessionCreated::dispatch($existing, $attempt);
                });

                return new CheckoutSession(
                    payment: $existing,
                    attempt: $attempt,
                    checkoutUrl: $checkoutUrl,
                    slug: $slug,
                );
            }

            try {
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
                    'gateway' => $gateway->identifier(),
                ]);
            } catch (QueryException $e) {
                // Race: another transaction inserted same tenant+display_id after our lock check (unique violation 23505)
                if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'unique')) {
                    $payment = Payment::where('tenant_id', $tenantId)->where('display_id', $data->displayId)->firstOrFail();
                    $attempt = PaymentAttempt::create([
                        'tenant_id' => $tenantId,
                        'payment_id' => $payment->id,
                        'slug' => $slug,
                        'status' => 'initiated',
                        'payload' => $data->toArray(),
                    ]);

                    Log::warning('CheckoutManager: unique violation recovered via existing payment', [
                        'tenant_id' => $tenantId,
                        'display_id' => $data->displayId,
                    ]);

                    $checkoutUrl = $gateway->buildCheckoutUrl($data, $apiKey);
                    DB::afterCommit(fn () => CheckoutSessionCreated::dispatch($payment, $attempt));

                    return new CheckoutSession(payment: $payment, attempt: $attempt, checkoutUrl: $checkoutUrl, slug: $slug);
                }

                throw $e;
            }

            $attempt = PaymentAttempt::create([
                'tenant_id' => $tenantId,
                'payment_id' => $payment->id,
                'slug' => $slug,
                'status' => 'initiated',
                'payload' => $data->toArray(),
            ]);

            // ── DIRECT flow (server-side charge, no redirect) ────────────────
            if ($gateway->supportsDirectPayment() && $data->token !== null) {
                $result = $gateway->processDirectPayment($data, $apiKey);

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
            $checkoutUrl = $gateway->buildCheckoutUrl($data, $apiKey);

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
