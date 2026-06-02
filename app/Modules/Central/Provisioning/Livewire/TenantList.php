<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Livewire;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class TenantList extends Component
{
    use WithPagination;

    public function render(): View
    {
        return view('provisioning::pages.tenant-list', [
            'tenants' => Tenant::with('domains')->latest()->paginate(10),
        ]);
    }
}
