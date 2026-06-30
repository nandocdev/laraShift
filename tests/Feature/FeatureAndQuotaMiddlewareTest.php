<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Features\Models\Feature;
use App\Modules\Central\Features\Models\TenantFeatureOverride;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Infrastructure\Services\QuotaManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = Plan::create([
        'name' => 'Test Plan',
        'slug' => 'test-plan',
        'price_monthly' => 1000,
        'amount' => 10,
        'currency' => 'USD',
        'features' => [
            'quotas' => [
                'users' => 5,
            ],
        ],
    ]);

    $this->feature = Feature::create([
        'id' => (string) Str::uuid(),
        'key' => 'api-access',
        'name' => 'API Access',
    ]);

    $this->plan->catalogFeatures()->attach($this->feature->id);

    $this->tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'test-tenant',
        'name' => 'Test Tenant',
        'email' => 'test@example.com',
        'plan_id' => $this->plan->slug,
    ]);

    tenancy()->initialize($this->tenant);
});

afterEach(function () {
    tenancy()->end();
});

it('allows access if tenant has feature', function () {
    Route::middleware(['web', InitializeTenancyByDomain::class, 'feature:api-access'])
        ->get('/feature-test', fn () => 'Allowed');

    $domain = 'test-tenant.'.config('tenancy.central_domain');
    $this->tenant->domains()->create(['domain' => $domain]);

    $this->get("http://{$domain}/feature-test")
        ->assertStatus(200)
        ->assertSee('Allowed');
});

it('denies access if tenant lacks feature', function () {
    Route::middleware(['web', InitializeTenancyByDomain::class, 'feature:non-existent-feature'])
        ->get('/feature-test-missing', fn () => 'Allowed');

    $domain = 'test-tenant.'.config('tenancy.central_domain');
    $this->tenant->domains()->create(['domain' => $domain]);

    $this->get("http://{$domain}/feature-test-missing")
        ->assertStatus(403);
});

it('allows access if feature is granted via override', function () {
    $customFeature = Feature::create([
        'id' => (string) Str::uuid(),
        'key' => 'custom-feature',
        'name' => 'Custom',
    ]);

    TenantFeatureOverride::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'feature_id' => $customFeature->id,
        'type' => 'allow',
    ]);

    Route::middleware(['web', InitializeTenancyByDomain::class, 'feature:custom-feature'])
        ->get('/feature-test-override', fn () => 'Allowed');

    $domain = 'test-tenant.'.config('tenancy.central_domain');
    $this->tenant->domains()->create(['domain' => $domain]);

    $this->get("http://{$domain}/feature-test-override")
        ->assertStatus(200)
        ->assertSee('Allowed');
});

it('allows access if tenant is within quota', function () {
    Route::middleware(['web', InitializeTenancyByDomain::class, 'quota:users'])
        ->get('/quota-test', fn () => 'Allowed');

    $domain = 'test-tenant.'.config('tenancy.central_domain');
    $this->tenant->domains()->create(['domain' => $domain]);

    $this->get("http://{$domain}/quota-test")
        ->assertStatus(200)
        ->assertSee('Allowed');
});

it('denies access if tenant exceeds quota', function () {
    $quotaManager = app(QuotaManager::class);
    $quotaManager->forceIncrement($this->tenant, 'users', 6);

    Route::middleware(['web', InitializeTenancyByDomain::class, 'quota:users'])
        ->get('/quota-test-exceeded', fn () => 'Allowed');

    $domain = 'test-tenant.'.config('tenancy.central_domain');
    $this->tenant->domains()->create(['domain' => $domain]);

    $this->get("http://{$domain}/quota-test-exceeded")
        ->assertStatus(429);
});
