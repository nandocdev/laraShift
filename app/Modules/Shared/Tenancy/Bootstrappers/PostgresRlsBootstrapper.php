<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Bootstrappers;

use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

final class PostgresRlsBootstrapper implements TenancyBootstrapper
{
    /**
     * Set the current tenant ID in the PostgreSQL database session.
     *
     * [RIESGOS]
     * - Only executed on PostgreSQL to avoid breaking SQLite in testing environments.
     */
    public function bootstrap(Tenant $tenant): void
    {
        $tenantId = $tenant->getTenantKey();

        try {
            $connection = DB::connection();
            if ($connection->getDriverName() === 'pgsql') {
                $connection->statement("SELECT set_config('app.tenant_id', ?, false)", [(string) $tenantId]);
            }
        } catch (\Throwable $e) {
            \Log::critical("RLS Bootstrapping failed for tenant {$tenantId}: ".$e->getMessage());
        }
    }

    /**
     * Reset the tenant ID session variable.
     */
    public function revert(): void
    {
        try {
            $connection = DB::connection();
            if ($connection->getDriverName() === 'pgsql') {
                $connection->statement("SELECT set_config('app.tenant_id', '', false)");
            }
        } catch (\Throwable $e) {
            \Log::error('RLS Revert failed: '.$e->getMessage());
        }
    }
}
