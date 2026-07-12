<?php

declare(strict_types=1);

namespace App\Modules\Platform\Security\RateLimiting;

use App\Modules\Platform\Contracts\TenantContract;

class TenantRateLimiter
{
    /**
     * Resolves the rate limit RPM for a tenant based on their plan quota.
     */
    public function resolveLimit(TenantContract $tenant, int $default = 60): int
    {
        $limit = $tenant->getQuotaLimit('rate_limit_rpm');

        return $limit > 0 ? $limit : $default;
    }

    /**
     * Builds the rate limiter cache key for a tenant.
     */
    public function key(string $tenantId, string $prefix = 'tenant_rate_limit'): string
    {
        return "{$prefix}:{$tenantId}";
    }
}
