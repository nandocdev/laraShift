<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Modules\Central\Payments\DTOs\PaymentResultData;
use App\Modules\Central\Payments\Models\Payment;

final class PaymentDeclined {
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentResultData $result,
    ) {
    }
}
