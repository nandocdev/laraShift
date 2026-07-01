<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class UpdateTenantAction
{
    public function execute(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($data);

        activity('provisioning')
            ->performedOn($tenant)
            ->withProperties(['updated' => array_keys($data)])
            ->log('tenant_updated');

        return $tenant;
    }
}
