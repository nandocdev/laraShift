<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class TenantInvoiceList extends Component
{
    use WithPagination;

    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function render(): View
    {
        return view('billing::pages.tenant-invoice-list', [
            'invoices' => Invoice::where('tenant_id', $this->tenant->id)->latest()->paginate(10),
        ]);
    }
}
