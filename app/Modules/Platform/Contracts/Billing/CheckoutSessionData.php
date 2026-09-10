<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

use Spatie\LaravelData\Data;

final class CheckoutSessionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $url,
        public readonly string $provider,
    ) {}
}
