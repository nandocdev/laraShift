<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Models\Concerns;

use App\Modules\Shared\Infrastructure\Services\QuotaManager;

trait HasQuotas {
    /**
     * Check if the tenant is within the quota for a specific metric.
     */
    public function withinQuota(string $metric, int $amount = 0): bool {
        $manager = app(QuotaManager::class);

        $limit = $manager->getLimit($this, $metric);

        if ($limit === -1) {
            return true;
        }

        $current = $manager->getCurrentUsage($this, $metric);

        return ($current + $amount) <= $limit;
    }

    /**
     * Get the current usage for a specific metric.
     */
    public function getUsage(string $metric): int {
        return app(QuotaManager::class)->getCurrentUsage($this, $metric);
    }

    /**
     * Get the limit for a specific metric.
     */
    public function getQuotaLimit(string $metric): int {
        return app(QuotaManager::class)->getLimit($this, $metric);
    }
}
