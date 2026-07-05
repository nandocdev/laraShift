<?php

declare(strict_types=1);

namespace App\Modules\Platform\Events;

use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantUserJoined
{
    use Dispatchable, SerializesModels;

    public string $tenantId;
    public string $userId;

    public function __construct(
        public User $user,
        public string $viaInviteId
    ) {
        $this->tenantId = (string) $user->tenant_id;
        $this->userId = (string) $user->id;
    }
}
