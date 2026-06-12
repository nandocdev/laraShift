<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Services;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\Queue\Job;

class TenantQueueManager
{
    /**
     * Resolves the queue name for a specific tenant.
     * Strategy: Use shared buckets to ensure Redis scalability and Horizon monitoring.
     * Buckets: tenant.high, tenant.default, tenant.low
     */
    public static function resolve(Tenant $tenant, string $priority = 'default'): string
    {
        // Low priority for suspended/past_due tenants as per PRD
        if ($tenant->status === 'suspended' || $tenant->status === 'past_due') {
            $priority = 'low';
        }

        return "tenant.{$priority}";
    }

    /**
     * Helper to dispatch a job to the tenant's isolated queue.
     */
    public static function dispatch(Tenant $tenant, $job, string $priority = 'default'): void
    {
        $queue = self::resolve($tenant, $priority);
        
        dispatch($job)->onQueue($queue);
    }
}
