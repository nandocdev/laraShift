<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Actions\EnsureTenantRolesExistAction;
use App\Modules\Tenant\Identity\Actions\ImpersonateTenantUserAction;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $plan = Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free Plan',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'impersonation-test',
        'name' => 'Impersonation Test',
        'email' => 'imp@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($this->tenant);
    app(EnsureTenantRolesExistAction::class)->execute($this->tenant);

    $this->admin = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);
    $this->admin->assignRole('admin');

    $this->target = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Target User',
        'email' => 'target@test.com',
        'password' => 'password',
    ]);
});

test('admin can impersonate a tenant user', function () {
    $action = app(ImpersonateTenantUserAction::class);

    $url = $action->execute($this->target, $this->admin, 'Testing impersonation');

    expect($url)->not->toBeNull();
    expect(Session::get('impersonate_target_id'))->toBe($this->target->id);
    expect(ImpersonateTenantUserAction::isActive())->toBeTrue();
});

test('non-admin cannot impersonate', function () {
    $member = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Member',
        'email' => 'member@test.com',
        'password' => 'password',
    ]);
    $member->assignRole('member');

    $action = app(ImpersonateTenantUserAction::class);

    expect(fn () => $action->execute($this->target, $member, 'Not allowed'))
        ->toThrow(\RuntimeException::class);
});

test('cannot impersonate yourself', function () {
    $action = app(ImpersonateTenantUserAction::class);

    expect(fn () => $action->execute($this->admin, $this->admin, 'Self'))
        ->toThrow(\RuntimeException::class);
});

test('revert impersonation returns to original user', function () {
    $action = app(ImpersonateTenantUserAction::class);
    $action->execute($this->target, $this->admin, 'Testing');

    $url = $action->revert();

    expect($url)->not->toBeNull();
    expect(ImpersonateTenantUserAction::isActive())->toBeFalse();
});

test('revert without active impersonation returns null', function () {
    $action = app(ImpersonateTenantUserAction::class);

    $result = $action->revert();

    expect($result)->toBeNull();
});

test('impersonation logs to activity', function () {
    $action = app(ImpersonateTenantUserAction::class);
    $action->execute($this->target, $this->admin, 'Audit test');

    $logs = \Spatie\Activitylog\Models\Activity::where('description', 'tenant_impersonation_started')->get();

    expect($logs)->not->toBeEmpty();
});
