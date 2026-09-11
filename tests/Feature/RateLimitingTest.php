<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Security\RateLimiting\TenantRateLimiter;
use App\Modules\Platform\Tenancy\Interface\Http\Middleware\ApplyTenantRateLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('applies the default rate limit when the tenant has no plan quota', function () {
    $tenantId = '00000000-0000-0000-0000-000000000012';
    $key = 'tenant_rate_limit:'.$tenantId;
    RateLimiter::clear($key);

    $tenant = Tenant::create([
        'id' => $tenantId,
        'slug' => 'rate-limit',
        'name' => 'Rate Limited Tenant',
        'email' => 'rate@tenant.com',
    ]);

    tenancy()->initialize($tenant);

    Route::get('/test-rate-limit', fn () => 'ok')->middleware(ApplyTenantRateLimits::class);

    // Default limit (no plan quotas): 60 rpm
    $this->get('/test-rate-limit')
        ->assertStatus(200)
        ->assertHeader('X-RateLimit-Limit', 60);
});

it('resolves the configured default when quota is unlimited', function () {
    $tenant = Tenant::create([
        'id' => '00000000-0000-0000-0000-000000000013',
        'slug' => 'rate-limit-default',
        'name' => 'Default Tenant',
        'email' => 'default@tenant.com',
    ]);

    expect(app(TenantRateLimiter::class)->resolveLimit($tenant, 60))->toBe(60);
});
