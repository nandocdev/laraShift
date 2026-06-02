<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Identity\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Models\Role;
use Illuminate\Support\Str;

final readonly class EnsureTenantRolesExistAction
{
    /**
     * Ensures that the default system roles exist for a given tenant.
     */
    public function execute(Tenant $tenant): void
    {
        $tenantId = $tenant->getTenantKey();

        // Admin Role
        Role::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'admin', 'guard_name' => 'web'],
            [
                'id' => Str::uuid()->toString(),
                'is_system' => true,
            ]
        );

        // Member Role
        Role::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'member', 'guard_name' => 'web'],
            [
                'id' => Str::uuid()->toString(),
                'is_system' => true,
            ]
        );
    }
}
