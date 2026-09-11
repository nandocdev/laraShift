<?php

declare(strict_types=1);

namespace App\Modules\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PaymentWebhookReceived
{
    use Dispatchable;

    public function __construct(
        public string $provider,
        public string $providerEventId,
        /** @var array<string, mixed> */
        public array $payload,
    ) {}
}
