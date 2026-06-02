<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class SwitchMaintenanceModeAction
{
    public function execute(Tenant $tenant, bool $enabled): void
    {
        $tenant->update([
            'maintenance_mode' => $enabled,
            'status' => $enabled ? 'maintenance' : 'active',
        ]);

        activity('provisioning')
            ->performedOn($tenant)
            ->withProperties(['maintenance_mode' => $enabled])
            ->log($enabled ? 'tenant_maintenance_enabled' : 'tenant_maintenance_disabled');
    }
}
