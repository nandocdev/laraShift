<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Services\MrrCalculator;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class ReportsView extends Component
{
    public string $period = 'this_month';

    public function render(): View
    {
        $calculator = app(MrrCalculator::class);

        $dateFrom = match ($this->period) {
            'this_month' => now()->startOfMonth(),
            'last_month' => now()->subMonth()->startOfMonth(),
            'last_3_months' => now()->subMonths(3)->startOfMonth(),
            'last_12_months' => now()->subMonths(12)->startOfMonth(),
            default => now()->startOfMonth(),
        };

        $dateTo = now();

        $currentMrr = $calculator->calculateMrr();
        $churn30d = $calculator->churnRate(now()->subDays(30));
        $churn90d = $calculator->churnRate(now()->subDays(90));
        $statusCounts = $calculator->tenantStatusCounts();
        $mrrByPlan = $calculator->mrrByPlan();
        $monthlyBreakdown = $calculator->monthlyBreakdown(12);

        $newTenants = Tenant::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $churnedTenants = Tenant::where('status', 'archived')
            ->whereBetween('archived_at', [$dateFrom, $dateTo])
            ->count();

        return view('billing::pages.reports-view', [
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
        ]);
    }
}
