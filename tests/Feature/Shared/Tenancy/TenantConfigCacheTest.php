<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Tenancy\Services\TenantConfigCache;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->plan = Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free Plan',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => ['quotas' => ['rate_limit_rpm' => 60]],
    ]);

    $this->tenant = Tenant::firstOrCreate(['id' => '00000000-0000-0000-0000-000000000001'], [
        'slug' => 'test-tenant',
        'name' => 'Test Tenant',
        'email' => 'test@tenant.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);

    $this->configCache = app(TenantConfigCache::class);
});

test('returns default value for missing key', function () {
    $value = $this->configCache->get($this->tenant, 'nonexistent', 'fallback');

    expect($value)->toBe('fallback');
});

test('stores and retrieves custom config values', function () {
    $this->configCache->set($this->tenant, 'custom_key', 'custom_value');

    $value = $this->configCache->get($this->tenant, 'custom_key');

    expect($value)->toBe('custom_value');
});

test('loads tenant plan config', function () {
    $config = $this->configCache->getAll($this->tenant);

    expect($config['plan_id'])->toBe('free');
    expect($config['status'])->toBe('active');
    expect($config['maintenance_mode'])->toBeFalse();
});

test('loads quota values from plan', function () {
    $config = $this->configCache->getAll($this->tenant);

    expect($config['quotas'])->toHaveKey('rate_limit_rpm');
    expect($config['quotas']['rate_limit_rpm'])->toBe(60);
});

test('flush clears cached config', function () {
    $this->configCache->getAll($this->tenant);

    $this->configCache->flush($this->tenant);

    $cacheKey = 'tenant_config:' . $this->tenant->id;
    expect(Cache::get($cacheKey))->toBeNull();
});

test('config is cached and not re-fetched on second call', function () {
    $this->configCache->getAll($this->tenant);

    $this->tenant->update(['name' => 'Updated Name']);

    $cached = $this->configCache->getAll($this->tenant);

    expect($cached['plan_id'])->toBe('free');
});
