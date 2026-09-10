<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use Spatie\LaravelData\Data;

final class ProviderPaymentRef extends Data
{
    public function __construct(
        public string $providerPaymentId,
        public string $gateway,
        public string $status,
        public ?int $amountCents = null,
    ) {}
}
