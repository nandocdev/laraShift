<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Actions\FetchTenantSubscriptionDetailAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class SubscriptionDetail extends Component
{
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function render(FetchTenantSubscriptionDetailAction $action): View
    {
        return view('billing::pages.subscription-detail', $action->execute($this->tenant));
    }
}
