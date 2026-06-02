<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Livewire;

use App\Modules\Tenant\Audit\Models\AuditLog;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AuditLogViewer extends Component
{
    use WithPagination;

    #[Url(as: 'user')]
    public string $filterUser = '';

    #[Url(as: 'action')]
    public string $filterAction = '';

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    // Export State
    public bool $showingExportModal = false;
    public bool $exporting = false;
    public string $exportFrom = '';
    public string $exportTo = '';

    public function mount(): void
    {
        $this->exportFrom = now()->subDays(30)->format('Y-m-d');
        $this->exportTo = now()->format('Y-m-d');
    }

    public function updated($property): void
    {
        if (in_array($property, ['filterUser', 'filterAction', 'dateFrom', 'dateTo'])) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['filterUser', 'filterAction', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function export(): void
    {
        $this->validate([
            'exportFrom' => 'required|date',
            'exportTo' => 'required|date|after_or_equal:exportFrom',
        ]);

        $diff = \Carbon\Carbon::parse($this->exportFrom)->diffInDays($this->exportTo);

        if ($diff > 90) {
            $this->addError('exportFrom', __('Export range cannot exceed 90 days.'));
            return;
        }

        $this->exporting = true;

        \App\Modules\Tenant\Audit\Jobs\ExportAuditLogsJob::dispatch(
            tenant('id'),
            auth()->id(),
            $this->exportFrom,
            $this->exportTo
        );

        $this->showingExportModal = false;
        $this->exporting = false;

        session()->flash('status', __('The export has been queued. You will receive an email with the download link shortly.'));
    }

    public function render(): View
    {
        $query = AuditLog::with('user')->latest();

        if ($this->filterUser) {
            $query->where('user_id', $this->filterUser);
        }

        if ($this->filterAction) {
            $query->where('action', 'like', "%{$this->filterAction}%");
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return view('audit::pages.viewer', [
            'logs' => $query->paginate(50),
            'users' => User::all(),
        ]);
    }
}
