<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class SubscriptionDetail extends Component
{
    use WithPagination;

    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function render(): View
    {
        $subscription = Subscription::where('tenant_id', $this->tenant->id)
            ->latest()
            ->first();

        $invoices = Invoice::where('tenant_id', $this->tenant->id)
            ->latest('issued_at')
            ->paginate(10);

        return view('billing::pages.subscription-detail', [
            'subscription' => $subscription,
            'invoices' => $invoices,
        ]);
    }
}
