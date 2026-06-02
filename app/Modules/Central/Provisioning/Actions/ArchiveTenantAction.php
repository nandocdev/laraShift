<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Carbon;

final readonly class ArchiveTenantAction
{
    public function execute(Tenant $tenant): void
    {
        $tenant->update([
            'status' => 'archived',
            'archived_at' => Carbon::now(),
            'read_only' => true,
        ]);

        activity('provisioning')
            ->performedOn($tenant)
            ->log('tenant_archived');
    }
}
