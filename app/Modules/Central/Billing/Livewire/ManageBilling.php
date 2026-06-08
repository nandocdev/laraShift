<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Actions\SyncInvoicesAction;
use App\Modules\Central\Billing\Models\Invoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Log;

#[Layout('layouts.app')]
class ManageBilling extends Component
{
    public function render(): View
    {
        $tenant = tenant();
        $subscription = $tenant->subscription('default');

        // Dispatch sync as background job
        \App\Modules\Central\Billing\Jobs\SyncTenantInvoicesJob::dispatch($tenant);
        
        return view('billing::pages.manage-billing', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'invoices' => Invoice::where('tenant_id', $tenant->id)->latest()->take(10)->get(),
        ]);
    }
}
