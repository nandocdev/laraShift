<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\DTOs;

use App\Modules\Platform\Integrations\Dlocal\Enums\DlocalPaymentStatus;

final readonly class PaymentResponseData
{
    public function __construct(
        public string $id,
        public string $orderId,
        public DlocalPaymentStatus $status,
        public ?string $statusDetail,
        public int $amountInCents,
        public string $currency,
        public ?string $redirectUrl = null,
    ) {}

    public static function fromApiResponse(array $response): self
    {
        return new self(
            id: (string) $response['id'],
            orderId: (string) $response['order_id'],
            status: DlocalPaymentStatus::from($response['status']),
            statusDetail: $response['status_detail'] ?? null,
            amountInCents: (int) round(((float) $response['amount']) * 100),
            currency: $response['currency'],
            redirectUrl: $response['redirect_url'] ?? null,
        );
    }
}
