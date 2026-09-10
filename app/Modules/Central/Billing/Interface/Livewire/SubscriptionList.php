<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Livewire;

use App\Modules\Central\Billing\Domain\Models\Subscription;
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
        $subscriptions = Subscription::latest()->paginate(15);
        $tenants = Tenant::whereIn('id', $subscriptions->pluck('tenant_id')->unique())
            ->get()
            ->keyBy('id');

        return view('billing::livewire.subscription-list', [
            'subscriptions' => $subscriptions,
            'tenants' => $tenants,
        ]);
    }
}
