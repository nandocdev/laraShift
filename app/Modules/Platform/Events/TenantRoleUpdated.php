<?php

declare(strict_types=1);

namespace App\Modules\Platform\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantRoleUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $roleId,
        public string $tenantId,
        public array $changedPermissions
    ) {}
}
