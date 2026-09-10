<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Application\Services;

use App\Modules\Central\Catalog\Application\Actions\ResolveTenantFeatures;
use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Platform\Contracts\TenantFeatureResolver;

final readonly class CatalogTenantFeatureResolver implements TenantFeatureResolver
{
    public function __construct(private ResolveTenantFeatures $features) {}

    public function hasFeature(TenantContract $tenant, string $key): bool
    {
        return $this->features->hasFeature($tenant, $key);
    }
}
