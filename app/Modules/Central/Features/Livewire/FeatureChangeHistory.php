<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Livewire;

use App\Modules\Shared\Models\Activity;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class FeatureChangeHistory extends Component
{
    use WithPagination;

    public function render(): View
    {
        $logs = Activity::whereIn('log_name', ['features'])
            ->latest()
            ->paginate(50);

        return view('features::pages.feature-history', [
            'logs' => $logs,
        ]);
    }
}
