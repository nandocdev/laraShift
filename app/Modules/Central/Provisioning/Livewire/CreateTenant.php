<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Livewire;

use App\Modules\Central\Provisioning\Actions\CreateTenantAction;
use App\Modules\Central\Provisioning\DTOs\CreateTenantData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.central')]
class CreateTenant extends Component
{
    #[Validate('required|string|min:3')]
    public string $name = '';

    #[Validate(['required', 'alpha_dash', 'unique:domains,domain', 'not_in:admin,api,root,support,www,mail,dev,stage,prod,central'])]
    public string $slug = '';

    #[Validate('required|email')]
    public string $email = '';

    public string $plan_id = 'free';

    public function save(CreateTenantAction $action): void
    {
        $this->validate();

        $data = new CreateTenantData(
            name: $this->name,
            slug: $this->slug,
            email: $this->email,
            plan_id: $this->plan_id,
        );

        try {
            $action->execute($data);

            session()->flash('status', __('Tenant provisioned successfully.'));
            $this->redirect(route('central.provisioning.index'), navigate: true);
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('provisioning::pages.create-tenant');
    }
}
