<?php

declare(strict_types=1);

use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\Invitation;
use App\Modules\Tenant\Access\Domain\Models\Role;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Workspace\Interface\Livewire\TeamManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

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

    $id = (string) Str::uuid();
    $tenant = Tenant::create([
        'id' => $id,
        'slug' => 'test-'.substr($id, 0, 8),
        'name' => 'Test Tenant',
        'email' => 'test-'.substr($id, 0, 8).'@tenant.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);

    $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
    $tenant->domains()->create(['domain' => $tenant->slug.'.'.$centralDomain]);
    tenancy()->initialize($tenant);

    app(EnsureTenantRolesExist::class)->execute($tenant);
});

test('renders the team management page', function () {
    $user = User::factory()->create(['tenant_id' => tenant('id')]);

    $this->actingAs($user);

    Livewire::test(TeamManagement::class)
        ->assertSuccessful();
});

test('displays current team members', function () {
    $admin = User::factory()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Admin User',
    ]);

    User::factory()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Member One',
    ]);

    $this->actingAs($admin);

    Livewire::test(TeamManagement::class)
        ->assertSee('Admin User')
        ->assertSee('Member One');
});

test('updates a member role successfully via dedicated action', function () {
    $admin = User::factory()->create(['tenant_id' => tenant('id')]);
    $member = User::factory()->create(['tenant_id' => tenant('id')]);

    setPermissionsTeamId(tenant('id'));
    $member->assignRole('member');

    $this->actingAs($admin);

    Livewire::test(TeamManagement::class)
        ->call('selectMember', $member->id)
        ->set('newRole', 'admin')
        ->call('updateRole')
        ->assertHasNoErrors()
        ->assertSee(__('User role updated.'));

    expect($member->fresh()->hasRole('admin'))->toBeTrue();
});

test('cannot change own role in team management', function () {
    $admin = User::factory()->create(['tenant_id' => tenant('id')]);

    setPermissionsTeamId(tenant('id'));
    $admin->assignRole('admin');

    $this->actingAs($admin);

    Livewire::test(TeamManagement::class)
        ->call('selectMember', $admin->id)
        ->set('newRole', 'member')
        ->call('updateRole')
        ->assertHasErrors(['newRole']);

    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});

test('revokes member access via dedicated action', function () {
    $admin = User::factory()->create(['tenant_id' => tenant('id')]);
    $member = User::factory()->create([
        'tenant_id' => tenant('id'),
        'status' => 'active',
    ]);

    $this->actingAs($admin);

    Livewire::test(TeamManagement::class)
        ->call('revokeAccess', $member->id)
        ->assertSee(__('User access revoked.'));

    expect($member->fresh()->trashed())->toBeTrue()
        ->and($member->fresh()->status)->toBe('inactive');
});

test('cancels pending invitation via dedicated action', function () {
    $admin = User::factory()->create(['tenant_id' => tenant('id')]);
    $role = Role::where('name', 'member')->first();

    $invitation = Invitation::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => tenant('id'),
        'email' => 'pending@example.com',
        'role_id' => $role->id,
        'token_hash' => hash('sha256', Str::random(32)),
        'expires_at' => now()->addDays(2),
    ]);

    $this->actingAs($admin);

    Livewire::test(TeamManagement::class)
        ->call('cancelInvitation', $invitation->id)
        ->assertSee(__('Invitation cancelled.'));

    expect(Invitation::find($invitation->id))->toBeNull();
});
