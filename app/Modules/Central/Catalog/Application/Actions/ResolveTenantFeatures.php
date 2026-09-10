<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Application\Actions;

use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Support\Facades\Cache;

final readonly class ResolveTenantFeatures
{
    public function __construct(private PlanManager $plans) {}

    /**
     * @return list<string>
     */
    public function execute(TenantContract $tenant, bool $refresh = false): array
    {
        $key = $this->cacheKey($tenant);

        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 3600, function () use ($tenant) {
            $plan = $this->plans->find($tenant->getPlanSlug());
            $features = $plan->features['display_features'] ?? [];

            return is_array($features) ? array_values($features) : [];
        });
    }

    public function hasFeature(TenantContract $tenant, string $key): bool
    {
        return in_array($key, $this->execute($tenant), true);
    }

    private function cacheKey(TenantContract $tenant): string
    {
        // The plan slug is part of the key: a plan change misses the old
        // cache automatically, no explicit invalidation needed.
        return "tenant:{$tenant->getId()}:features:{$tenant->getPlanSlug()}";
    }
}
