<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Actions;

use App\Modules\Central\Features\Models\Feature;
use App\Modules\Central\Features\Models\TenantFeatureOverride;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Cache;

final readonly class ResolveTenantFeaturesAction
{
    /**
     * Resolves and caches the effective feature set for a tenant.
     * Hierarchy: Override (Deny > Allow) -> Plan Base.
     * Returns an array of feature keys.
     */
    public function execute(Tenant $tenant, bool $forceRefresh = false): array
    {
        $cacheKey = "tenant:{$tenant->id}:features";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::rememberForever($cacheKey, function () use ($tenant) {
            // 1. Get Plan Features
            $planFeatures = Feature::whereHas('plans', function ($query) use ($tenant) {
                    $query->where('plans.slug', $tenant->plan_id);
                    
                    if (\Illuminate\Support\Str::isUuid($tenant->plan_id)) {
                        $query->orWhere('plans.id', $tenant->plan_id);
                    }
                })
                ->where('is_active', true)
                ->pluck('key')
                ->toArray();

            // 2. Get Active Overrides
            $overrides = TenantFeatureOverride::where('tenant_id', $tenant->id)
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
