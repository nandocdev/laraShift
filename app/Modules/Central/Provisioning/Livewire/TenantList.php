<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Livewire;

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

    public function selectTenant(Tenant $tenant): void
    {
        $this->selectedTenant = $tenant;
    }

    public function impersonate(ImpersonateTenantAction $action): void
    {
        $this->validate([
            'impersonationReason' => 'required|string|min:20',
        ]);

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
