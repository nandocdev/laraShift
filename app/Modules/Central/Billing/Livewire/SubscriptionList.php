<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class SubscriptionList extends Component
{
    use WithPagination;

    public function render(): View
    {
        return view('billing::pages.subscription-list', [
            'tenants' => Tenant::has('subscriptions')->with('subscriptions')->latest()->paginate(10),
        ]);
    }
}
