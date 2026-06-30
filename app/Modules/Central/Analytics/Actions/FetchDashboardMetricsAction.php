<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\Actions;

use App\Modules\Central\Analytics\DTOs\DashboardMetrics;
use App\Modules\Central\Analytics\DTOs\MonthlyBreakdownRow;
use App\Modules\Central\Analytics\DTOs\MrrByPlanRow;
use App\Modules\Central\Analytics\Models\PlatformMetric;
use App\Modules\Central\Billing\Services\MrrCalculator;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class FetchDashboardMetricsAction
{
    public function __construct(
        private MrrCalculator $calculator,
    ) {}

    public function execute(): DashboardMetrics
    {
        $latest = PlatformMetric::where('period', now()->format('Y-m-d'))
            ->get()
            ->keyBy(fn ($m) => $m->group ? "{$m->metric}.{$m->group}" : $m->metric);

        $monthlyBreakdown = array_map(
            fn (array $row) => new MonthlyBreakdownRow(
                month: $row['month'],
                mrr: $row['mrr'],
                newTenants: $row['new_tenants'],
                churned: $row['churned'],
            ),
            $this->calculator->monthlyBreakdown(12),
        );

        $mrrByPlan = array_map(
            fn (array $row) => new MrrByPlanRow(
                plan: $row['plan'],
                count: $row['count'],
                mrr: $row['mrr'],
            ),
            $this->calculator->mrrByPlan(),
        );

        return new DashboardMetrics(
            currentMrr: $latest->get('mrr')?->value ?? $this->calculator->calculateMrr(),
            churn30d: $latest->get('churn_rate_30d')?->value ?? $this->calculator->churnRate(now()->subDays(30)),
            totalTenants: $latest->get('tenants.total')?->value ?? Tenant::count(),
            activeTenants: $latest->get('tenants.active')?->value ?? 0,
            suspendedTenants: $latest->get('tenants.suspended')?->value ?? 0,
            archivedTenants: $latest->get('tenants.archived')?->value ?? 0,
            failedProvisioning: $latest->get('provisioning.failed')?->value ?? 0,
            monthlyBreakdown: $monthlyBreakdown,
            mrrByPlan: $mrrByPlan,
        );
    }
}
