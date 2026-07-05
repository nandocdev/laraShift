<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Modules\Central\Billing\Application\DTO\PaymentResultData;
use App\Modules\Central\Billing\Domain\Models\Payment;

final class PaymentApproved {
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly PaymentResultData $result,
    ) {
    }
}
