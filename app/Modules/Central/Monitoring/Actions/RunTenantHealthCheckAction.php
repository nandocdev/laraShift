<?php

declare(strict_types=1);

namespace App\Modules\Central\Monitoring\Actions;

use App\Modules\Central\Monitoring\Models\TenantHealthCheck;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class RunTenantHealthCheckAction
{
    /**
     * Run a health check for a specific tenant.
     * Checks: tenancy initialization, database connectivity, and basic model access.
     */
    public function execute(Tenant $tenant): TenantHealthCheck
    {
        try {
            tenancy()->initialize($tenant);

            $canQuery = DB::table('tenant_settings')->count();

            $status = 'pass';
            $message = 'Tenant is healthy';
            $details = [
                'tenant_initialized' => true,
                'db_query_ok' => true,
            ];

            tenancy()->end();

            $this->alertOnFailure($tenant, false);
        } catch (\Throwable $e) {
            $status = 'fail';
            $message = $e->getMessage();
            $details = [
                'tenant_initialized' => false,
                'db_query_ok' => false,
                'error' => $e->getMessage(),
            ];

            $this->alertOnFailure($tenant, true, $e->getMessage());
        }

        return TenantHealthCheck::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'check_type' => 'tenant_availability',
            'status' => $status,
            'message' => $message,
            'details' => $details,
        ]);
    }

    private function alertOnFailure(Tenant $tenant, bool $isFailed, ?string $error = null): void
    {
        if (! $isFailed) {
            return;
        }

        Log::critical('Tenant health check FAILED', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'error' => $error,
        ]);

        activity('monitoring')
            ->performedOn($tenant)
            ->withProperties(['error' => $error, 'check_type' => 'tenant_availability'])
            ->log('tenant_health_check_failed');
    }
}
