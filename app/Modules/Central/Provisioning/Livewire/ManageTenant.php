<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Livewire;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Features\Actions\ResolveTenantFeaturesAction;
use App\Modules\Central\Provisioning\Actions\UpdateTenantAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class ManageTenant extends Component
{
    public Tenant $tenant;

    public string $name = '';

    public string $email = '';

    public string $plan_id = '';

    public string $status = '';

    public bool $maintenance_mode = false;

    public bool $read_only = false;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->name = $tenant->name;
        $this->email = $tenant->email;
        $this->plan_id = (string) $tenant->plan_id;
        $this->status = $tenant->status;
        $this->maintenance_mode = $tenant->maintenance_mode;
        $this->read_only = $tenant->read_only;
    }

    public function save(UpdateTenantAction $action): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'plan_id' => 'required|string',
            'status' => 'required|in:provisioning,active,suspended,archived,failed',
            'maintenance_mode' => 'boolean',
            'read_only' => 'boolean',
        ]);

        $action->execute($this->tenant, [
            'name' => $this->name,
            'email' => $this->email,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'maintenance_mode' => $this->maintenance_mode,
            'read_only' => $this->read_only,
        ]);

        if ($this->tenant->wasChanged('plan_id')) {
            app(ResolveTenantFeaturesAction::class)->execute($this->tenant, true);
        }

        session()->flash('status', __('Tenant updated successfully.'));
        $this->redirect(route('central.provisioning.index'), navigate: true);
    }

    public function render(): View
    {
        return view('provisioning::pages.manage-tenant', [
            'plans' => Plan::where('is_active', true)->get(),
        ]);
    }
}
