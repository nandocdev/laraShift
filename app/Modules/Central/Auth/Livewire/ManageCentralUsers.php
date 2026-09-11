<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Livewire;

use App\Modules\Central\Auth\Models\CentralSession;
use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.central')]
class ManageCentralUsers extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $isGlobalAdmin = false;

    public function mount(): void
    {
        $this->authorize('admin-users:manage');
    }

    public function invite(): void
    {
        $this->authorize('admin-users:manage');

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:central_users,email',
            'password' => 'required|string|min:12|confirmed',
            'isGlobalAdmin' => 'boolean',
        ]);

        // Nadie crea un segundo global-admin salvo otro global-admin (ya autorizado
        // en mount), y nunca por encima del propio nivel: sin escalada posible aquí.

        $user = CentralUser::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_global_admin' => (bool) ($validated['isGlobalAdmin'] ?? false),
        ]);

        activity('auth')
            ->causedBy(auth('central')->user())
            ->performedOn($user)
            ->log('central_user_invited');

        $this->reset(['name', 'email', 'password', 'password_confirmation', 'isGlobalAdmin']);
        $this->resetPage();

        session()->flash('status', __('Admin invited successfully.'));
    }

    public function toggleAdmin(string $id): void
    {
        $this->authorize('admin-users:manage');

        $user = CentralUser::find($id);

        if (! $user || $user->id === auth('central')->id()) {
            $this->addError('role', __('You cannot change your own role.'));

            return;
        }

        $user->update(['is_global_admin' => ! $user->is_global_admin]);

        activity('auth')
            ->causedBy(auth('central')->user())
            ->performedOn($user)
            ->withProperties(['is_global_admin' => $user->is_global_admin])
            ->log('central_user_role_changed');

        session()->flash('status', __('Role updated.'));
    }

    public function disable(string $id): void
    {
        $this->authorize('admin-users:manage');

        $user = CentralUser::find($id);

        if (! $user || $user->id === auth('central')->id()) {
            $this->addError('role', __('You cannot disable your own access.'));

            return;
        }

        $user->update(['locked_until' => now()->addYears(100)]);

        activity('auth')
            ->causedBy(auth('central')->user())
            ->performedOn($user)
            ->log('central_user_disabled');

        session()->flash('status', __('Access disabled.'));
    }

    public function enable(string $id): void
    {
        $this->authorize('admin-users:manage');

        $user = CentralUser::find($id);

        if (! $user) {
            return;
        }

        $user->update(['locked_until' => null]);

        activity('auth')
            ->causedBy(auth('central')->user())
            ->performedOn($user)
            ->log('central_user_enabled');

        session()->flash('status', __('Access re-enabled.'));
    }

    public function revokeSessions(string $id): void
    {
        $this->authorize('admin-users:manage');

        $user = CentralUser::find($id);

        if (! $user || $user->id === auth('central')->id()) {
            $this->addError('role', __('You cannot revoke your own sessions here. Use logout instead.'));

            return;
        }

        CentralSession::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->get()
            ->each(fn (CentralSession $session) => $session->revoke('Admin revocation'));

        activity('auth')
            ->causedBy(auth('central')->user())
            ->performedOn($user)
            ->log('central_user_sessions_revoked');

        session()->flash('status', __('Sessions revoked.'));
    }

    public function render(): View
    {
        $users = CentralUser::withCount(['centralSessions as active_sessions' => fn ($query) => $query->whereNull('revoked_at')])
            ->latest()
            ->paginate(15);

        return view('central-auth::pages.manage-central-users', [
            'users' => $users,
        ]);
    }
}
