<?php

declare(strict_types=1);

namespace App\Modules\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantUserJoined
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $userId,
        public string $tenantId,
        public string $viaInviteId
    ) {}
}
