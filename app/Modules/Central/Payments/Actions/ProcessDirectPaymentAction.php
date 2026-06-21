<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Actions;

use App\Modules\Shared\Contracts\PaymentGatewayContract;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\DTOs\PaymentResultData;
use App\Modules\Central\Payments\Enums\PaymentStatus;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\Models\PaymentAttempt;
use App\Modules\Central\Payments\Services\PaymentHandlerDispatcher;
use App\Modules\Shared\Events\PaymentCompleted;
use Illuminate\Support\Facades\DB;

/**
 * [REALIZACIÓN DE CASO DE USO - RUP]
 * Caso de Uso: Procesar Pago Directo (Smart Fields/Token)
 */
final readonly class ProcessDirectPaymentAction
{
    public function __construct(
        private PaymentGatewayContract $gateway,
        private PaymentHandlerDispatcher $dispatcher,
    ) {}

    public function execute(PaymentData $data, string $token, ?bool $saveCard = false): array
    {
        return DB::transaction(function () use ($data, $token): array {
            $slug = $data->resolvedSlug();

            // Create Payment record
            $payment = Payment::create([
                'tenant_id' => $data->tenantId,
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

            // Create Attempt
            $attempt = PaymentAttempt::create([
                'tenant_id' => $data->tenantId,
                'payment_id' => $payment->id,
                'slug' => $slug,
                'status' => 'processing',
                'payload' => array_merge($data->toArray(), ['token' => '***']),
            ]);

            $apiKey = config("payments.{$this->gateway->identifier()}.login");
            
            // Process via Gateway
            $result = $this->gateway->processDirectPayment($data, (string) $apiKey, $token);

            // Update records
            $payment->update(['status' => $result->status->value]);
            $attempt->update([
                'status' => $result->status->isSuccessful() ? 'approved' : 'failed',
                'gateway_reference' => $result->gatewayReference,
                'response_payload' => $result->raw,
            ]);

            // Dispatch to context-aware handler
            $metadata = array_merge($data->customFieldValues, [
                'gateway_reference' => $result->gatewayReference,
                'gateway'           => $this->gateway->identifier(),
                'error_message'     => $result->errorMessage,
            ]);

            DB::afterCommit(function () use ($data, $result, $metadata) {
                $this->dispatcher->dispatch(
                    context: $data->context,
                    tenantId: $data->tenantId,
                    displayId: $data->displayId,
                    amount: $result->amount,
                    success: $result->status->isSuccessful(),
                    metadata: $metadata,
                );

                PaymentCompleted::dispatch(
                    tenantId: $data->tenantId,
                    displayId: $data->displayId,
                    context: $data->context,
                    status: $result->status,
                    amount: $result->amount,
                    gatewayReference: $result->gatewayReference,
                );
            });

            return [
                'success' => $result->status->isSuccessful(),
                'displayId' => $data->displayId,
                'message' => $result->status->isSuccessful()
                    ? 'Payment approved'
                    : ($result->errorMessage ?? 'Payment declined'),
            ];
        });
    }
}
