<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Infrastructure\Jobs\SnapshotQuotasJob;
use App\Modules\Shared\Infrastructure\Services\QuotaManager;
use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Settings\Livewire\UsageOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Usage Test Plan',
        'slug' => 'usage-test',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'is_active' => true,
        'features' => [
            'quotas' => [
                'staff' => 5,
                'bookings' => 50,
                'invitations' => 10,
                'api_keys' => 3,
            ],
        ],
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'usage-dash',
        'name' => 'Usage Dashboard',
        'email' => 'usage@test.com',
        'plan_id' => 'usage-test',
    ]);

    tenancy()->initialize($this->tenant);

    $this->user = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Usage Admin',
        'email' => 'admin@usage.com',
        'password' => 'password',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('renders the usage overview page', function () {
    $this->actingAs($this->user);

    Livewire::test(UsageOverview::class)
        ->assertStatus(200);
});

it('displays quota metrics from plan', function () {
    $this->actingAs($this->user);

    Livewire::test(UsageOverview::class)
        ->assertSee('Active Staff Members')
        ->assertSee('Monthly Bookings')
        ->assertSee('Pending Invitations')
        ->assertSee('Active API Keys');
});

it('shows current usage from Redis counters', function () {
    $manager = app(QuotaManager::class);
    $manager->forceIncrement($this->tenant, 'staff', 3);

    $this->actingAs($this->user);

    Livewire::test(UsageOverview::class)
        ->assertSee('3');
});

it('shows unlimited badge when limit is -1', function () {
    $this->plan->update(['features' => ['quotas' => ['staff' => -1, 'bookings' => -1, 'invitations' => -1, 'api_keys' => -1]]]);

    $this->actingAs($this->user);

    Livewire::test(UsageOverview::class)
        ->assertSee('UNLIMITED');
});

it('shows FULL badge when quota is exhausted', function () {
    $manager = app(QuotaManager::class);
    $manager->forceIncrement($this->tenant, 'staff', 5);

    $this->actingAs($this->user);

    Livewire::test(UsageOverview::class)
        ->assertSee('FULL');
});

it('shows NEAR LIMIT badge at 80% or above', function () {
    $manager = app(QuotaManager::class);
    $manager->forceIncrement($this->tenant, 'staff', 4);

    $this->actingAs($this->user);

    Livewire::test(UsageOverview::class)
        ->assertSee('NEAR LIMIT');
});

it('enforces hard quota limit via QuotaManager', function () {
    $manager = app(QuotaManager::class);

    // Start at 0, increment by 5 to hit limit exactly (should succeed)
    $allowed = $manager->increment($this->tenant, 'staff', 5);
    expect($allowed)->toBeTrue();

    // Now try to go over the limit
    $allowed = $manager->increment($this->tenant, 'staff', 1);
    expect($allowed)->toBeFalse();
});

it('allows increment when limit is -1 (unlimited)', function () {
    $this->plan->update(['features' => ['quotas' => ['staff' => -1, 'bookings' => -1, 'invitations' => -1, 'api_keys' => -1]]]);

    $manager = app(QuotaManager::class);

    $allowed = $manager->increment($this->tenant, 'staff', 999);
    expect($allowed)->toBeTrue();

    expect($manager->getCurrentUsage($this->tenant, 'staff'))->toBe(999);
});

it('resolves limit from plan features', function () {
    $manager = app(QuotaManager::class);

    expect($manager->getLimit($this->tenant, 'staff'))->toBe(5);
    expect($manager->getLimit($this->tenant, 'bookings'))->toBe(50);
    expect($manager->getLimit($this->tenant, 'nonexistent'))->toBe(-1);
});

it('snapshot job persists Redis counters to DB', function () {
    $manager = app(QuotaManager::class);
    $manager->forceIncrement($this->tenant, 'staff', 3);

    $job = new SnapshotQuotasJob;
    $job->handle();

    $this->assertDatabaseHas('quota_snapshots', [
        'tenant_id' => $this->tenant->id,
        'metric' => 'staff',
        'usage' => 3,
    ]);
});

it('registers and renders the usage Livewire component', function () {
    $this->actingAs($this->user);

    Livewire::test('tenant-usage-overview')
        ->assertStatus(200);
});
