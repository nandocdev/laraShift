<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Shared\Infrastructure\Services\QuotaManager;
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
        
        // 1. Check Quotas (US-T101, US-T401)
        $quota = app(QuotaManager::class);
        
        if (! $quota->increment($tenant, 'invitations')) {
            throw new \Exception(__('Maximum limit of pending invitations reached for your plan.'));
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
