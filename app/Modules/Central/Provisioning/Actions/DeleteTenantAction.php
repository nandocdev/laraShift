<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class DeleteTenantAction
{
    public function execute(Tenant $tenant, bool $hardDelete = false): void
    {
        if ($hardDelete) {
            $tenant->forceDelete();
            
            activity('provisioning')
                ->withProperties(['id' => $tenant->id, 'hard_delete' => true])
                ->log('tenant_hard_deleted');
        } else {
            $tenant->delete();

            activity('provisioning')
                ->performedOn($tenant)
                ->log('tenant_soft_deleted');
        }
    }
}
