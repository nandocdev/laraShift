<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Services;

use App\Modules\Central\Payments\DTOs\PaymentResultData;
use App\Modules\Central\Payments\Enums\PaymentContext;
use App\Modules\Central\Payments\Enums\PaymentStatus;
use App\Modules\Central\Payments\Events\PaymentWebhookReceived;
use App\Modules\Central\Payments\Exceptions\WebhookVerificationException;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\Models\PaymentAttempt;
use App\Modules\Central\Payments\Models\PaymentWebhook;
use App\Modules\Shared\Contracts\PaymentGatewayContract;
use App\Modules\Shared\Events\PaymentCompleted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class PaymentVerifier
{
    public function __construct(
        private PaymentGatewayContract $gateway,
        private PaymentHandlerDispatcher $dispatcher,
    ) {}

    /**
     * Process an inbound webhook.
     * Idempotent: duplicate gateway references are silently ignored.
     *
     * @throws WebhookVerificationException if signature is invalid
     */
    public function handleWebhook(
        string $rawPayload,
        string $signature,
        string $webhookSecret,
        string $tenantId,
    ): void {
        if (! $this->gateway->verifyWebhook($rawPayload, $signature, $webhookSecret)) {
            Log::warning('ClaveGateway: webhook signature mismatch', [
                'tenant_id' => $tenantId,
            ]);

            throw new WebhookVerificationException('Invalid webhook signature');
        }

        $payload = json_decode($rawPayload, true);
        $result = $this->gateway->parseWebhookPayload($payload);

        $lockKey = "webhook_processing_{$tenantId}_{$result->gatewayReference}";
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            Log::info('Webhook is already being processed', [
                'gateway_reference' => $result->gatewayReference,
                'tenant_id' => $tenantId,
            ]);

            return;
        }

        try {
            DB::transaction(function () use ($result, $rawPayload, $tenantId): void {
                // Security: Ensure the payment actually belongs to the resolved tenant
                // before recording anything to prevent cross-tenant log pollution.
                $paymentExists = Payment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('display_id', $result->displayId)
                    ->exists();

                if (! $paymentExists) {
                    Log::warning('ClaveGateway: Webhook received for non-existent payment or tenant mismatch', [
                        'tenant_id' => $tenantId,
                        'display_id' => $result->displayId,
                    ]);

                    return;
                }

                $this->recordWebhook($result, $rawPayload, $tenantId);
                $this->reconcilePayment($result, $tenantId);
            });
        } finally {
            $lock->release();
        }
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function recordWebhook(PaymentResultData $result, string $rawPayload, string $tenantId): void
    {
        // Idempotency guard: same gateway reference = already processed
        $exists = PaymentWebhook::where('tenant_id', $tenantId)
            ->where('gateway_reference', $result->gatewayReference)
            ->exists();

        if ($exists) {
            Log::info('ClaveGateway: duplicate webhook ignored', [
                'gateway_reference' => $result->gatewayReference,
                'tenant_id' => $tenantId,
            ]);

            return;
        }

        PaymentWebhook::create([
            'tenant_id' => $tenantId,
            'gateway_reference' => $result->gatewayReference,
            'display_id' => $result->displayId,
            'status' => $result->status->value,
            'amount' => $result->amount,
            'gateway_code' => $result->gatewayCode,
            'authorization_code' => $result->authorizationCode,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'raw_payload' => $this->maskSensitiveData($rawPayload),
        ]);

        PaymentWebhookReceived::dispatch($result, $tenantId);
    }

    private function maskSensitiveData(string $rawPayload): string
    {
        $payload = json_decode($rawPayload, true);
        if (! is_array($payload)) {
            return $rawPayload;
        }

        $sensitiveFields = ['cardNumber', 'cvv', 'card_number', 'card_cvv', 'password'];

        array_walk_recursive($payload, function (&$value, $key) use ($sensitiveFields) {
            if (in_array($key, $sensitiveFields, true)) {
                $value = '****';
            }
        });

        return json_encode($payload);
    }

    private function reconcilePayment(PaymentResultData $result, string $tenantId): void
    {
        $payment = Payment::where('tenant_id', $tenantId)
            ->where('display_id', $result->displayId)
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            Log::warning('ClaveGateway: no payment found for webhook', [
                'display_id' => $result->displayId,
                'tenant_id' => $tenantId,
            ]);

            return;
        }

        // Never regress a terminal status
        if (PaymentStatus::from($payment->status)->isTerminal()) {
            return;
        }

        if ($result->status === PaymentStatus::Approved && $payment->amount > $result->amount) {
            Log::alert('Monto insuficiente reportado por pasarela', ['payment' => $payment->id]);
            $payment->update([
                'status' => PaymentStatus::PartialPayment->value,
                'gateway_reference' => $result->gatewayReference,
                'authorization_code' => $result->authorizationCode,
                'error_code' => 'INSUFFICIENT_AMOUNT',
            ]);

            PaymentAttempt::where('tenant_id', $tenantId)
                ->where('payment_id', $payment->id)
                ->whereIn('status', ['initiated', 'pending'])
                ->update(['status' => PaymentStatus::PartialPayment->value]);

            return;
        }

        $payment->update([
            'status' => $result->status->value,
            'gateway_reference' => $result->gatewayReference,
            'authorization_code' => $result->authorizationCode,
            'error_code' => $result->errorCode,
        ]);

        PaymentAttempt::where('tenant_id', $tenantId)
            ->where('payment_id', $payment->id)
            ->whereIn('status', ['initiated', 'pending'])
            ->update(['status' => $result->status->value]);

        match ($result->status) {
            PaymentStatus::Approved,
            PaymentStatus::Declined => DB::afterCommit(function () use ($payment, $result, $tenantId) {
                $context = $payment->context ?? PaymentContext::Subscription;
                $metadata = $payment->attempts()->latest()->first()?->payload ?? [];

                $this->dispatcher->dispatch(
                    context: $context,
                    tenantId: $tenantId,
                    displayId: $result->displayId,
                    amount: $result->amount,
                    success: $result->status === PaymentStatus::Approved,
                    metadata: array_merge($metadata, [
                        'gateway_reference' => $result->gatewayReference,
                        'gateway' => $this->gateway->identifier(),
                        'error_message' => $result->errorMessage,
                    ]),
                );

                PaymentCompleted::dispatch(
                    tenantId: $tenantId,
                    displayId: $result->displayId,
                    context: $context,
                    status: $result->status,
                    amount: $result->amount,
                    gatewayReference: $result->gatewayReference,
                );
            }),
            default => null,
        };
    }
}
