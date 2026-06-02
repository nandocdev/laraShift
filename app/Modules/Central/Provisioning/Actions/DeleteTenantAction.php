<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Jobs\PurgeTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class DeleteTenantAction
{
    /**
     * Deletes a tenant.
     * 
     * @param bool $hardDelete If true, dispatches a background purge job.
     */
    public function execute(Tenant $tenant, bool $hardDelete = false): void
    {
        $id = $tenant->id;
        $slug = $tenant->slug;

        if ($hardDelete) {
            // US-103: Purge is completed in a background job
            PurgeTenantJob::dispatch($id, $slug);
            
            activity('provisioning')
                ->performedOn($tenant)
                ->withProperties(['slug' => $slug, 'hard_delete' => true])
                ->log('tenant_hard_delete_queued');
        } else {
            $tenant->delete();

            activity('provisioning')
                ->performedOn($tenant)
                ->log('tenant_soft_deleted');
        }
    }
}
