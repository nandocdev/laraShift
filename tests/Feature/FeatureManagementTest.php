<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Features\Actions\ResolveTenantFeaturesAction;
use App\Modules\Central\Features\Models\Feature;
use App\Modules\Central\Features\Models\TenantFeatureOverride;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('resolves features from the plan', function () {
    $plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 2900,
        'price_yearly' => 29000,
        'features' => [],
    ]);

    $feature = Feature::create([
        'id' => Str::uuid()->toString(),
        'key' => 'reports.advanced',
        'name' => 'Advanced Reports',
    ]);

    $plan->catalogFeatures()->attach($feature->id);

    $tenant = Tenant::create([
        'id' => 'test-tenant',
        'name' => 'Test Tenant',
        'email' => 'test@tenant.com',
        'plan_id' => 'pro',
    ]);

    expect($tenant->hasFeature('reports.advanced'))->toBeTrue();
    expect($tenant->hasFeature('crm.pipeline'))->toBeFalse();
});

it('can override plan features with deny type', function () {
    $plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 2900,
        'price_yearly' => 29000,
        'features' => [],
    ]);

    $feature = Feature::create([
        'id' => Str::uuid()->toString(),
        'key' => 'reports.advanced',
        'name' => 'Advanced Reports',
    ]);

    $plan->catalogFeatures()->attach($feature->id);

    $tenant = Tenant::create([
        'id' => 'test-tenant',
        'name' => 'Test Tenant',
        'email' => 'test@tenant.com',
        'plan_id' => 'pro',
    ]);

    // Plan has it
    expect($tenant->hasFeature('reports.advanced'))->toBeTrue();

    // Create Deny Override
    TenantFeatureOverride::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'feature_id' => $feature->id,
        'type' => 'deny',
    ]);

    // Force refresh to clear cache
    app(ResolveTenantFeaturesAction::class)->execute($tenant, true);

    expect($tenant->hasFeature('reports.advanced'))->toBeFalse();
});

it('can grant additional features via allow override', function () {
    $plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Free',
        'slug' => 'free',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'features' => [],
    ]);

    $feature = Feature::create([
        'id' => Str::uuid()->toString(),
        'key' => 'api.access',
        'name' => 'API Access',
    ]);

    $tenant = Tenant::create([
        'id' => 'free-tenant',
        'name' => 'Free Tenant',
        'email' => 'free@tenant.com',
        'plan_id' => 'free',
    ]);

    expect($tenant->hasFeature('api.access'))->toBeFalse();

    // Create Allow Override
    TenantFeatureOverride::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'feature_id' => $feature->id,
        'type' => 'allow',
    ]);

    app(ResolveTenantFeaturesAction::class)->execute($tenant, true);

    expect($tenant->hasFeature('api.access'))->toBeTrue();
});
