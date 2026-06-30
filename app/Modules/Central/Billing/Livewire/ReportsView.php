<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Actions\FetchBillingReportsAction;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class ReportsView extends Component
{
    public string $period = 'this_month';

    public function render(FetchBillingReportsAction $action): View
    {
        return view('billing::pages.reports-view', $action->execute($this->period));
    }
}
