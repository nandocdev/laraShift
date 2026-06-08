<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\Models\PaymentAttempt;

final class CheckoutSessionCreated {
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentAttempt $attempt,
    ) {
    }
}
