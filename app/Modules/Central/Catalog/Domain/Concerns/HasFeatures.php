<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Domain\Concerns;

use App\Modules\Central\Catalog\Application\Actions\ResolveTenantFeatures;

trait HasFeatures
{
    /**
     * Check if the tenant has access to a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        $features = app(ResolveTenantFeatures::class)->execute($this);

        return in_array($feature, $features);
    }

    /**
     * Check if the tenant has access to all given features.
     */
    public function hasAllFeatures(array $features): bool
    {
        $effective = app(ResolveTenantFeatures::class)->execute($this);

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
        $effective = app(ResolveTenantFeatures::class)->execute($this);

        foreach ($features as $feature) {
            if (in_array($feature, $effective)) {
                return true;
            }
        }

        return false;
    }
}
