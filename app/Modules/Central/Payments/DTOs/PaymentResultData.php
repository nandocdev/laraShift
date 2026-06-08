<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\DTOs;

use Spatie\LaravelData\Data;
use App\Modules\Central\Payments\Enums\PaymentStatus;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Numeric;

final class PaymentResultData extends Data {
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

        public readonly ?string $authorizationCode,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly array $raw = [],
    ) {
    }

    public static function fromClavePayload(array $payload): self {
        $status = match (true) {
            isset($payload['approved']) && $payload['approved'] => PaymentStatus::Approved,
            isset($payload['declined']) && $payload['declined'] => PaymentStatus::Declined,
            default => PaymentStatus::Pending,
        };

        return new self(
            gatewayReference: $payload['transactionId'] ?? $payload['txId'] ?? '',
            displayId: $payload['displayId'] ?? $payload['orderId'] ?? '',
            status: $status,
            amount: (float) ($payload['amount'] ?? 0),
            gatewayCode: $payload['gatewayCode'] ?? 'CLAVE',
            authorizationCode: $payload['authCode'] ?? $payload['authorization'] ?? null,
            errorCode: $payload['errorCode'] ?? null,
            errorMessage: $payload['errorMessage'] ?? $payload['description'] ?? null,
            raw: $payload,
        );
    }
}
