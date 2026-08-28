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
        // Ensure RLS context is set via SET LOCAL inside a transaction (never session-level SET)
        // When running under RehydrateTenantContext the transaction is already active and SET LOCAL is set.
        // Fallback path (tinker/artisan without tenancy) wraps seeder in explicit transaction + SET LOCAL.
        $needsTransaction = DB::getDriverName() === 'pgsql' && (! function_exists('tenancy') || ! tenancy()->initialized);

        if ($needsTransaction) {
            DB::transaction(function () use ($tenant) {
                DB::statement("SELECT set_config('app.tenant_id', ?, true)", [(string) $tenant->id]);
                $seeder = new TenantDataSeeder;
                $seeder->run((string) $tenant->id);
            });
        } else {
            // 2. Run the data seeder for the tenant
            $seeder = new TenantDataSeeder;
            $seeder->run((string) $tenant->id);
        }

        activity('provisioning')
            ->performedOn($tenant)
            ->log('tenant_core_data_initialized');
    }
}
