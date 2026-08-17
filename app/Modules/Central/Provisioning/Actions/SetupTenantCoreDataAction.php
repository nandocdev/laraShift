<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use Database\Seeders\TenantDataSeeder;
use Illuminate\Support\Facades\DB;

final readonly class SetupTenantCoreDataAction
{
    /**
     * Initializes base data for the tenant (roles, settings, etc.).
     * In a single-db RLS architecture, this ensures the tenant has its initial state.
     */
    public function execute(Tenant $tenant): void
    {
        // When running under a rehydrated tenant context (ProvisionTenantJob),
        // RLS is already active via SET LOCAL inside the job's transaction.
        // Only fall back to a session-level config when no context is present,
        // avoiding the session-level SET that would leak across units of work.
        if (DB::getDriverName() === 'pgsql' && (! function_exists('tenancy') || ! tenancy()->initialized)) {
            DB::statement("SELECT set_config('app.tenant_id', ?, false)", [(string) $tenant->id]);
        }

        // 2. Run the data seeder for the tenant
        $seeder = new TenantDataSeeder;
        $seeder->run((string) $tenant->id);

        activity('provisioning')
            ->performedOn($tenant)
            ->log('tenant_core_data_initialized');
    }
}
