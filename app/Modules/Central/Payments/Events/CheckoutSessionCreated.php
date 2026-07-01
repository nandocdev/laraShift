<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Events;

use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\Models\PaymentAttempt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CheckoutSessionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentAttempt $attempt,
    ) {}
}
