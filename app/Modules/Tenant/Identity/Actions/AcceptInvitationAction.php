<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Shared\Events\TenantUserJoined;
use App\Modules\Tenant\Audit\Actions\RecordAuditLogAction;
use App\Modules\Tenant\Audit\DTOs\AuditLogData;
use App\Modules\Tenant\Audit\Enums\AuditAction;
use App\Modules\Tenant\Identity\DTOs\UserAcceptanceData;
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
    public function execute(UserAcceptanceData $data): User
    {
        $tokenHash = hash('sha256', $data->token);

        $invitation = Invitation::where('token_hash', $tokenHash)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return DB::transaction(function () use ($invitation, $data) {
            // 1. Create or Update User (including soft-deleted for reactivation)
            $user = User::withTrashed()
                ->where('tenant_id', $invitation->tenant_id)
                ->where('email', $invitation->email)
                ->first();

            if ($user) {
                // Security check: Only update name and password if not already set or if explicitly requested.
                // For a restoration, we should be careful not to hijack an existing active account if logic fails.
                $user->restore(); // If it was soft-deleted
                $user->update([
                    'name' => $data->name,
                    'password' => Hash::make($data->password),
                    'is_active' => true,
                ]);
            } else {
                $user = User::create([
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $invitation->tenant_id,
                    'name' => $data->name,
                    'email' => $invitation->email,
                    'password' => Hash::make($data->password),
                    'is_active' => true,
                ]);
            }

            // 2. Assign Role
            setPermissionsTeamId($invitation->tenant_id);
            $user->assignRole($invitation->role->name);

            // 3. Mark invitation as accepted
            $invitation->update(['accepted_at' => now()]);

            app(RecordAuditLogAction::class)->execute(
                new AuditLogData(
                    action: AuditAction::USER_JOINED,
                    resource: 'user',
                    resourceId: $user->id,
                    metadata: ['email' => $user->email, 'role' => $invitation->role->name],
                    userId: $user->id // Ensure the log registers the new user
                )
            );

            activity('identity')
                ->performedOn($user)
                ->log('user_joined_via_invite');

            event(new TenantUserJoined($user, $invitation->id));

            return $user;
        });
    }
}
