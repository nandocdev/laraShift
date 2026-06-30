<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Services;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Cache;

final readonly class TenantConfigCache
{
    private const CACHE_PREFIX = 'tenant_config';

    private const DEFAULT_TTL = 300;

    private int $ttl;

    public function __construct(?int $ttl = null)
    {
        $this->ttl = $ttl ?? (int) config('tenancy.cache.ttl', self::DEFAULT_TTL);
    }

    public function get(Tenant $tenant, string $key, mixed $default = null): mixed
    {
        $config = $this->getAll($tenant);

        return $config[$key] ?? $default;
    }

    public function set(Tenant $tenant, string $key, mixed $value): void
    {
        $cacheKey = $this->cacheKey($tenant);
        $config = Cache::get($cacheKey, []);
        $config[$key] = $value;
        Cache::put($cacheKey, $config, now()->addSeconds($this->ttl));
    }

    public function getAll(Tenant $tenant): array
    {
        return Cache::remember(
            $this->cacheKey($tenant),
            now()->addSeconds($this->ttl),
            fn () => $this->loadConfig($tenant),
        );
    }

    public function flush(Tenant $tenant): void
    {
        Cache::forget($this->cacheKey($tenant));
    }

    private function loadConfig(Tenant $tenant): array
    {
        $plan = $tenant->plan;
        $features = $tenant->features ?? [];
        $quotas = $plan?->features['quotas'] ?? [];

        return [
            'plan_id' => $tenant->plan_id,
            'plan_name' => $plan?->name ?? 'Free',
            'status' => $tenant->status,
            'maintenance_mode' => (bool) $tenant->maintenance_mode,
            'read_only' => (bool) $tenant->read_only,
            'features' => $features,
            'quotas' => $quotas,
            'billing_gateway' => $tenant->billing_gateway ?? config('payments.default'),
            'timezone' => $tenant->timezone ?? 'UTC',
            'locale' => $tenant->locale ?? config('app.locale'),
        ];
    }

    private function cacheKey(Tenant $tenant): string
    {
        return self::CACHE_PREFIX.":{$tenant->id}";
    }
}
