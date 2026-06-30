<?php

declare(strict_types=1);

namespace App\Modules\Central\Monitoring\Livewire;

use App\Modules\Central\Monitoring\Actions\CheckCriticalAlertsAction;
use App\Modules\Central\Monitoring\Models\TenantHealthCheck;
use App\Modules\Shared\Models\Activity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class MonitoringDashboard extends Component
{
    public array $alerts = [];

    public function checkAlerts(CheckCriticalAlertsAction $action): void
    {
        $this->alerts = $action->execute();
    }

    public function render(): View
    {
        $healthSummary = TenantHealthCheck::selectRaw('
                check_type,
                status,
                COUNT(*) as count
            ')
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('check_type', 'status')
            ->get();

        $totalChecks = TenantHealthCheck::where('created_at', '>=', now()->subDay())->count();
        $failedChecks = TenantHealthCheck::where('created_at', '>=', now()->subDay())
            ->where('status', 'fail')
            ->count();

        $recentActivity = Activity::latest()->take(50)->get();

        return view('monitoring::pages.dashboard', [
            'healthSummary' => $healthSummary,
            'totalChecks' => $totalChecks,
            'failedChecks' => $failedChecks,
            'recentActivity' => $recentActivity,
            'alerts' => $this->alerts,
        ]);
    }
}
