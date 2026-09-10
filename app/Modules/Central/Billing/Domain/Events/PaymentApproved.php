<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Events;

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Platform\Contracts\Billing\BillingEventData;
use Illuminate\Foundation\Events\Dispatchable;

class PaymentApproved
{
    use Dispatchable;

    public function __construct(
        public Payment $payment,
        public BillingEventData $event,
    ) {}
}
