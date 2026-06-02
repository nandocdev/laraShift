<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Tenant\Identity\Models\Invitation;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class AcceptInvitationAction
{
    /**
     * Accepts an invitation and creates or links the user.
     */
    public function execute(string $token, string $name, string $password): User
    {
        $tokenHash = hash('sha256', $token);
        
        $invitation = Invitation::where('token_hash', $tokenHash)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return DB::transaction(function () use ($invitation, $name, $password) {
            // 1. Create or Update User (including soft-deleted for reactivation)
            $user = User::withTrashed()
                ->where('tenant_id', $invitation->tenant_id)
                ->where('email', $invitation->email)
                ->first();

            if ($user) {
                $user->restore(); // If it was soft-deleted
                $user->update([
                    'name' => $name,
                    'password' => Hash::make($password),
                    'is_active' => true,
                ]);
            } else {
                $user = User::create([
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $invitation->tenant_id,
                    'name' => $name,
                    'email' => $invitation->email,
                    'password' => Hash::make($password),
                    'is_active' => true,
                ]);
            }

            // 2. Assign Role
            setPermissionsTeamId($invitation->tenant_id);
            $user->assignRole($invitation->role->name);

            // 3. Mark invitation as accepted
            $invitation->update(['accepted_at' => now()]);

            activity('identity')
                ->performedOn($user)
                ->log('user_joined_via_invite');

            event(new \App\Modules\Shared\Events\TenantUserJoined($user, $invitation->id));

            return $user;
        });
    }
}
