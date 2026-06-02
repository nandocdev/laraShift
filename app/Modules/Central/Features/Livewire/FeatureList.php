<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Livewire;

use App\Modules\Central\Features\Models\Feature;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class FeatureList extends Component
{
    use WithPagination;

    public function render(): View
    {
        return view('features::pages.feature-list', [
            'features' => Feature::orderBy('module')->orderBy('key')->paginate(20),
        ]);
    }
}
