<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Workspace\Interface\Livewire;

use App\Modules\Tenant\Access\Application\Actions\CancelTenantInvitation;
use App\Modules\Tenant\Access\Application\Actions\RevokeTenantUserAccess;
use App\Modules\Tenant\Access\Application\Actions\SendInvitation;
use App\Modules\Tenant\Access\Application\Actions\UpdateTenantUserRole;
use App\Modules\Tenant\Access\Application\DTO\InvitationData;
use App\Modules\Tenant\Access\Domain\Models\Invitation;
use App\Modules\Tenant\Access\Domain\Models\Role;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TeamManagement extends Component
{
    use WithPagination;

    // Invitation form state
    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    // Change Role state
    #[Locked]
    public ?string $selectedMemberId = null;

    public ?User $selectedMember = null;

    public string $newRole = '';

    public function invite(SendInvitation $action): void
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

    public function resendInvitation(string $id, SendInvitation $action): void
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

    public function cancelInvitation(string $id, CancelTenantInvitation $action): void
    {
        $invite = Invitation::findOrFail($id);
        $action->execute($invite, auth()->user());

        session()->flash('status', __('Invitation cancelled.'));
    }

    public function selectMember(string $userId): void
    {
        $this->selectedMember = User::findOrFail($userId);
        $this->selectedMemberId = $this->selectedMember->id;
        $this->newRole = $this->selectedMember->getRoleNames()->first() ?: 'member';
    }

    public function updateRole(UpdateTenantUserRole $action): void
    {
        $this->validate([
            'newRole' => 'required|exists:roles,name',
        ]);

        if (! $this->selectedMemberId) {
            return;
        }

        $targetUser = User::findOrFail($this->selectedMemberId);

        try {
            $action->execute($targetUser, $this->newRole, auth()->user());

            $this->reset(['selectedMember', 'selectedMemberId', 'newRole']);
            session()->flash('status', __('User role updated.'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function revokeAccess(string $userId, RevokeTenantUserAccess $action): void
    {
        $user = User::findOrFail($userId);

        try {
            $action->execute($user, auth()->user());
            session()->flash('status', __('User access revoked.'));
        } catch (ValidationException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('workspace::livewire.team-management', [
            'members' => User::with('roles')->latest()->paginate(10, ['*'], 'members_page'),
            'invitations' => Invitation::with('role')->whereNull('accepted_at')->latest()->paginate(10, ['*'], 'invitations_page'),
            'availableRoles' => Role::all(),
        ]);
    }
}
