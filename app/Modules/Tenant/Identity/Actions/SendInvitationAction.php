<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Tenant\Identity\Models\Invitation;
use App\Modules\Tenant\Identity\Models\Role;
use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Identity\Notifications\TenantInvitationNotification;
use Illuminate\Support\Facades\Notification;
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
        
        // 1. Check if email exists in another tenant (PRD US-T103 policy)
        $existingUser = User::withoutGlobalScope(\App\Modules\Shared\Tenancy\Models\Concerns\TenantScope::class)
            ->where('email', $email)
            ->where('tenant_id', '!=', $tenant->id)
            ->first();

        if ($existingUser) {
            throw new \Exception(__('This email is already associated with another organization. Multi-organization access requires a reactivation flow.'));
        }

        // 2. Check Quotas (US-T101: 10 pending invites by default)
        // Note: For now we use the default 10, later we can pull from Plan features.
        $pendingCount = Invitation::whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->count();

        if ($pendingCount >= 10) {
            throw new \Exception(__('Maximum limit of pending invitations reached (10).'));
        }
        
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
        Notification::route('mail', $email)
            ->notify(new TenantInvitationNotification($token, $tenant->name));

        activity('identity')
            ->performedOn($invitation)
            ->log('user_invited');

        event(new \App\Modules\Shared\Events\TenantUserInvited($invitation));

        return $invitation;
    }
}
