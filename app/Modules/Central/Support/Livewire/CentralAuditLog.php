<?php

declare(strict_types=1);

namespace App\Modules\Central\Support\Livewire;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Observability\Audit\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.central')]
class CentralAuditLog extends Component
{
    use WithPagination;

    public string $search = '';

    public string $logFilter = '';

    public string $tenantSlug = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?string $selectedId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLogFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTenantSlug(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'logFilter', 'tenantSlug', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function view(string $id): void
    {
        $this->selectedId = $id;
    }

    public function export(): StreamedResponse
    {
        $rows = $this->baseQuery()->latest()->limit(1000)->get();

        $filename = 'audit-log-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'log', 'action', 'actor', 'resource', 'date']);

            foreach ($rows as $act) {
                fputcsv($out, [
                    $act->id,
                    $act->log_name,
                    $act->description,
                    $act->causer?->name ?? 'system',
                    $this->resourceLabel($act),
                    $act->created_at->toIso8601String(),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function logs(): array
    {
        try {
            return Activity::distinct()
                ->whereNotNull('log_name')
                ->orderBy('log_name')
                ->pluck('log_name')
                ->map(fn ($log) => ['value' => $log, 'label' => ucfirst($log)])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    #[Computed]
    public function selected(): ?Activity
    {
        if (! $this->selectedId) {
            return null;
        }

        return Activity::find($this->selectedId);
    }

    public function render(): View
    {
        $entries = $this->baseQuery()->latest()->paginate(15);

        $tenantNames = $this->resolveTenantNames($entries->getCollection());

        return view('support::livewire.central-audit-log', [
            'entries' => $entries,
            'tenantNames' => $tenantNames,
        ]);
    }

    private function baseQuery(): Builder
    {
        return Activity::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.strtolower($this->search).'%';
                $query->whereRaw('LOWER(description) LIKE ?', [$term]);
            })
            ->when($this->logFilter !== '', fn ($query) => $query->where('log_name', $this->logFilter))
            ->when($this->tenantSlug !== '', function ($query) {
                $ids = Tenant::where('slug', 'like', '%'.$this->tenantSlug.'%')->pluck('id')->all();
                $query->where(function ($nested) use ($ids) {
                    $nested->where(function ($q) use ($ids) {
                        $q->where('subject_type', Tenant::class)->whereIn('subject_id', $ids);
                    })->orWhere(function ($q) use ($ids) {
                        $q->where('causer_type', Tenant::class)->whereIn('causer_id', $ids);
                    });
                });
            })
            ->when($this->dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $this->dateTo));
    }

    private function resourceLabel(Activity $act): string
    {
        if (! $act->subject_type) {
            return '—';
        }

        return class_basename($act->subject_type).'#'.$act->subject_id;
    }

    /**
     * @param  Collection<int, Activity>  $entries
     * @return array<string, string> subject_id => tenant slug
     */
    private function resolveTenantNames($entries): array
    {
        $ids = $entries
            ->where('subject_type', Tenant::class)
            ->map(fn ($act) => (string) $act->subject_id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        try {
            return Tenant::whereIn('id', $ids)->pluck('slug', 'id')->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
