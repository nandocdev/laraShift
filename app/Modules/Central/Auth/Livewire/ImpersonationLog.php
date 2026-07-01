<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Livewire;

use App\Modules\Central\Support\Models\SupportSession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class ImpersonationLog extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public function render(): View
    {
        $query = SupportSession::with(['operator', 'tenant'])
            ->latest('started_at');

        if ($this->filterStatus === 'active') {
            $query->whereNull('ended_at');
        } elseif ($this->filterStatus === 'ended') {
            $query->whereNotNull('ended_at');
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('operator', function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                })->orWhereHas('tenant', function ($q) {
                    $q->where('name', 'like', "%{$this->search}%");
                })->orWhere('reason', 'like', "%{$this->search}%");
            });
        }

        return view('central-auth::pages.impersonation-log', [
            'sessions' => $query->paginate(20),
        ]);
    }
}
