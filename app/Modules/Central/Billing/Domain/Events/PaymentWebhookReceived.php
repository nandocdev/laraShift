<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Modules\Central\Billing\Application\DTO\PaymentResultData;

final class PaymentWebhookReceived {
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PaymentResultData $result,
        public readonly string $tenantId,
    ) {
    }
}
