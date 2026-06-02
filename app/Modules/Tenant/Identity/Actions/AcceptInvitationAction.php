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
            // 1. Create or Update User
            // Since User model has BelongsToTenant, we must ensure it links correctly
            $user = User::updateOrCreate(
                ['tenant_id' => $invitation->tenant_id, 'email' => $invitation->email],
                [
                    'id' => Str::uuid()->toString(),
                    'name' => $name,
                    'password' => Hash::make($password),
                    'is_active' => true,
                ]
            );

            // 2. Assign Role
            setPermissionsTeamId($invitation->tenant_id);
            $user->assignRole($invitation->role->name);

            // 3. Mark invitation as accepted
            $invitation->update(['accepted_at' => now()]);

            activity('identity')
                ->performedOn($user)
                ->log('user_joined_via_invite');

            return $user;
        });
    }
}
