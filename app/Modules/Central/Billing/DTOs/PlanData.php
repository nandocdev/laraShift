<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\DTOs;

use Spatie\LaravelData\Data;

final class PlanData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        public int $price_monthly, // in cents
        public int $price_yearly,  // in cents
        public bool $is_active = true,
        public array $features = [],
    ) {}
}
