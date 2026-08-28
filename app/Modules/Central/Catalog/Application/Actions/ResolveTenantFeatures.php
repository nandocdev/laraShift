<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Application\Actions;

use App\Modules\Central\Catalog\Domain\Models\Feature;
use App\Modules\Central\Catalog\Domain\Models\TenantFeatureOverride;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\FeatureResolver;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final readonly class ResolveTenantFeatures implements FeatureResolver
{
    /**
     * Resolves and caches the effective feature set for a tenant.
     * Hierarchy: Override (Deny > Allow) -> Plan Base.
     *
     * @param  TenantContract  $tenant  The tenant instance.
     * @param  bool  $forceRefresh  Whether to force cache rebuild.
     * @return array<string> List of active feature keys.
     *
     * [RIESGOS]
     * - Cache pollution in testing environment -> Mitigated by forcing Cache::forget when running unit tests.
     * - High query volume if cache fails -> Solved by caching indefinitely (rememberForever) in production.
     */
    public function execute(TenantContract $tenant, bool $forceRefresh = false): array
    {
        // Resolve the concrete Tenant model when a non-model contract is passed
        $tenantModel = $tenant instanceof Tenant ? $tenant : Tenant::findOrFail($tenant->getId());

        $cacheKey = "tenant:{$tenantModel->id}:features";

        if ($forceRefresh || app()->runningUnitTests()) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 3600, function () use ($tenantModel) {
            // 1. Get Plan Features
            $planFeatures = Feature::whereHas('plans', function ($query) use ($tenantModel) {
                $query->withTrashed(); // Support retired plans for existing tenants
                $query->where('plans.slug', $tenantModel->plan_id);

                if (Str::isUuid($tenantModel->plan_id)) {
                    $query->orWhere('plans.id', $tenantModel->plan_id);
                }
            })
                ->where('is_active', true)
                ->pluck('key')
                ->toArray();

            // 2. Get Active Overrides
            $overrides = TenantFeatureOverride::where('tenant_id', $tenantModel->id)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->with('feature')
                ->get();

            $allowedByOverride = $overrides->where('type', 'allow')->pluck('feature.key')->toArray();
            $deniedByOverride = $overrides->where('type', 'deny')->pluck('feature.key')->toArray();

            // 3. Merge: (Plan + Allowed Overrides) - Denied Overrides
            $effectiveFeatures = array_unique(array_merge($planFeatures, $allowedByOverride));

            return array_values(array_diff($effectiveFeatures, $deniedByOverride));
        });
    }
}
