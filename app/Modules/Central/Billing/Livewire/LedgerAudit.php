<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Plinth\MultiTenantBilling\Core\Models\LedgerEntry;

#[Layout('layouts.central')]
class LedgerAudit extends Component {
    use WithPagination;

    #[Url(as: 'tenant')]
    public string $tenantId = '';

    #[Url(as: 'type')]
    public string $type = '';

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'sort_by')]
    public string $sortBy = 'created_at';

    #[Url(as: 'direction')]
    public string $sortDirection = 'desc';

    /**
     * Reset pagination when filters change.
     */
    public function updatingFilters(): void {
        $this->resetPage();
    }

    /**
     * Toggle sorting direction or change sort column.
     *
     * @param string $field The field name.
     */
    public function sort(string $field): void {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }
    }

    /**
     * Render the Livewire component.
     * 
     * [RIESGOS]
     * - Pagination of large datasets -> Mitigated by using paginate(25).
     * - N+1 Query on polymorphic relations -> Polymorphic references are loaded on-demand.
     */
    public function render(): View {
        $query = LedgerEntry::query();

        // Filters
        if ($this->tenantId !== '') {
            $query->where('tenant_id', $this->tenantId);
        }

        if ($this->type !== '') {
            $query->where('type', $this->type);
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('description', 'like', '%' . $this->search . '%')
                    ->orWhere('reference_type', 'like', '%' . $this->search . '%')
                    ->orWhere('reference_id', 'like', '%' . $this->search . '%')
                    ->orWhere('tenant_id', 'like', '%' . $this->search . '%');
            });
        }

        // Sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        // Fetch tenants for dropdown filter
        $tenants = Tenant::orderBy('name')->get();

        return view('billing::pages.ledger-audit', [
            'entries' => $query->paginate(25),
            'tenants' => $tenants,
        ]);
    }
}
