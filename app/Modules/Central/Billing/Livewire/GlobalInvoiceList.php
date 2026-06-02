<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Models\Invoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class GlobalInvoiceList extends Component
{
    use WithPagination;

    public function render(): View
    {
        return view('billing::pages.global-invoice-list', [
            'invoices' => Invoice::with('tenant')->latest()->paginate(20),
        ]);
    }
}
