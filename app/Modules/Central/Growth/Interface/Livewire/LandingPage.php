<?php

declare(strict_types=1);

namespace App\Modules\Central\Growth\Interface\Livewire;

use App\Modules\Central\Settings\Infrastructure\Services\CentralBranding;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.marketing')]
class LandingPage extends Component
{
    public function render(): View
    {
        return view('marketing::pages.landing-page', [
            'platformName' => CentralBranding::platformName(),
            'primaryColor' => CentralBranding::primaryColor(),
            'logoUrl' => CentralBranding::logoUrl(),
        ]);
    }
}
