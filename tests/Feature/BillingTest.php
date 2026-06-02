<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Support\PlanManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use Laravel\Cashier\Billable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has a plan matrix configured', function () {
    $plans = PlanManager::all();

    expect($plans)->not->toBeEmpty();
    expect($plans->has('free'))->toBeTrue();
    expect($plans->has('pro'))->toBeTrue();
});

it('tenant model uses billable trait', function () {
    $tenant = new Tenant();
    
    expect(class_uses_recursive($tenant))->toContain(Billable::class);
});

it('can find a specific plan', function () {
    $plan = PlanManager::find('pro');

    expect($plan)->not->toBeNull();
    expect($plan['name'])->toBe('Pro');
    expect($plan['price'])->toBe(2900);
});
