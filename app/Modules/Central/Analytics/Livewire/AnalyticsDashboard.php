<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\Livewire;

use App\Modules\Central\Analytics\Actions\ExportPlatformMetricsAction;
use App\Modules\Central\Analytics\Models\PlatformMetric;
use App\Modules\Central\Billing\Services\MrrCalculator;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class AnalyticsDashboard extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $exporting = false;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function export(ExportPlatformMetricsAction $action): void
    {
        $this->validate([
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
        ]);

        $this->exporting = true;

        try {
            $filePath = $action->execute($this->dateFrom, $this->dateTo);
            session()->flash('status', __('Export generated. File: :path', ['path' => $filePath]));
        } catch (\Exception $e) {
            $this->addError('export', $e->getMessage());
        } finally {
            $this->exporting = false;
        }
    }

    public function render(): View
    {
        $calculator = app(MrrCalculator::class);

        $latest = PlatformMetric::where('period', now()->format('Y-m-d'))
            ->get()
            ->keyBy(fn ($m) => $m->group ? "{$m->metric}.{$m->group}" : $m->metric);

        $monthlyBreakdown = $calculator->monthlyBreakdown(12);

        $mrrByPlan = $calculator->mrrByPlan();

        return view('analytics::pages.dashboard', [
            'currentMrr' => $latest->get('mrr')?->value ?? $calculator->calculateMrr(),
            'churn30d' => $latest->get('churn_rate_30d')?->value ?? $calculator->churnRate(now()->subDays(30)),
            'totalTenants' => $latest->get('tenants.total')?->value ?? Tenant::count(),
            'activeTenants' => $latest->get('tenants.active')?->value ?? 0,
            'suspendedTenants' => $latest->get('tenants.suspended')?->value ?? 0,
            'archivedTenants' => $latest->get('tenants.archived')?->value ?? 0,
            'failedProvisioning' => $latest->get('provisioning.failed')?->value ?? 0,
            'monthlyBreakdown' => $monthlyBreakdown,
            'mrrByPlan' => $mrrByPlan,
        ]);
    }
}
