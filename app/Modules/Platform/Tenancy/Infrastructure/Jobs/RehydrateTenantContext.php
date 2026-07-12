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

        DB::transaction(function () use ($tenantId, $job, $next) {
            DB::statement('SET LOCAL app.tenant_id = ?', [$tenantId]);

            if (function_exists('tenancy') && ! tenancy()->initialized) {
                tenancy()->initialize($tenantId);
            }

            $next($job);
        });
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
