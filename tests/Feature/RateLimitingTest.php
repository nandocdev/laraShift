<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Tenancy\Http\Middleware\ApplyTenantRateLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('enforces rate limits based on the plan', function () {
    $tenantId = '00000000-0000-0000-0000-000000000012';
    $key = 'tenant_rate_limit:'.$tenantId;
    RateLimiter::clear($key);

    $plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Limited Plan',
        'slug' => 'limited',
        'price_monthly' => 1000,
        'price_yearly' => 10000,
        'features' => [
            'quotas' => [
                'rate_limit_rpm' => 2, // Only 2 requests per minute
            ],
        ],
    ]);

    $tenant = Tenant::create([
        'id' => $tenantId,
        'slug' => 'rate-limit',
        'name' => 'Rate Limited Tenant',
        'email' => 'rate@tenant.com',
        'plan_id' => 'limited',
    ]);

    tenancy()->initialize($tenant);

    Route::get('/test-rate-limit', fn () => 'ok')->middleware(ApplyTenantRateLimits::class);

    // Request 1: OK
    $this->get('/test-rate-limit')->assertStatus(200)->assertHeader('X-RateLimit-Limit', 2);

    // Request 2: OK
    $this->get('/test-rate-limit')->assertStatus(200);

    // Request 3: 429
    $response = $this->get('/test-rate-limit');
    $response->assertStatus(429);
    $response->assertJsonStructure(['error', 'message']);
});
