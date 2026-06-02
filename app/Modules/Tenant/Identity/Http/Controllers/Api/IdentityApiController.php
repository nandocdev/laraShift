<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Identity\Actions\SendInvitationAction;
use App\Modules\Tenant\Identity\Models\Invitation;
use App\Modules\Tenant\Identity\Models\Role;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IdentityApiController extends Controller
{
    /**
     * List all team members.
     */
    public function listMembers(): JsonResponse
    {
        Gate::authorize('team:read');

        $members = User::with('roles')->latest()->get()->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'is_active' => $user->is_active,
            'joined_at' => $user->created_at->toIso8601String(),
        ]);

        return response()->json($members);
    }

    /**
     * Invite a new member.
     */
    public function inviteMember(Request $request, SendInvitationAction $action): JsonResponse
    {
        Gate::authorize('team:manage');

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|exists:roles,name',
        ]);

        try {
            $invitation = $action->execute(
                $validated['email'],
                $validated['role'],
                auth()->user()
            );

            return response()->json([
                'message' => 'Invitation sent.',
                'invitation_id' => $invitation->id,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * List pending invitations.
     */
    public function listInvitations(): JsonResponse
    {
        Gate::authorize('team:manage');

        $invitations = Invitation::with('role')
            ->whereNull('accepted_at')
            ->latest()
            ->get()
            ->map(fn ($invite) => [
                'id' => $invite->id,
                'email' => $invite->email,
                'role' => $invite->role->name,
                'expires_at' => $invite->expires_at->toIso8601String(),
                'is_expired' => $invite->expires_at->isPast(),
            ]);

        return response()->json($invitations);
    }

    /**
     * Revoke member access.
     */
    public function revokeMember(string $id): JsonResponse
    {
        Gate::authorize('team:manage');

        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot revoke your own access.'], 400);
        }

        $user->update(['is_active' => false]);
        $user->delete(); // Soft delete

        return response()->json(['message' => 'Member access revoked.']);
    }

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(string $id): JsonResponse
    {
        Gate::authorize('team:manage');

        $invite = Invitation::findOrFail($id);
        $invite->delete();

        return response()->json(['message' => 'Invitation cancelled.']);
    }

    /**
     * Change a member's role.
     */
    public function updateMemberRole(Request $request, string $id): JsonResponse
    {
        Gate::authorize('team:manage');

        $validated = $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::findOrFail($id);
        
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot change your own role via API.'], 400);
        }

        setPermissionsTeamId(tenant('id'));
        $user->syncRoles([$validated['role']]);

        return response()->json(['message' => 'Member role updated.']);
    }

    /**
     * Create a new custom role.
     */
    public function createRole(Request $request): JsonResponse
    {
        Gate::authorize('roles:manage');

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:100|unique:roles,name',
            'permissions' => 'array',
        ]);

        $role = Role::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'tenant_id' => tenant('id'),
            'name' => $validated['name'],
            'guard_name' => 'web',
            'is_system' => false,
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'message' => 'Role created.',
            'role_id' => $role->id,
        ], 201);
    }

    /**
     * List all roles.
     */
    public function listRoles(): JsonResponse
    {
        Gate::authorize('roles:manage');

        $roles = Role::with('permissions')->get()->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'is_system' => $role->is_system,
            'permissions' => $role->permissions->pluck('name'),
        ]);

        return response()->json($roles);
    }

    /**
     * Delete a custom role.
     */
    public function deleteRole(string $id): JsonResponse
    {
        Gate::authorize('roles:manage');

        $role = Role::findOrFail($id);

        if ($role->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 400);
        }

        if ($role->users()->exists()) {
            return response()->json(['message' => 'Cannot delete role with active users.'], 409);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }
}
