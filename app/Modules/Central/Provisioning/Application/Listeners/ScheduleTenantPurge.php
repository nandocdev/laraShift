<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Application\Listeners;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Events\TenantClosureRequested;
use Illuminate\Support\Carbon;

class ScheduleTenantPurge
{
    /**
     * Archives and soft-deletes the tenant immediately (inaccessible from
     * now on). The physical purge runs via the tenants:purge-closed sweep
     * after the grace window: a delayed dispatch would carry a stancl
     * payload for a soft-deleted tenant and fail at processing time.
     */
    public function handle(TenantClosureRequested $event): void
    {
        $tenant = Tenant::find($event->tenant->getId());

        if (! $tenant || $tenant->trashed()) {
            return;
        }

        $tenant->update([
            'status' => 'archived',
            'archived_at' => Carbon::now(),
            'read_only' => true,
        ]);

        $tenant->delete();

        activity('provisioning')
            ->performedOn($tenant)
            ->withProperties(['requested_by' => $event->requestedByUserId, 'grace_days' => $event->graceDays])
            ->log('tenant_closure_requested');
    }
}
