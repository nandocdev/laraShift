<?php

declare(strict_types=1);

namespace App\Modules\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantUserInvited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $invitationId,
        public string $tenantId,
        public string $inviterId,
        public string $email,
        public string $roleId
    ) {}
}
