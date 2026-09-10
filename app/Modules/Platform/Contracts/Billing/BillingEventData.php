<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use Spatie\LaravelData\Data;

final class BillingEventData extends Data
{
    public function __construct(
        public string $type,
        public string $gateway,
        public string $gatewayEventId,
        public ?string $displayId = null,
        public ?string $providerCustomerId = null,
        public ?int $amountCents = null,
        public ?string $currency = null,
        /** @var array<string, mixed> */
        public array $raw = [],
    ) {}
}
