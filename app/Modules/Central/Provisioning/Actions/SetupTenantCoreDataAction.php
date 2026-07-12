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
        // 1. Force the DB session to this tenant to bypass RLS restrictions during setup
        if (DB::getDriverName() === 'pgsql') {
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
