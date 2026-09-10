<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Application\Services;

/**
 * Reads the product capability registry (config/catalog.php).
 * Products extend it through config — never by editing core.
 *
 * @see config/catalog.php
 */
final readonly class CatalogFeatureRegistry
{
    /**
     * @return list<string>
     */
    public function featureKeys(): array
    {
        return array_keys(config('catalog.features', []));
    }

    /**
     * @return array<string, string> key => label
     */
    public function featureLabels(): array
    {
        return collect(config('catalog.features', []))
            ->mapWithKeys(fn ($meta, $key) => [$key => (string) ($meta['label'] ?? $key)])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function quotaMetrics(): array
    {
        return array_keys(config('catalog.quotas', []));
    }

    /**
     * @return array<string, string> metric => label
     */
    public function quotaLabels(): array
    {
        return collect(config('catalog.quotas', []))
            ->mapWithKeys(fn ($meta, $metric) => [$metric => (string) ($meta['label'] ?? $metric)])
            ->all();
    }
}
