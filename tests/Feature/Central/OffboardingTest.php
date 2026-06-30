<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Actions\ArchiveWithRetentionAction;
use App\Modules\Central\Provisioning\Actions\ChangeTenantPlanAction;
use App\Modules\Central\Provisioning\Actions\VerifyCustomDomainAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $this->proPlan = Plan::create([
        'slug' => 'pro-offboarding',
        'name' => 'Pro',
        'price_monthly' => 2999,
        'price_yearly' => 29990,
        'amount' => 2999,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'offboarding-test-'.Str::random(4),
        'name' => 'Offboarding Test',
        'email' => 'off@test.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);
});

test('archive with retention sets correct metadata', function () {
    $action = app(ArchiveWithRetentionAction::class);
    $result = $action->execute($this->tenant);

    $this->tenant->refresh();
    expect($this->tenant->status)->toBe('archived');
    expect($this->tenant->archived_at)->not->toBeNull();
    expect($this->tenant->read_only)->toBeTrue();
    expect($result['retention_days'])->toBe(90);
});

test('restore reverts archived tenant', function () {
    $action = app(ArchiveWithRetentionAction::class);
    $action->execute($this->tenant);

    $action->restore($this->tenant);

    $this->tenant->refresh();
    expect($this->tenant->status)->toBe('active');
    expect($this->tenant->archived_at)->toBeNull();
    expect($this->tenant->read_only)->toBeFalse();
});

test('should purge returns true after retention period', function () {
    $action = app(ArchiveWithRetentionAction::class);
    $action->execute($this->tenant);

    $this->tenant->archived_at = now()->subDays(100);
    $this->tenant->save();

    expect($action->shouldPurge($this->tenant))->toBeTrue();
});

test('should purge returns false within retention period', function () {
    $action = app(ArchiveWithRetentionAction::class);
    $action->execute($this->tenant);

    $this->tenant->archived_at = now();
    $this->tenant->save();

    expect($action->shouldPurge($this->tenant))->toBeFalse();
});

test('change plan updates tenant plan', function () {
    $action = app(ChangeTenantPlanAction::class);
    $result = $action->execute($this->tenant, 'pro-offboarding');

    $this->tenant->refresh();
    expect($this->tenant->plan_id)->toBe('pro-offboarding');
    expect($result['old_plan'])->toBe('free');
    expect($result['new_plan'])->toBe('pro-offboarding');
});

test('change plan rejects inactive plan', function () {
    $inactivePlan = Plan::create([
        'slug' => 'inactive-plan',
        'name' => 'Inactive',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => false,
        'features' => [],
    ]);

    $action = app(ChangeTenantPlanAction::class);

    expect(fn () => $action->execute($this->tenant, 'inactive-plan'))
        ->toThrow(InvalidArgumentException::class, 'not active');
});

test('change plan includes proration info', function () {
    $action = app(ChangeTenantPlanAction::class);
    $result = $action->execute($this->tenant, 'pro-offboarding');

    expect($result['proration'])->toHaveKey('type');
    expect($result['proration']['type'])->toBe('upgrade');
});

test('custom domain verification stores verification status', function () {
    $action = app(VerifyCustomDomainAction::class);

    $result = $action->checkStatus($this->tenant, 'example.com');

    expect($result)->toBeFalse();
});

test('custom domain verification handles dns errors gracefully', function () {
    $action = app(VerifyCustomDomainAction::class);

    $result = $action->verify($this->tenant, 'this-domain-does-not-exist-12345.com');

    expect($result['verified'])->toBeFalse();
});

test('enterprise retention is 365 days', function () {
    $enterpriseTenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'ent-off-'.Str::random(4),
        'name' => 'Enterprise',
        'email' => 'ent@test.com',
        'plan_id' => 'enterprise',
        'status' => 'active',
    ]);

    // Create enterprise plan
    Plan::firstOrCreate(['slug' => 'enterprise'], [
        'name' => 'Enterprise',
        'price_monthly' => 9900,
        'price_yearly' => 99000,
        'amount' => 9900,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $action = app(ArchiveWithRetentionAction::class);
    $result = $action->execute($enterpriseTenant);

    expect($result['retention_days'])->toBe(365);
});
