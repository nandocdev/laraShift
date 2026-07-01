<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\DTOs;

use Spatie\LaravelData\Data;

class MonthlyBreakdownRow extends Data
{
    public function __construct(
        public string $month,
        public float $mrr,
        public int $newTenants,
        public int $churned,
    ) {}
}
