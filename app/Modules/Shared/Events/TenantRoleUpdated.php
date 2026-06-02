<?php

declare(strict_types=1);

namespace App\Modules\Shared\Events;

use App\Modules\Tenant\Identity\Models\Role;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantRoleUpdated
{
    use Dispatchable, SerializesModels;

    public string $tenantId;
    public string $roleId;

    public function __construct(
        public Role $role,
        public array $changedPermissions
    ) {
        $this->tenantId = (string) $role->tenant_id;
        $this->roleId = (string) $role->id;
    }
}
