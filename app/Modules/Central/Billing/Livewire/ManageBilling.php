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

        // Sync invoices from gateway
        try {
            $syncedCount = app(SyncInvoicesAction::class)->execute($tenant);
            
            if ($syncedCount > 0) {
                 $this->dispatch('toast', heading: __('Billing Sync'), text: trans_choice('{1} :count new invoice synchronized.|[2,*] :count new invoices synchronized.', $syncedCount, ['count' => $syncedCount]), variant: 'success');
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync invoices for tenant {$tenant->id}: " . $e->getMessage());
        }
        
        return view('billing::pages.manage-billing', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'invoices' => Invoice::where('tenant_id', $tenant->id)->latest()->take(10)->get(),
        ]);
    }
}
