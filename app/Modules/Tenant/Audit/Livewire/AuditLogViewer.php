<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Livewire;

use App\Modules\Tenant\Audit\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AuditLogViewer extends Component
{
    use WithPagination;

    public function render(): View
    {
        return view('audit::pages.viewer', [
            'logs' => AuditLog::with('user')->latest()->paginate(50),
        ]);
    }
}
