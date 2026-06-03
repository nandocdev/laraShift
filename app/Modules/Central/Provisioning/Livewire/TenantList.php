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
class TenantList extends Component {
    use WithPagination;

    public ?int $selectedTenantId = null;
    public string $impersonationReason = '';
    public string $confirmSlug = '';

    public function getSelectedTenantProperty(): ?Tenant {
        if (! $this->selectedTenantId) {
            return null;
        }

        return Tenant::find($this->selectedTenantId);
    }

    public function selectTenant($tenantId): void {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->addError('selectedTenant', __('Tenant not found.'));
            return;
        }

        $this->selectedTenantId = (int) $tenant->id;
        $this->confirmSlug = '';
    }

    public function delete(DeleteTenantAction $action): void {
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

    public function impersonate(ImpersonateTenantAction $action): void {
        $tenant = $this->selectedTenant;

        if (! $tenant) {
            $this->addError('impersonationReason', __('No tenant selected.'));
            return;
        }

        $this->validate([
            'impersonationReason' => 'required|string|min:20',
        ]);

        try {
            $url = $action->execute($tenant, $this->impersonationReason);

            $this->reset(['impersonationReason', 'selectedTenantId']);
            $this->redirect($url, navigate: false); // External redirect to tenant domain
        } catch (\Exception $e) {
            $this->addError('impersonationReason', $e->getMessage());
        }
    }

    public function render(): View {
        return view('provisioning::pages.tenant-list', [
            'tenants' => Tenant::with('domains')->latest()->paginate(10),
            'selectedTenant' => $this->selectedTenant,
        ]);
    }
}
