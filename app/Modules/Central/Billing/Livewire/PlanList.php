<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Models\Plan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class PlanList extends Component
{
    public ?Plan $selectedPlan = null;

    public function showFeatures(Plan $plan): void
    {
        $this->selectedPlan = $plan->load('catalogFeatures');
    }

    public function render(): View
    {
        return view('billing::pages.plan-list', [
            'plans' => Plan::with('catalogFeatures')->get(),
        ]);
    }
}
