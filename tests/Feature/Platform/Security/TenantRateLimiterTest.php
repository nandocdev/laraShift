<?php

declare(strict_types=1);

use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Platform\Security\RateLimiting\TenantRateLimiter;

beforeEach(function () {
    $this->limiter = new TenantRateLimiter;
});

test('resolves limit from tenant quota', function () {
    $tenant = Mockery::mock(TenantContract::class);
    $tenant->shouldReceive('getQuotaLimit')->with('rate_limit_rpm')->andReturn(120);

    expect($this->limiter->resolveLimit($tenant))->toBe(120);
});

test('uses default when tenant has no limit', function () {
    $tenant = Mockery::mock(TenantContract::class);
    $tenant->shouldReceive('getQuotaLimit')->with('rate_limit_rpm')->andReturn(0);

    expect($this->limiter->resolveLimit($tenant))->toBe(60);
});

test('builds cache key', function () {
    expect($this->limiter->key('tenant-123'))->toBe('tenant_rate_limit:tenant-123');
});
