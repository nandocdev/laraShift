<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Tenant\Identity\Models\Invitation;
use App\Modules\Tenant\Identity\Models\Role;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class SendInvitationAction
{
    /**
     * Sends an invitation to a new or existing user.
     */
    public function execute(
        string $email,
        string $roleName,
        User $inviter
    ): Invitation {
        $tenant = tenant();
        
        // 1. Check Quotas (US-T101)
        // TODO: Integration with Quotas module
        
        // 2. Resolve Role
        $role = Role::where('name', $roleName)->firstOrFail();

        // 3. Generate token
        $token = Str::random(64);

        // 4. Create Invitation
        $invitation = Invitation::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'email' => $email,
            'role_id' => $role->id,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addHours(48),
        ]);

        // 5. Send Notification
        // $invitation->notify(new TenantInvitationNotification($token));

        activity('identity')
            ->performedOn($invitation)
            ->log('user_invited');

        return $invitation;
    }
}
