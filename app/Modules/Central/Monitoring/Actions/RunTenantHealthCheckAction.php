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
    public function execute(Tenant $tenant): TenantHealthCheck
    {
        $status = 'pass';
        $message = 'Tenant is healthy';
        $details = [
            'tenant_initialized' => true,
            'db_query_ok' => true,
        ];

        try {
            tenancy()->initialize($tenant);

            DB::table('tenant_settings')->count();

            tenancy()->end();
        } catch (\Throwable $e) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            $status = 'fail';
            $message = $e->getMessage();
            $details = [
                'tenant_initialized' => false,
                'db_query_ok' => false,
                'error' => $e->getMessage(),
            ];

            Log::critical('Tenant health check FAILED', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'error' => $e->getMessage(),
            ]);

            activity('monitoring')
                ->performedOn($tenant)
                ->withProperties(['error' => $e->getMessage(), 'check_type' => 'tenant_availability'])
                ->log('tenant_health_check_failed');
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
}
