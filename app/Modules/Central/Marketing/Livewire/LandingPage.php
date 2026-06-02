<?php

declare(strict_types=1);

namespace App\Modules\Central\Marketing\Livewire;

use App\Modules\Central\Billing\Support\PlanManager;
use App\Modules\Central\Settings\Support\CentralBranding;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class LandingPage extends Component
{
    public function render(): View
    {
        return view('marketing::pages.landing-page', [
            'plans' => PlanManager::all(),
            'platformName' => CentralBranding::platformName(),
            'primaryColor' => CentralBranding::primaryColor(),
            'logoUrl' => CentralBranding::logoUrl(),
        ]);
    }
}
