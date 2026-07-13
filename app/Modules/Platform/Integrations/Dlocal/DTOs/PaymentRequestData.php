<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\DTOs;

use App\Modules\Platform\Integrations\Dlocal\Enums\PaymentMethodFlow;

final readonly class PaymentRequestData
{
    public function __construct(
        public string $orderId,
        public int $amountInCents,
        public string $currency,
        public string $country,
        public PayerData $payer,
        public PaymentMethodFlow $flow,
        public ?string $description = null,
        public ?string $token = null,
        public ?string $notificationUrl = null,
        public ?string $successUrl = null,
        public ?string $backUrl = null,
        public array $metadata = [],
    ) {}
}
