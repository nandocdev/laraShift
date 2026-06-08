<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Services;

use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\Models\PaymentAttempt;

final readonly class CheckoutSession {
    public function __construct(
        public Payment $payment,
        public PaymentAttempt $attempt,
        public string $checkoutUrl,
        public string $slug,
    ) {
    }
}
