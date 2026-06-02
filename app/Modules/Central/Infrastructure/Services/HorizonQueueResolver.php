<?php

declare(strict_types=1);

namespace App\Modules\Central\Infrastructure\Services;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class HorizonQueueResolver
{
    /**
     * Resolves the list of queues Horizon should monitor.
     * Includes default platform queues and dynamic tenant queues.
     * 
     * [PERFORMANCE]
     * - Uses Cache to avoid DB queries on every Horizon loop if needed.
     * - Safely checks for table existence to avoid errors during migrations.
     */
    public static function resolve(): array
    {
        $baseQueues = ['default', 'notifications', 'broadcasts'];

        try {
            // Safety check for CLI/Migrations
            if (! Schema::hasTable('tenants')) {
                return $baseQueues;
            }

            $tenantQueues = Cache::remember('horizon_tenant_queues', 60, function () {
                return Tenant::whereIn('status', ['active', 'suspended', 'past_due'])
                    ->pluck('slug')
                    ->flatMap(function ($slug) {
                        return [
                            "tenant.{$slug}.default",
                            "tenant.{$slug}.low",
                        ];
                    })
                    ->toArray();
            });

            return array_merge($baseQueues, $tenantQueues);
        } catch (\Exception $e) {
            return $baseQueues;
        }
    }
}
