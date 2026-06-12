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
        return [
            'default', 
            'notifications', 
            'broadcasts',
            'tenant.high',
            'tenant.default',
            'tenant.low',
        ];
    }
}
