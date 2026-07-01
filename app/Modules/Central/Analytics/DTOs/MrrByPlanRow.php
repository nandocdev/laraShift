<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\DTOs;

use Spatie\LaravelData\Data;

class MrrByPlanRow extends Data
{
    public function __construct(
        public string $plan,
        public int $count,
        public float $mrr,
    ) {}
}
