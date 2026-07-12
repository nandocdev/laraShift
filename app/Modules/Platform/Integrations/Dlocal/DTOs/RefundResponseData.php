<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\DTOs;

final readonly class RefundResponseData
{
    public function __construct(
        public string $id,
        public string $paymentId,
        public string $status,
        public int $amountInCents,
    ) {}

    public static function fromApiResponse(array $response): self
    {
        return new self(
            id: (string) $response['id'],
            paymentId: (string) $response['payment_id'],
            status: $response['status'],
            amountInCents: (int) round(((float) $response['amount']) * 100),
        );
    }
}
