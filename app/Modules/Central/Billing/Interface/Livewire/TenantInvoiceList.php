<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Livewire;

use App\Modules\Central\Billing\Domain\Models\Invoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TenantInvoiceList extends Component
{
    use WithPagination;

    public function render(): View
    {
        return view('billing::livewire.tenant-invoice-list', [
            'invoices' => Invoice::where('tenant_id', tenant()->getId())->latest()->paginate(15),
        ]);
    }
}
