<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class ReserveTenantDomainAction
{
    /**
     * Reserves the subdomain/domain for the new tenant.
     */
    public function execute(Tenant $tenant, string $slug): void
    {
        $domain = $slug . '.' . config('tenancy.central_domain', 'larashift.test');
        
        $tenant->domains()->updateOrCreate([
            'domain' => $domain,
        ], [
            'tenant_id' => $tenant->id,
        ]);
    }
}
