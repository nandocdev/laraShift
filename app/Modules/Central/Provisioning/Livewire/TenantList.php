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

    public ?Tenant $selectedTenant = null;
    public string $impersonationReason = '';
    public string $confirmSlug = '';

    public function selectTenant(Tenant $tenant): void
    {
        $this->selectedTenant = $tenant;
        $this->confirmSlug = '';
    }

    public function delete(DeleteTenantAction $action): void
    {
        if ($this->confirmSlug !== $this->selectedTenant->slug) {
            $this->addError('confirmSlug', __('Slug confirmation does not match.'));
            return;
        }

        try {
            $action->execute($this->selectedTenant, true); // US-103: Purge is completed in background job
            
            $this->reset(['selectedTenant', 'confirmSlug']);
            session()->flash('status', __('Tenant deletion queued successfully.'));
        } catch (\Exception $e) {
            $this->addError('confirmSlug', $e->getMessage());
        }
            if (! $this->selectedTenant) {
                $this->addError('confirmSlug', __('No tenant selected.')); 
                return;
            }
    }

    public function impersonate(ImpersonateTenantAction $action): void
    {
        $this->validate([
            'impersonationReason' => 'required|string|min:20',
        ]);

        if (! $this->selectedTenant) {
            $this->addError('impersonationReason', __('No tenant selected.'));
            return;
        }
        try {
            $url = $action->execute($this->selectedTenant, $this->impersonationReason);
            
            $this->reset(['impersonationReason', 'selectedTenant']);
            $this->redirect($url, navigate: false); // External redirect to tenant domain
        } catch (\Exception $e) {
            $this->addError('impersonationReason', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('provisioning::pages.tenant-list', [
            'tenants' => Tenant::with('domains')->latest()->paginate(10),
        ]);
    }
}
