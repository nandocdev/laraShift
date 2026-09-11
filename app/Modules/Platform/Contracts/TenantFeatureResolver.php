<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

/**
 * Answers whether the tenant's current plan includes a feature key.
 */
interface TenantFeatureResolver
{
    public function hasFeature(TenantContract $tenant, string $key): bool;
}
