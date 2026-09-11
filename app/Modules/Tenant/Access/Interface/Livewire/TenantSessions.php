<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Interface\Livewire;

use App\Modules\Tenant\Access\Application\Actions\RevokeOtherTenantSessions;
use App\Modules\Tenant\Access\Domain\Models\TenantSession;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant')]
class TenantSessions extends Component
{
    use WithPagination;

    public function revokeOthers(RevokeOtherTenantSessions $action): void
    {
        $count = $action->execute(auth()->user());

        session()->flash('status', $count > 0
            ? __(':count other session(s) closed.', ['count' => $count])
            : __('No other active sessions.'));
    }

    public function render(): View
    {
        return view('identity::livewire.tenant-sessions', [
            'sessions' => TenantSession::where('user_id', auth()->id())
                ->latest()
                ->paginate(10),
            'currentSessionId' => Session::getId(),
        ]);
    }
}
