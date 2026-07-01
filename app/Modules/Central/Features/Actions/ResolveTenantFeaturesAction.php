<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Actions;

use App\Modules\Central\Features\Models\Feature;
use App\Modules\Central\Features\Models\TenantFeatureOverride;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Infrastructure\Services\QuotaManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ResolveTenantFeaturesAction
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Resolves and caches the effective feature set for a tenant.
     * Hierarchy: Targeting -> Override (Deny > Allow) -> Plan Base.
     */
    public function execute(Tenant $tenant, bool $forceRefresh = false): array
    {
        $cacheKey = "tenant:{$tenant->id}:features";

        if ($forceRefresh || app()->runningUnitTests()) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenant) {
            // 1. Get Plan Features
            $planFeatures = Feature::whereHas('plans', function ($query) use ($tenant) {
                $query->withTrashed();
                $query->where('plans.slug', $tenant->plan_id);

                if (Str::isUuid($tenant->plan_id)) {
                    $query->orWhere('plans.id', $tenant->plan_id);
                }
            })
                ->where('is_active', true)
                ->get();

            // 2. Evaluate targeting rules against tenant attributes
            $effectiveByRule = $planFeatures->filter(function (Feature $feature) use ($tenant) {
                return $this->matchesTargeting($feature, $tenant);
            })->pluck('key')->toArray();

            // 3. Get Active Overrides
            $overrides = TenantFeatureOverride::where('tenant_id', $tenant->id)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->with('feature')
                ->get();

            $allowedByOverride = $overrides->where('type', 'allow')->pluck('feature.key')->toArray();
            $deniedByOverride = $overrides->where('type', 'deny')->pluck('feature.key')->toArray();

            // 4. Merge: (Targeted Plan Features + Allowed Overrides) - Denied Overrides
            $effectiveFeatures = array_unique(array_merge($effectiveByRule, $allowedByOverride));

            return array_values(array_diff($effectiveFeatures, $deniedByOverride));
        });
    }

    /**
     * Evaluate feature targeting rules against the tenant.
     */
    private function matchesTargeting(Feature $feature, Tenant $tenant): bool
    {
        $targeting = $feature->targeting;

        if (empty($targeting)) {
            return true;
        }

        // Region targeting
        if (! empty($targeting['regions'])) {
            $tenantRegion = $this->getTenantRegion($tenant);

            if ($tenantRegion === null || ! in_array($tenantRegion, $targeting['regions'])) {
                return false;
            }
        }

        // Staff count targeting
        if (isset($targeting['staff_min']) || isset($targeting['staff_max'])) {
            $staffCount = $this->getTenantStaffCount($tenant);
            $min = $targeting['staff_min'] ?? 0;
            $max = $targeting['staff_max'] ?? PHP_INT_MAX;

            if ($staffCount < $min || $staffCount > $max) {
                return false;
            }
        }

        // Tenancy age targeting
        if (isset($targeting['min_tenancy_days'])) {
            $createdAt = DB::table('tenants')
                ->where('id', $tenant->id)
                ->value('created_at');

            $tenancyDays = $createdAt ? abs(now()->diffInDays(Carbon::parse($createdAt))) : 0;

            if ($tenancyDays < $targeting['min_tenancy_days']) {
                return false;
            }
        }

        return true;
    }

    private function getTenantRegion(Tenant $tenant): ?string
    {
        $raw = DB::table('tenants')
            ->where('id', $tenant->id)
            ->value('data');

        $data = $raw ? (array) json_decode($raw, true) : [];

        return $data['region'] ?? $data['country'] ?? null;
    }

    private function getTenantStaffCount(Tenant $tenant): int
    {
        return app(QuotaManager::class)->getCurrentUsage($tenant, 'staff');
    }
}
