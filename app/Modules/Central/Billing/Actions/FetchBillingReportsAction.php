<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Services\MrrCalculator;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class FetchBillingReportsAction
{
    public function __construct(
        private MrrCalculator $calculator,
    ) {}

    public function execute(string $period): array
    {
        $dateFrom = match ($period) {
            'this_month' => now()->startOfMonth(),
            'last_month' => now()->subMonth()->startOfMonth(),
            'last_3_months' => now()->subMonths(3)->startOfMonth(),
            'last_12_months' => now()->subMonths(12)->startOfMonth(),
            default => now()->startOfMonth(),
        };

        $dateTo = now();

        $currentMrr = $this->calculator->calculateMrr();
        $churn30d = $this->calculator->churnRate(now()->subDays(30));
        $churn90d = $this->calculator->churnRate(now()->subDays(90));
        $statusCounts = $this->calculator->tenantStatusCounts();
        $mrrByPlan = $this->calculator->mrrByPlan();
        $monthlyBreakdown = $this->calculator->monthlyBreakdown(12);

        $newTenants = Tenant::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $churnedTenants = Tenant::where('status', 'archived')
            ->whereBetween('archived_at', [$dateFrom, $dateTo])
            ->count();

        return [
            'currentMrr' => $currentMrr,
            'arr' => $currentMrr * 12,
            'churn30d' => $churn30d,
            'churn90d' => $churn90d,
            'totalTenants' => $statusCounts['active'] ?? 0,
            'newTenants' => $newTenants,
            'churnedTenants' => $churnedTenants,
            'mrrByPlan' => $mrrByPlan,
            'monthlyBreakdown' => $monthlyBreakdown,
            'statusCounts' => $statusCounts,
        ];
    }
}
