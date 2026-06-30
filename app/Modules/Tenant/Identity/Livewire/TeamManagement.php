<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Livewire;

use App\Modules\Shared\Events\TenantUserRevoked;
use App\Modules\Tenant\Identity\Actions\SendInvitationAction;
use App\Modules\Tenant\Identity\DTOs\InvitationData;
use App\Modules\Tenant\Identity\Models\Invitation;
use App\Modules\Tenant\Identity\Models\Role;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TeamManagement extends Component
{
    use WithPagination;

    // Invitation form state
    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    public function invite(SendInvitationAction $action): void
    {
        $this->validate([
            'inviteEmail' => 'required|email|max:255',
            'inviteRole' => 'required|exists:roles,name',
        ]);

        try {
            $action->execute(new InvitationData(
                email: $this->inviteEmail,
                roleName: $this->inviteRole
            ), auth()->user());

            $this->reset(['inviteEmail', 'inviteRole']);
            session()->flash('status', __('Invitation sent.'));
        } catch (\Exception $e) {
            $this->addError('inviteEmail', $e->getMessage());
        }
    }

    public function resendInvitation(string $id, SendInvitationAction $action): void
    {
        $oldInvite = Invitation::findOrFail($id);

        try {
            // Re-execute sending using same email and role
            $action->execute(new InvitationData(
                email: $oldInvite->email,
                roleName: $oldInvite->role->name
            ), auth()->user());

            // Delete the old one
            $oldInvite->delete();

            session()->flash('status', __('Invitation resent.'));
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelInvitation(string $id): void
    {
        $invite = Invitation::findOrFail($id);
        $invite->delete();

        activity('identity')
            ->withProperties(['email' => $invite->email])
            ->log('invitation_cancelled');

        session()->flash('status', __('Invitation cancelled.'));
    }

    // Change Role state
    public ?User $selectedMember = null;

    public string $newRole = '';

    public function selectMember(string $userId): void
    {
        $this->selectedMember = User::findOrFail($userId);
        $this->newRole = $this->selectedMember->getRoleNames()->first() ?: 'member';
    }

    public function updateRole(): void
    {
        $this->validate([
            'newRole' => 'required|exists:roles,name',
        ]);

        if ($this->selectedMember->id === auth()->id()) {
            $this->addError('newRole', __('You cannot change your own role.'));

            return;
        }

        setPermissionsTeamId(tenant('id'));
        $this->selectedMember->syncRoles([$this->newRole]);

        activity('identity')
            ->performedOn($this->selectedMember)
            ->withProperties(['new_role' => $this->newRole])
            ->log('user_role_changed');

        $this->reset(['selectedMember', 'newRole']);
        session()->flash('status', __('User role updated.'));
    }

    public function revokeAccess(string $userId): void
    {
        $user = User::findOrFail($userId);

        // Don't allow revoking self
        if ($user->id === auth()->id()) {
            return;
        }

        $user->update(['status' => 'inactive']);
        $user->delete(); // Soft delete as per US-T103

        activity('identity')
            ->performedOn($user)
            ->log('user_access_revoked');

        event(new TenantUserRevoked($user, auth()->id()));

        session()->flash('status', __('User access revoked.'));
    }

    public function render(): View
    {
        return view('identity::livewire.team-management', [
            'members' => User::with('roles')->latest()->paginate(10),
            'invitations' => Invitation::with('role')->whereNull('accepted_at')->latest()->get(),
            'availableRoles' => Role::all(),
        ]);
    }
}
