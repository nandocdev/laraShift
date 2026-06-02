<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Models\Concerns;

use App\Modules\Central\Features\Actions\ResolveTenantFeaturesAction;

trait HasFeatures
{
    /**
     * Check if the tenant has access to a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        $features = app(ResolveTenantFeaturesAction::class)->execute($this);

        return in_array($feature, $features);
    }

    /**
     * Check if the tenant has access to all given features.
     */
    public function hasAllFeatures(array $features): bool
    {
        $effective = app(ResolveTenantFeaturesAction::class)->execute($this);

        foreach ($features as $feature) {
            if (! in_array($feature, $effective)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the tenant has access to at least one of the given features.
     */
    public function hasAnyFeature(array $features): bool
    {
        $effective = app(ResolveTenantFeaturesAction::class)->execute($this);

        foreach ($features as $feature) {
            if (in_array($feature, $effective)) {
                return true;
            }
        }

        return false;
    }
}
