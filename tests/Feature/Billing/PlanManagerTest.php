<?php

declare(strict_types=1);

use App\Modules\Central\Catalog\Application\Actions\ResolveTenantFeatures;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;

it('resolves provider refs per gateway with fallback', function () {
    $plan = Plan::create([
        'slug' => 'pro-test',
        'name' => 'Pro Test',
        'provider_plan_id' => 'FALLBACK-1',
        'price_monthly' => 2900,
        'price_yearly' => 29000,
        'currency' => 'USD',
        'interval' => 'month',
        'features' => ['gateway_ids' => ['dlocal' => 'PLAN-83920']],
        'is_active' => true,
    ]);

    $manager = app(PlanManager::class);

    expect($manager->getProviderRef($plan, 'dlocal'))->toBe('PLAN-83920')
        ->and($manager->getProviderRef($plan, 'clave'))->toBe('FALLBACK-1')
        ->and($manager->getProviderRef($plan, 'stripe'))->toBe('FALLBACK-1');

    $plan->update(['provider_plan_id' => null, 'features' => ['gateway_ids' => []]]);

    expect($manager->getProviderRef($plan->fresh(), 'clave'))->toBeNull();
});

it('resolves tenant features and misses cache on plan change', function () {
    Plan::create([
        'slug' => 'free', 'name' => 'Free', 'price_monthly' => 0, 'price_yearly' => 0,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => ['basic_dashboard']],
        'is_active' => true,
    ]);
    Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 2900, 'price_yearly' => 29000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => ['basic_dashboard', 'api_access']],
        'is_active' => true,
    ]);

    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'catalog-test',
        'name' => 'Catalog Test',
        'email' => 'catalog@test.com',
        'status' => 'active',
        'plan_id' => 'free',
    ]);

    $resolver = app(ResolveTenantFeatures::class);

    expect($resolver->hasFeature($tenant, 'api_access'))->toBeFalse()
        ->and($resolver->hasFeature($tenant, 'basic_dashboard'))->toBeTrue();

    $tenant->update(['plan_id' => 'pro']);

    // The cache key embeds the plan slug: no explicit invalidation needed.
    expect($resolver->hasFeature($tenant->fresh(), 'api_access'))->toBeTrue();

    assertDatabaseHas('plans', ['slug' => 'pro']);
});
