<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Actions;

use App\Modules\Central\Payments\DTOs\PayoutData;
use App\Modules\Central\Payments\DTOs\PayoutResultData;
use App\Modules\Shared\Contracts\PaymentGatewayContract;
use Illuminate\Support\Facades\Log;

/**
 * [REALIZACIÓN DE CASO DE USO - RUP]
 * Caso de Uso: Solicitar Retiro (Payout) para Tenant
 */
final readonly class RequestPayoutAction
{
    public function __construct(
        private PaymentGatewayContract $gateway
    ) {}

    public function execute(PayoutData $data): PayoutResultData
    {
        Log::info('Processing payout request for tenant', [
            'tenant_id' => $data->tenantId,
            'amount' => $data->amount,
            'currency' => $data->currency,
        ]);

        // In a real scenario, we would check tenant balance here first

        $result = $this->gateway->submitPayout($data);

        if ($result->isSuccessful()) {
            Log::info('Payout processed successfully', ['id' => $result->id]);
        } elseif ($result->isPending()) {
            Log::info('Payout pending approval', ['id' => $result->id]);
        } else {
            Log::error('Payout failed', [
                'id' => $result->id,
                'detail' => $result->statusDetail,
            ]);
        }

        return $result;
    }
}
