<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Services;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\Queue\Job;

class TenantQueueManager
{
    /**
     * Resolves the queue name for a specific tenant.
     * Rule: tenant.{slug}.{priority}
     */
    public static function resolve(Tenant $tenant, string $priority = 'default'): string
    {
        // Low priority for suspended/past_due tenants as per PRD
        if ($tenant->status === 'suspended' || $tenant->status === 'past_due') {
            $priority = 'low';
        }

        return "tenant.{$tenant->slug}.{$priority}";
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
