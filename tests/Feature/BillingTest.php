<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Support\PlanManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use Laravel\Cashier\Billable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\PlanSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('has a plan matrix in database', function () {
    $plans = PlanManager::all();

    expect($plans)->not->toBeEmpty();
    expect($plans->pluck('slug'))->toContain('free', 'pro');
});

it('tenant model uses billable trait', function () {
    $tenant = new Tenant();
    
    expect(class_uses_recursive($tenant))->toContain(Billable::class);
});

it('can find a specific plan by slug', function () {
    $plan = PlanManager::find('pro');

    expect($plan)->not->toBeNull();
    expect($plan->name)->toBe('Pro');
    expect($plan->price_monthly->getAmount())->toBe('2999');
});
