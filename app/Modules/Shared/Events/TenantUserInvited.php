<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

use App\Modules\Tenant\Identity\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantUserInvited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Invitation $invitation
    ) {}
}
