<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Access\Application\Actions;

use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Tenant\Access\Domain\Models\Role;
use Illuminate\Support\Str;

final readonly class EnsureTenantRolesExist
{
    /**
     * Ensures that the default system roles exist for a given tenant.
     */
    public function execute(TenantContract $tenant): void
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
