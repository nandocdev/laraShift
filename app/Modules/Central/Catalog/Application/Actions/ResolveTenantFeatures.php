<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Application\Actions;

use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Platform\Contracts\TenantContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final readonly class ResolveTenantFeatures
{
    public function __construct(private PlanManager $plans) {}

    /**
     * @return list<string>
     */
    public function execute(TenantContract $tenant, bool $refresh = false): array
    {
        try {
            $plan = $this->plans->find($tenant->getPlanSlug());
        } catch (ModelNotFoundException) {
            Log::warning('billing.plan_not_found', ['tenant_id' => (string) $tenant->getId(), 'plan_slug' => $tenant->getPlanSlug()]);

            return [];
        }

        // A content hash is part of the key: editing a plan in ManagePlans
        // misses the old cache automatically (timestamps only have
        // second precision, so updated_at alone can collide).
        $key = 'tenant:'.$tenant->getId().':features:'.$plan->slug.':'.md5((string) json_encode($plan->features));

        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 3600, function () use ($plan) {
            $features = $plan->features['display_features'] ?? [];

            return is_array($features) ? array_values($features) : [];
        });
    }

    public function hasFeature(TenantContract $tenant, string $key): bool
    {
        return in_array($key, $this->execute($tenant), true);
    }
}
