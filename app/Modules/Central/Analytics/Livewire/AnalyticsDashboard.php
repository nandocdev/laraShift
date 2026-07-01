<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\Livewire;

use App\Modules\Central\Analytics\Actions\ExportPlatformMetricsAction;
use App\Modules\Central\Analytics\Actions\FetchDashboardMetricsAction;
use App\Modules\Central\Analytics\Exceptions\ExportFailedException;
use App\Modules\Shared\Tenancy\Services\CentralFallback;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class AnalyticsDashboard extends Component
{
    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $exporting = false;

    public function mount(CentralFallback $centralFallback): void
    {
        $centralFallback->ensureCentral();

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
        } catch (ExportFailedException $e) {
            $this->addError('export', $e->getMessage());
        } finally {
            $this->exporting = false;
        }
    }

    public function render(FetchDashboardMetricsAction $action): View
    {
        $metrics = $action->execute();

        return view('analytics::pages.dashboard', [
            'currentMrr' => $metrics->currentMrr,
            'churn30d' => $metrics->churn30d,
            'totalTenants' => $metrics->totalTenants,
            'activeTenants' => $metrics->activeTenants,
            'suspendedTenants' => $metrics->suspendedTenants,
            'archivedTenants' => $metrics->archivedTenants,
            'failedProvisioning' => $metrics->failedProvisioning,
            'monthlyBreakdown' => $metrics->monthlyBreakdown,
            'mrrByPlan' => $metrics->mrrByPlan,
        ]);
    }
}
