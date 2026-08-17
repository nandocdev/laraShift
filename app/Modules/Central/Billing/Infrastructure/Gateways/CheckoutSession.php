<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

use App\Modules\Central\Billing\Application\DTO\PaymentResultData;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentAttempt;

final readonly class CheckoutSession
{
    public function __construct(
        public Payment $payment,
        public PaymentAttempt $attempt,
        public ?string $checkoutUrl,
        public string $slug,
        public ?PaymentResultData $result = null,
    ) {}
}
