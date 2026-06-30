<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Livewire\ImpersonationLog;
use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Models\SupportSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Super Admin',
        'email' => 'admin@test.com',
        'password' => Hash::make('password'),
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'impersonation-test',
        'name' => 'Impersonation Test Corp',
        'email' => 'corp@test.com',
        'plan_id' => 'free',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('renders the impersonation log page', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(ImpersonationLog::class)
        ->assertStatus(200)
        ->assertSee(__('Impersonation Log'));
});

it('displays existing impersonation sessions', function () {
    SupportSession::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'operator_id' => $this->admin->id,
        'reason' => str_repeat('Testing impersonation for audit purposes ', 3),
        'token' => Str::random(64),
        'started_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    $this->actingAs($this->admin, 'central');

    Livewire::test(ImpersonationLog::class)
        ->assertStatus(200)
        ->assertSee($this->admin->name)
        ->assertSee($this->tenant->name)
        ->assertSee(__('Active'));
});

it('shows empty state when no impersonation sessions exist', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(ImpersonationLog::class)
        ->assertStatus(200)
        ->assertSee(__('No impersonation sessions found.'));
});

it('filters by active status', function () {
    SupportSession::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'operator_id' => $this->admin->id,
        'reason' => str_repeat('Active session for testing ', 3),
        'token' => Str::random(64),
        'started_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    SupportSession::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'operator_id' => $this->admin->id,
        'reason' => str_repeat('Ended session for testing ', 3),
        'token' => Str::random(64),
        'started_at' => now()->subDay(),
        'expires_at' => now()->subHour(),
        'ended_at' => now()->subHour(),
    ]);

    $this->actingAs($this->admin, 'central');

    Livewire::test(ImpersonationLog::class)
        ->set('filterStatus', 'active')
        ->assertSee('Active session')
        ->assertDontSee('Ended session');
});

it('search finds operator by name', function () {
    SupportSession::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'operator_id' => $this->admin->id,
        'reason' => str_repeat('Specific audit reason for search test ', 3),
        'token' => Str::random(64),
        'started_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    $this->actingAs($this->admin, 'central');

    Livewire::test(ImpersonationLog::class)
        ->set('search', 'Super Admin')
        ->assertSee('Specific audit reason');
});

it('search finds tenant by name', function () {
    SupportSession::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'operator_id' => $this->admin->id,
        'reason' => str_repeat('Reason for tenant search test ', 3),
        'token' => Str::random(64),
        'started_at' => now(),
        'expires_at' => now()->addHours(2),
    ]);

    $this->actingAs($this->admin, 'central');

    Livewire::test(ImpersonationLog::class)
        ->set('search', 'Impersonation Test Corp')
        ->assertSee('Reason for tenant search');
});

it('paginates results', function () {
    foreach (range(1, 25) as $i) {
        $tenant = Tenant::create([
            'id' => Str::uuid()->toString(),
            'slug' => "imp-pagination-{$i}",
            'name' => "Pagination Tenant {$i}",
            'email' => "pagination{$i}@test.com",
            'plan_id' => 'free',
        ]);

        SupportSession::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $tenant->id,
            'operator_id' => $this->admin->id,
            'reason' => str_repeat("Pagination session {$i} ", 3),
            'token' => Str::random(64),
            'started_at' => now()->subMinutes($i),
            'expires_at' => now()->addHours(2),
        ]);
    }

    $this->actingAs($this->admin, 'central');

    Livewire::test(ImpersonationLog::class)
        ->assertStatus(200)
        ->assertSee('Pagination session');
});

it('redirects unauthenticated users to login via http', function () {
    $this->get(route('central.auth.impersonations'))
        ->assertRedirect(route('central.login'));
});
