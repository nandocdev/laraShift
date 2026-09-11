<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Livewire;

use App\Modules\Central\Provisioning\Actions\DeleteTenantAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\ImpersonateTenantAction;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class TenantList extends Component
{
    use WithPagination;

    public ?string $selectedTenantId = null;

    public string $search = '';

    public string $statusFilter = '';

    public string $planFilter = '';

    public string $healthFilter = '';

    public string $impersonationTicketId = '';

    public string $impersonationReason = '';

    public string $confirmSlug = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPlanFilter(): void
    {
        $this->resetPage();
    }

    public function updatedHealthFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'planFilter', 'healthFilter']);
        $this->resetPage();
    }

    public function getSelectedTenantProperty(): ?Tenant
    {
        if (! $this->selectedTenantId) {
            return null;
        }

        return Tenant::find($this->selectedTenantId);
    }

    public function selectTenant($tenantId): void
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->addError('selectedTenant', __('Tenant not found.'));

            return;
        }

        $this->selectedTenantId = $tenant->id;
        $this->confirmSlug = '';
    }

    public function delete(DeleteTenantAction $action): void
    {
        $tenant = $this->selectedTenant;

        if (! $tenant) {
            $this->addError('confirmSlug', __('No tenant selected.'));

            return;
        }

        if ($this->confirmSlug !== $tenant->slug) {
            $this->addError('confirmSlug', __('Slug confirmation does not match.'));

            return;
        }

        try {
            $action->execute($tenant, true); // US-103: Purge is completed in background job

            $this->reset(['selectedTenantId', 'confirmSlug']);
            session()->flash('status', __('Tenant deletion queued successfully.'));
        } catch (\Exception $e) {
            $this->addError('confirmSlug', $e->getMessage());
        }
    }

    public function impersonate(ImpersonateTenantAction $action): void
    {
        $tenant = $this->selectedTenant;

        if (! $tenant) {
            $this->addError('impersonationReason', __('No tenant selected.'));

            return;
        }

        $this->validate([
            'impersonationTicketId' => 'required|string|min:3|max:50',
            'impersonationReason' => 'required|string|min:20',
        ]);

        try {
            $reason = "[Ticket: {$this->impersonationTicketId}] {$this->impersonationReason}";
            $url = $action->execute($tenant, $reason);

            $this->reset(['impersonationReason', 'impersonationTicketId', 'selectedTenantId']);
            $this->js("window.open('{$url}', '_blank');"); // Open in isolated tab
        } catch (\Exception $e) {
            $this->addError('impersonationReason', $e->getMessage());
        }
    }

    public function render(): View
    {
        $tenants = Tenant::with('domains')
            ->when($this->search !== '', function ($query) {
                $term = '%'.strtolower($this->search).'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->planFilter !== '', fn ($query) => $query->where('plan_id', $this->planFilter))
            ->when($this->healthFilter !== '', fn ($query) => $query->whereIn('status', self::statusesForHealth($this->healthFilter)))
            ->latest()
            ->paginate(10);

        return view('provisioning::pages.tenant-list', [
            'tenants' => $tenants,
            'selectedTenant' => $this->selectedTenant,
            'plans' => $this->planOptions(),
            'statuses' => self::statusOptions(),
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function statusOptions(): array
    {
        return [
            ['value' => 'provisioning', 'label' => 'Provisioning'],
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'pending_payment', 'label' => 'Pending payment'],
            ['value' => 'past_due', 'label' => 'Past due'],
            ['value' => 'suspended', 'label' => 'Suspended'],
            ['value' => 'quarantine', 'label' => 'Quarantine'],
            ['value' => 'archived', 'label' => 'Archived'],
            ['value' => 'failed', 'label' => 'Failed'],
            ['value' => 'expired', 'label' => 'Expired'],
        ];
    }

    /**
     * Salud derivada del estado: active → healthy; provisioning/pending_payment/
     * suspended → warning; resto → critical.
     */
    public static function healthFor(string $status): string
    {
        return match (true) {
            $status === 'active' => 'healthy',
            in_array($status, ['provisioning', 'pending_payment', 'suspended'], true) => 'warning',
            default => 'critical',
        };
    }

    /**
     * @return list<string>
     */
    public static function statusesForHealth(string $health): array
    {
        return match ($health) {
            'healthy' => ['active'],
            'warning' => ['provisioning', 'pending_payment', 'suspended'],
            'critical' => ['past_due', 'quarantine', 'archived', 'failed', 'expired'],
            default => [],
        };
    }

    /**
     * @return array<int, array{slug: string, name: string}>
     */
    private function planOptions(): array
    {
        try {
            return Plan::where('is_active', true)
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn ($plan) => ['slug' => $plan->slug, 'name' => $plan->name])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
