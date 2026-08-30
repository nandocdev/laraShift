<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Compliance\Interface\Livewire;

use App\Modules\Tenant\Compliance\Application\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Compliance\Application\Jobs\ExportAuditLogsJob;
use App\Modules\Tenant\Compliance\Domain\DTOs\AuditLogData;
use App\Modules\Tenant\Compliance\Domain\Enums\AuditAction;
use App\Modules\Tenant\Compliance\Domain\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AuditLogViewer extends Component
{
    use AuthorizesRequests, WithPagination;

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
        $this->authorize('audit:read');
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
        $this->authorize('audit:read');

        $this->validate([
            'exportFrom' => 'required|date',
            'exportTo' => 'required|date|after_or_equal:exportFrom',
        ]);

        $diff = Carbon::parse($this->exportFrom)->diffInDays($this->exportTo);

        // Security Policy: Range limit enforced at application level.
        if ($diff > 90) {
            $this->addError('exportFrom', __('Export range cannot exceed 90 days.'));

            return;
        }

        $this->exporting = true;

        // Record the export request in audit log
        app(RecordAuditLogAction::class)->execute(new AuditLogData(
            action: AuditAction::EXPORT_STARTED,
            metadata: ['from' => $this->exportFrom, 'to' => $this->exportTo]
        ));

        ExportAuditLogsJob::dispatch(
            tenant('id'),
            auth()->id(),
            $this->exportFrom,
            $this->exportTo
        );

        $this->showingExportModal = false;
        $this->exporting = false;

        $this->dispatch('notify', message: __('The export has been queued. You will receive an email shortly.'));
    }

    public function render(): View
    {
        $this->authorize('audit:read');

        $query = AuditLog::with('user')->latest();

        if ($this->filterUser) {
            $query->whereHas('user', function ($q) {
                $q->where('name', 'like', "%{$this->filterUser}%");
            });
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

        return view('compliance::pages.viewer', [
            'logs' => $query->paginate(50),
        ]);
    }
}
