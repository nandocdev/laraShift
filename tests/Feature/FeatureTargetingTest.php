<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Features\Actions\ResolveTenantFeaturesAction;
use App\Modules\Central\Features\Livewire\FeatureChangeHistory;
use App\Modules\Central\Features\Models\Feature;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Infrastructure\Services\QuotaManager;
use App\Modules\Shared\Models\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 2900,
        'price_yearly' => 29000,
        'features' => [],
    ]);

    $this->feature = Feature::create([
        'id' => Str::uuid()->toString(),
        'key' => 'reports.advanced',
        'name' => 'Advanced Reports',
        'is_active' => true,
    ]);

    $this->plan->catalogFeatures()->attach($this->feature->id);
});

it('resolves feature without targeting rules', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'no-target',
        'name' => 'No Targeting',
        'email' => 'no@test.com',
        'plan_id' => 'pro',
    ]);

    expect($tenant->hasFeature('reports.advanced'))->toBeTrue();
});

it('restricts feature by region targeting', function () {
    $this->feature->update(['targeting' => ['regions' => ['LATAM']]]);

    $tenantInRegion = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'in-region',
        'name' => 'In Region',
        'email' => 'in@test.com',
        'plan_id' => 'pro',
    ]);
    DB::table('tenants')->where('id', $tenantInRegion->id)->update(['data' => json_encode(['region' => 'LATAM'])]);

    $tenantOutOfRegion = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'out-region',
        'name' => 'Out Region',
        'email' => 'out@test.com',
        'plan_id' => 'pro',
    ]);
    DB::table('tenants')->where('id', $tenantOutOfRegion->id)->update(['data' => json_encode(['region' => 'EU'])]);

    $inRegion = Tenant::find($tenantInRegion->id);
    $outRegion = Tenant::find($tenantOutOfRegion->id);

    $action = app(ResolveTenantFeaturesAction::class);
    expect($action->execute($inRegion, true))->toContain('reports.advanced');
    expect($action->execute($outRegion, true))->not->toContain('reports.advanced');
});

it('restricts feature by staff count targeting', function () {
    $this->feature->update(['targeting' => ['staff_min' => 3, 'staff_max' => 10]]);

    $tenantSmall = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'small-tenant',
        'name' => 'Small',
        'email' => 'small@test.com',
        'plan_id' => 'pro',
    ]);

    $tenantLarge = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'large-tenant',
        'name' => 'Large',
        'email' => 'large@test.com',
        'plan_id' => 'pro',
    ]);

    app(QuotaManager::class)->forceIncrement($tenantSmall, 'staff', 5);
    app(QuotaManager::class)->forceIncrement($tenantLarge, 'staff', 20);

    expect($tenantSmall->hasFeature('reports.advanced'))->toBeTrue();
    expect($tenantLarge->hasFeature('reports.advanced'))->toBeFalse();
});

it('restricts feature by tenancy age targeting', function () {
    $this->feature->update(['targeting' => ['min_tenancy_days' => 30]]);

    $action = app(ResolveTenantFeaturesAction::class);

    $recentTenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'recent',
        'name' => 'Recent',
        'email' => 'recent@test.com',
        'plan_id' => 'pro',
    ]);
    DB::table('tenants')->where('id', $recentTenant->id)->update(['created_at' => now()->subDays(15)]);

    $matureTenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'mature',
        'name' => 'Mature',
        'email' => 'mature@test.com',
        'plan_id' => 'pro',
    ]);
    DB::table('tenants')->where('id', $matureTenant->id)->update(['created_at' => now()->subDays(60)]);

    // Use forceRefresh to bypass cache
    expect($action->execute($recentTenant, true))->not->toContain('reports.advanced');
    expect($action->execute($matureTenant, true))->toContain('reports.advanced');
});

it('uses cache TTL instead of rememberForever', function () {
    $action = app(ResolveTenantFeaturesAction::class);

    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'cache-test',
        'name' => 'Cache Test',
        'email' => 'cache@test.com',
        'plan_id' => 'pro',
    ]);

    $cacheKey = "tenant:{$tenant->id}:features";

    $features = $action->execute($tenant);
    expect($features)->toContain('reports.advanced');

    $this->feature->update(['is_active' => false]);

    $cached = cache($cacheKey);
    expect($cached)->not->toBeNull();
});

it('force refresh bypasses cache', function () {
    $action = app(ResolveTenantFeaturesAction::class);

    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'refresh-test',
        'name' => 'Refresh Test',
        'email' => 'refresh@test.com',
        'plan_id' => 'pro',
    ]);

    $action->execute($tenant);

    $this->feature->update(['is_active' => false]);

    $refreshed = $action->execute($tenant, true);
    expect($refreshed)->not->toContain('reports.advanced');
});

it('logs feature changes via activity log', function () {
    $admin = CentralUser::create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'central');

    $originalTargeting = $this->feature->targeting;
    $this->feature->update(['description' => 'Updated description']);

    activity('features')
        ->performedOn($this->feature)
        ->withProperties([
            'changes' => ['description' => ['from' => null, 'to' => 'Updated description']],
            'actor' => auth('central')->id(),
        ])
        ->log('feature_updated');

    $log = Activity::where('log_name', 'features')->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toBe('feature_updated');
    expect($log->causer_id)->toBe((string) $admin->id);
});

it('registers the feature history Livewire component', function () {
    $admin = CentralUser::create([
        'name' => 'Admin',
        'email' => 'admin2@test.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'central');

    Livewire::test(FeatureChangeHistory::class)
        ->assertStatus(200);
});
