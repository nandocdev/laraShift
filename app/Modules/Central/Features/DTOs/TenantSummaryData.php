<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\DTOs;

use Spatie\LaravelData\Data;

final class TenantSummaryData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $email,
        public string $plan_id,
        public string $status,
    ) {}
}
