<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Livewire;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('central-auth::pages.dashboard', [
            'tenantCount' => Tenant::count(),
        ]);
    }
}
