<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Services;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Contracts\Queue\Job;

class TenantQueueManager
{
    /**
     * Resolves the queue name for a specific tenant.
     * Strategy: Use shared buckets to ensure Redis scalability and prevent Noisy Neighbor.
     * Queues: tenant.b{1-5}.{priority}
     */
    public static function resolve(Tenant $tenant, string $priority = 'default'): string
    {
        // Low priority for suspended/past_due tenants
        if ($tenant->status === 'suspended' || $tenant->status === 'past_due') {
            $priority = 'low';
        }

        // Hashing the UUID to a bucket number (1-5)
        $bucket = (crc32($tenant->id) % 5) + 1;

        return "tenant.b{$bucket}.{$priority}";
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
