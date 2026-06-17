<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Actions;

use App\Modules\Central\Payments\Contracts\PaymentGateway;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\DTOs\PaymentResultData;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\Models\PaymentAttempt;
use App\Modules\Central\Payments\Events\PaymentApproved;
use App\Modules\Shared\Events\PaymentFailed;
use Illuminate\Support\Facades\DB;

/**
 * [REALIZACIÓN DE CASO DE USO - RUP]
 * Caso de Uso: Procesar Pago Directo (Smart Fields/Token)
 */
final readonly class ProcessDirectPaymentAction
{
    public function __construct(
        private PaymentGateway $gateway
    ) {}

    public function execute(PaymentData $data, string $token, ?bool $saveCard = false): array
    {
        return DB::transaction(function () use ($data, $token): array {
            $slug = $data->resolvedSlug();

            // Create Payment record
            $payment = Payment::create([
                'tenant_id' => $data->tenantId,
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

            if ($result->status->isSuccessful()) {
                PaymentApproved::dispatch($payment, $result);
                return [
                    'success' => true,
                    'displayId' => $data->displayId,
                    'message' => 'Payment approved'
                ];
            }

            PaymentFailed::dispatch($payment, $result);
            return [
                'success' => false,
                'message' => $result->errorMessage ?? 'Payment declined'
            ];
        });
    }
}
