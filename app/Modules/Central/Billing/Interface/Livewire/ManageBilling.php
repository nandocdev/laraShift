<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Livewire;

use App\Modules\Central\Billing\Application\Jobs\SyncTenantInvoicesJob;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageBilling extends Component
{
    public function render(): View
    {
        $tenant = tenant();
        $subscription = $tenant->subscription('default');

        // Dispatch sync as background job
        SyncTenantInvoicesJob::dispatch((string) $tenant->id);

        return view('billing::pages.manage-billing', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'invoices' => Invoice::where('tenant_id', $tenant->id)->latest()->take(10)->get(),
        ]);
    }
}
