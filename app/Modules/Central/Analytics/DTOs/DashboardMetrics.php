<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\DTOs;

use Spatie\LaravelData\Data;

class DashboardMetrics extends Data
{
    public function __construct(
        public float $currentMrr,
        public float $churn30d,
        public int $totalTenants,
        public int $activeTenants,
        public int $suspendedTenants,
        public int $archivedTenants,
        public int $failedProvisioning,
        /** @var MonthlyBreakdownRow[] */
        public array $monthlyBreakdown,
        /** @var MrrByPlanRow[] */
        public array $mrrByPlan,
    ) {}
}
