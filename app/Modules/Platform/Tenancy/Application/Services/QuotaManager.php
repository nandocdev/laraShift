<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Application\Services;

use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Platform\Tenancy\Infrastructure\Notifications\QuotaThresholdReachedNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QuotaManager
{
    private const KEY_PREFIX = 'quota';

    /**
     * Increments a metric and checks if it exceeds the plan limit.
     */
    public function increment(TenantContract $tenant, string $metric, int $amount = 1): bool
    {
        $limit = $this->getLimit($tenant, $metric);

        if ($limit === -1) {
            $this->forceIncrement($tenant, $metric, $amount);
            return true;
        }

        $current = $this->getCurrentUsage($tenant, $metric);

        if (($current + $amount) > $limit) {
            $this->checkThresholds($tenant, $metric, $current + $amount, $limit);
            return false;
        }

        $this->forceIncrement($tenant, $metric, $amount);
        $this->checkThresholds($tenant, $metric, $current + $amount, $limit);

        return true;
    }

    public function getCurrentUsage(TenantContract $tenant, string $metric): int
    {
        return (int) Cache::get($this->getCacheKey($tenant, $metric), 0);
    }

    public function getLimit(TenantContract $tenant, string $metric): int
    {
        return $tenant->getQuotaLimit($metric);
    }

    public function forceIncrement(TenantContract $tenant, string $metric, int $amount = 1): void
    {
        $key = $this->getCacheKey($tenant, $metric);
        
        if (Cache::has($key)) {
            Cache::increment($key, $amount);
        } else {
            Cache::put($key, $amount, now()->addDays(32));
        }
    }

    public function reset(TenantContract $tenant, string $metric): void
    {
        Cache::forget($this->getCacheKey($tenant, $metric));
    }

    private function getCacheKey(TenantContract $tenant, string $metric): string
    {
        $period = now()->format('Y-m');
        $prefix = self::KEY_PREFIX;
        return "{$prefix}:{$tenant->getId()}:{$metric}:{$period}";
    }

    private function checkThresholds(TenantContract $tenant, string $metric, int $current, int $limit): void
    {
        if ($limit <= 0) return;

        $percentage = ($current / $limit) * 100;
        $period = now()->format('Y-m');
        $prefix = self::KEY_PREFIX;

        foreach ([80, 100] as $threshold) {
            if ($percentage >= $threshold) {
                $lockKey = "{$prefix}:alert:{$tenant->getId()}:{$metric}:{$threshold}:{$period}";
                
                if (Cache::add($lockKey, '1', now()->addDays(30))) {
                    $tenant->notify(new QuotaThresholdReachedNotification($metric, $current, $limit, $threshold));
                }
            }
        }
    }
}
