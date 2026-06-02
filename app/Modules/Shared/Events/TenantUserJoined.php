<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantUserJoined
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public string $viaInviteId
    ) {}
}
