<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class HostedCheckout extends Component
{
    public Tenant $tenant;

    public Plan $plan;

    public function mount(string $tenant_uuid, string $plan_uuid): void
    {
        $this->tenant = Tenant::findOrFail($tenant_uuid);
        $this->plan = Plan::findOrFail($plan_uuid);
    }

    #[On('payment-approved')]
    public function handleSuccess(): void
    {
        $this->dispatch('toast', variant: 'success', heading: __('Payment Approved'), text: __('Your subscription has been activated successfully.'));

        session()->flash('success', __('Your subscription has been activated successfully.'));
        $this->redirect(route('tenant.billing.success'), navigate: true);
    }

    public function render(): View
    {
        return view('billing::pages.hosted-checkout');
    }
}
