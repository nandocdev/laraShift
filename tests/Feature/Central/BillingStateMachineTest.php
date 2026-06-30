<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Services\TenantStateMachine;
use App\Modules\Central\Billing\Services\ProrationCalculator;
use App\Modules\Central\Billing\Services\DunningEngine;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Infrastructure\Casts\MoneyCast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stateMachine = app(TenantStateMachine::class);
    $this->proration = app(ProrationCalculator::class);
    $this->dunning = app(DunningEngine::class);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'billing-state-' . Str::random(4),
        'name' => 'Billing State Test',
        'email' => 'billing-state@test.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);
});

test('state machine allows valid transitions', function () {
    expect($this->stateMachine->canTransition($this->tenant, 'suspended'))->toBeTrue();
    expect($this->stateMachine->canTransition($this->tenant, 'archived'))->toBeTrue();
    expect($this->stateMachine->canTransition($this->tenant, 'provisioning'))->toBeFalse();
});

test('state machine executes transition', function () {
    $this->stateMachine->transition($this->tenant, 'suspended');

    $this->tenant->refresh();
    expect($this->tenant->status)->toBe('suspended');
    expect($this->tenant->suspended_at)->not->toBeNull();
});

test('state machine rejects invalid transitions', function () {
    expect(fn () => $this->stateMachine->transition($this->tenant, 'provisioning'))
        ->toThrow(\InvalidArgumentException::class);
});

test('state machine can reactivate from suspended', function () {
    $this->stateMachine->transition($this->tenant, 'suspended');
    $this->stateMachine->transition($this->tenant, 'active');

    $this->tenant->refresh();
    expect($this->tenant->status)->toBe('active');
    expect($this->tenant->suspended_at)->toBeNull();
});

test('archived is terminal state', function () {
    expect(TenantStateMachine::isTerminal('archived'))->toBeTrue();
    expect(TenantStateMachine::isTerminal('active'))->toBeFalse();
});

test('proration calculates upgrade net amount', function () {
    $freePlan = Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $proPlan = Plan::create([
        'slug' => 'pro-test',
        'name' => 'Pro Test',
        'price_monthly' => 2999,
        'price_yearly' => 29990,
        'amount' => 2999,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $description = $this->proration->describe($freePlan, $proPlan);

    expect($description['type'])->toBe('upgrade');
    expect($description['credit'])->toBe(0.0);
    expect($description['charge'])->toBeGreaterThan(0);
});

test('proration identifies upgrade', function () {
    $freePlan = Plan::firstOrCreate(['slug' => 'free-upgrade-test'], [
        'name' => 'Free',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $proPlan = Plan::create([
        'slug' => 'pro-upgrade-test',
        'name' => 'Pro',
        'price_monthly' => 2999,
        'price_yearly' => 29990,
        'amount' => 2999,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $isUpgrade = $this->proration->isUpgrade($freePlan, $proPlan);

    expect($isUpgrade)->toBeTrue();
});

test('dunning process sends notification on first failure', function () {
    $result = $this->dunning->process($this->tenant, 1);

    expect($result)->toBe('notify');
});

test('dunning suspends after multiple failures', function () {
    $result = $this->dunning->process($this->tenant, 15);

    expect($result)->toBe('suspended');
});

test('dunning returns no_action for zero failures', function () {
    $result = $this->dunning->process($this->tenant, 0);

    expect($result)->toBe('no_action');
});

test('remaining grace days is positive for active tenant', function () {
    $days = $this->dunning->remainingGraceDays($this->tenant);

    expect($days)->toBeGreaterThan(0);
});
