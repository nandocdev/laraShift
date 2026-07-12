<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\DTOs;

final readonly class RefundRequestData
{
    public function __construct(
        public string $paymentId,
        public ?int $amountInCents = null,
        public ?string $notificationUrl = null,
    ) {}
}
