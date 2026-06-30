<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

use App\Modules\Tenant\Identity\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantUserInvited
{
    use Dispatchable, SerializesModels;

    public string $tenantId;

    public string $inviterId;

    public string $email;

    public string $roleId;

    public function __construct(public Invitation $invitation)
    {
        $this->tenantId = (string) $invitation->tenant_id;
        $this->inviterId = (string) $invitation->invited_by;
        $this->email = $invitation->email;
        $this->roleId = (string) $invitation->role_id;
    }
}
