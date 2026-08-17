<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Support\Facades;

use App\Modules\Platform\Metering\Application\Services\MeteringManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Modules\Platform\Metering\Domain\Models\UsageEvent|null record(\App\Modules\Platform\Metering\Application\DTO\RecordUsageData $data, ?\App\Modules\Platform\Contracts\TenantContract $tenant = null)
 * @method static int usage(\App\Modules\Platform\Contracts\TenantContract $tenant, string $meter, ?string $period = null)
 * @method static \App\Modules\Platform\Metering\Domain\Models\UsageRollup|null rollup(\App\Modules\Platform\Contracts\TenantContract $tenant, string $meter, string $period)
 * @method static void reset(\App\Modules\Platform\Contracts\TenantContract $tenant, string $meter, ?string $period = null)
 * @method static void aggregate(string $tenantId, string $period)
 *
 * @see MeteringManager
 */
class Meter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MeteringManager::class;
    }
}
