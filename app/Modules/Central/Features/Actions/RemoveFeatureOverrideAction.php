<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Actions;

use App\Modules\Central\Features\Models\TenantFeatureOverride;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class RemoveFeatureOverrideAction
{
    public function __construct(
        private ResolveTenantFeaturesAction $resolveFeatures,
    ) {}

    public function execute(Tenant $tenant, string $overrideId): void
    {
        $override = TenantFeatureOverride::where('tenant_id', $tenant->id)
            ->findOrFail($overrideId);

        $featureKey = $override->feature?->key;

        activity('features')
            ->performedOn($tenant)
            ->withProperties([
                'feature_key' => $featureKey,
                'type' => $override->type,
                'actor' => auth('central')->id(),
                'removed_override_id' => $overrideId,
            ])
            ->log('feature_override_removed');

        $override->delete();

        $this->resolveFeatures->execute($tenant, true);
    }
}
