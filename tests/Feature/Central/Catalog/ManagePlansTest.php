<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Catalog\Interface\Livewire\ManagePlans;
use Livewire\Livewire;

function centralStaff(): CentralUser
{
    return CentralUser::factory()->create();
}

it('creates a plan with features, quotas and gateway refs', function () {
    $this->actingAs(centralStaff(), 'central');

    Livewire::test(ManagePlans::class)
        ->set('name', 'Business')
        ->set('slug', 'business')
        ->set('priceMonthly', '49.00')
        ->set('priceYearly', '490.00')
        ->set('currency', 'usd')
        ->set('interval', 'month')
        ->set('displayFeatures', ['basic_dashboard', 'api_access'])
        ->set('quotas', ['api_keys' => '10', 'invitations' => '', 'bookings' => ''])
        ->set('gatewayDlocal', 'PLAN-123')
        ->call('save')
        ->assertHasNoErrors();

    $plan = Plan::where('slug', 'business')->firstOrFail();

    expect($plan->price_monthly)->toBe(4900)
        ->and($plan->price_yearly)->toBe(49000)
        ->and($plan->currency)->toBe('USD')
        ->and($plan->features['display_features'])->toBe(['basic_dashboard', 'api_access'])
        ->and($plan->features['quotas'])->toBe(['api_keys' => 10])
        ->and($plan->features['gateway_ids'])->toBe(['dlocal' => 'PLAN-123']);
});

it('validates plan input', function () {
    $this->actingAs(centralStaff(), 'central');

    Livewire::test(ManagePlans::class)
        ->set('name', '')
        ->set('slug', 'Bad Slug!')
        ->set('priceMonthly', '-5')
        ->call('save')
        ->assertHasErrors(['name', 'slug', 'priceMonthly']);

    expect(Plan::count())->toBe(0);
});

it('edits a plan without changing its slug', function () {
    $this->actingAs(centralStaff(), 'central');
    $plan = Plan::create([
        'name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 2900, 'price_yearly' => 29000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => ['api_access'], 'gateway_ids' => [], 'quotas' => []],
        'is_active' => true,
    ]);

    Livewire::test(ManagePlans::class)
        ->call('edit', $plan->id)
        ->set('slug', 'hacked')
        ->set('name', 'Pro Plus')
        ->set('displayFeatures', ['basic_dashboard'])
        ->call('save')
        ->assertHasNoErrors();

    $plan->refresh();

    expect($plan->slug)->toBe('pro')
        ->and($plan->name)->toBe('Pro Plus')
        ->and($plan->features['display_features'])->toBe(['basic_dashboard']);
});

it('toggles and archives plans', function () {
    $this->actingAs(centralStaff(), 'central');
    $plan = Plan::create([
        'name' => 'Pro', 'slug' => 'pro', 'price_monthly' => 2900, 'price_yearly' => 29000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => [], 'gateway_ids' => [], 'quotas' => []],
        'is_active' => true,
    ]);

    Livewire::test(ManagePlans::class)
        ->call('toggleActive', $plan->id);

    expect($plan->fresh()->is_active)->toBeFalse();

    Livewire::test(ManagePlans::class)
        ->call('delete', $plan->id);

    expect($plan->fresh()->trashed())->toBeTrue();
});
