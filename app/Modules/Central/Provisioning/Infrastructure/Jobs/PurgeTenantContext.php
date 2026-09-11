<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Infrastructure\Jobs;

use App\Modules\Platform\Contracts\TenantAware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;

/**
 * Queue middleware for PurgeTenantJob only. Unlike RehydrateTenantContext,
 * it tolerates a missing stancl runtime: the sweep dispatches purges for
 * soft-deleted tenants from console (no stancl payload), and the tenant row
 * is already gone by processing time. RLS scoping via SET LOCAL still
 * applies, so tenant data can only ever match the job's own tenant id.
 */
class PurgeTenantContext
{
    public function handle(TenantAware $job, \Closure $next): void
    {
        $tenantId = $job->tenantId();

        try {
            DB::transaction(function () use ($tenantId, $job, $next) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('SET LOCAL app.tenant_id = ?', [$tenantId]);
                }

                if (function_exists('tenancy') && ! tenancy()->initialized) {
                    try {
                        tenancy()->initialize($tenantId);
                    } catch (TenantCouldNotBeIdentifiedById) {
                        Log::warning('purge.stancl_runtime_skipped', ['tenant_id' => $tenantId]);
                    }
                }

                $next($job);
            });
        } finally {
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    public function failed(TenantAware $job, \Throwable $e): void
    {
        Log::error('Purge job failed for tenant '.$job->tenantId().': '.$e->getMessage(), [
            'tenant_id' => $job->tenantId(),
            'job' => get_class($job),
        ]);
    }
}
