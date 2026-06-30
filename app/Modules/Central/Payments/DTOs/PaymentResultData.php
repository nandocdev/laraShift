<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\DTOs;

use App\Modules\Central\Payments\Enums\PaymentStatus;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class PaymentResultData extends Data
{
    public function __construct(
        #[Required, StringType]
        public readonly string $gatewayReference,

        #[Required, StringType]
        public readonly string $displayId,

        #[Required]
        public readonly PaymentStatus $status,

        #[Required, Numeric]
        public readonly float $amount,

        #[Required, StringType]
        public readonly string $gatewayCode,

        public readonly ?string $authorizationCode = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = [],
    ) {}

    public static function fromClavePayload(array $payload): self
    {
        // 1. Determine Status
        // Webhook uses 'status' (1 approved, 0 declined)
        // Redirect uses 'Estado' ('Aprobada', 'Denegada', 'Pendiente')
        $status = match (true) {
            ($payload['status'] ?? null) === 1 => PaymentStatus::Approved,
            ($payload['status'] ?? null) === 0 => PaymentStatus::Declined,
            ($payload['Estado'] ?? '') === 'Aprobada' => PaymentStatus::Approved,
            ($payload['Estado'] ?? '') === 'Denegada' => PaymentStatus::Declined,
            isset($payload['approved']) && $payload['approved'] => PaymentStatus::Approved,
            isset($payload['declined']) && $payload['declined'] => PaymentStatus::Declined,
            default => PaymentStatus::Pending,
        };

        // 2. Resolve Gateway Reference (codOper / Oper / transactionId)
        $reference = (string) ($payload['codOper'] ?? $payload['Oper'] ?? $payload['transactionId'] ?? $payload['txId'] ?? '');

        // 3. Resolve Amount (totalPay / TotalPagado / amount)
        $amount = (float) ($payload['totalPay'] ?? $payload['TotalPagado'] ?? $payload['amount'] ?? 0);

        // 4. Resolve Display ID (order/invoice reference)
        // We often send displayId in PARM_2 or PARM_1 depending on the flow
        $displayId = (string) ($payload['PARM_2'] ?? $payload['PARM_1'] ?? $payload['displayId'] ?? $payload['orderId'] ?? '');

        return new self(
            gatewayReference: $reference,
            displayId: $displayId,
            status: $status,
            amount: $amount,
            gatewayCode: $payload['type'] ?? $payload['gatewayCode'] ?? 'CLAVE',
            authorizationCode: $payload['authStatus'] ?? $payload['authCode'] ?? $payload['authorization'] ?? null,
            errorCode: $payload['error_code'] ?? $payload['errorCode'] ?? null,
            errorMessage: $payload['messageSys'] ?? $payload['errorMessage'] ?? $payload['Razon'] ?? $payload['description'] ?? null,
            raw: $payload,
        );
    }
}
