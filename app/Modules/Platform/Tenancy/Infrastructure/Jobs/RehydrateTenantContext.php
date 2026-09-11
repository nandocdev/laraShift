<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Infrastructure\Jobs;

use App\Modules\Platform\Contracts\TenantAware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RehydrateTenantContext
{
    /**
     * Handle the job middleware.
     *
     * Starts a DB transaction, sets the RLS tenant context via SET LOCAL,
     * and initializes tenancy before delegating to the job handle().
     * On completion the transaction commits, which resets SET LOCAL
     * automatically (per PostgreSQL behavior).
     */
    public function handle(TenantAware $job, \Closure $next): void
    {
        $tenantId = $job->tenantId();

        try {
            DB::transaction(function () use ($tenantId, $job, $next) {
                // RLS context only applies to PostgreSQL; on other drivers (e.g.
                // SQLite tests) there is no policy to satisfy.
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('SET LOCAL app.tenant_id = ?', [$tenantId]);
                }

                if (function_exists('tenancy') && ! tenancy()->initialized) {
                    tenancy()->initialize($tenantId);
                }

                $next($job);
            });
        } finally {
            // Always tear down the tenant context: under Octane/PgBouncer the
            // worker process is reused, and a lingering context would leak the
            // tenant_id of this job into the next unit of work.
            if (function_exists('tenancy') && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * Called when the job fails after retries.
     */
    public function failed(TenantAware $job, \Throwable $e): void
    {
        Log::error('Job failed for tenant '.$job->tenantId().': '.$e->getMessage(), [
            'tenant_id' => $job->tenantId(),
            'job' => get_class($job),
        ]);
    }
}
