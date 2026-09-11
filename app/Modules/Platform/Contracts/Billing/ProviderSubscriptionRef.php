<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use Spatie\LaravelData\Data;

final class ProviderSubscriptionRef extends Data
{
    public function __construct(
        public string $providerSubscriptionId,
        public string $gateway,
        public string $status,
    ) {}
}
