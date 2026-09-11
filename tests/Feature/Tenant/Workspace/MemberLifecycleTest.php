<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\Role;
use App\Modules\Tenant\Access\Domain\Models\TenantSession;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Access\Domain\Models\UserMfa;
use App\Modules\Tenant\Compliance\Domain\Models\AuditLog;
use App\Modules\Tenant\Workspace\Interface\Livewire\TeamManagement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $id = (string) Str::uuid();
    $this->tenant = Tenant::create([
        'id' => $id,
        'slug' => 'lifecycle-'.substr($id, 0, 8),
        'name' => 'Lifecycle Tenant',
        'email' => 'lifecycle-'.substr($id, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);
    tenancy()->initialize($this->tenant);

    app(EnsureTenantRolesExist::class)->execute($this->tenant);
    setPermissionsTeamId($this->tenant->id);

    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $this->admin->assignRole('admin');

    $this->actingAs($this->admin);
});

it('restores a revoked member', function () {
    $member = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'inactive']);
    $member->assignRole('member');
    $member->delete();

    Livewire::test(TeamManagement::class)
        ->call('restoreAccess', $member->id)
        ->assertHasNoErrors();

    expect($member->fresh()->trashed())->toBeFalse()
        ->and($member->fresh()->status)->toBe('active');
});

it('lists revoked members for regularization', function () {
    $member = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'inactive',
        'email' => 'revoked-visible@example.com',
    ]);
    $member->delete();

    Livewire::test(TeamManagement::class)
        ->assertSee('revoked-visible@example.com');
});

it('permanently deletes a member with cascades and keeps the audit trail', function () {
    $member = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $member->assignRole('member');

    TenantSession::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $member->id,
        'session_id' => Str::random(40),
    ]);
    UserMfa::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $member->id,
        'method' => 'totp',
        'secret' => 'secret',
        'recovery_codes' => ['rc-1'],
        'enrolled_at' => now(),
    ]);
    AuditLog::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $member->id,
        'action' => 'auth.login',
    ]);

    Livewire::test(TeamManagement::class)
        ->call('deleteUser', $member->id)
        ->assertHasNoErrors();

    expect(User::withTrashed()->find($member->id))->toBeNull()
        ->and(TenantSession::where('user_id', $member->id)->count())->toBe(0)
        ->and(UserMfa::where('user_id', $member->id)->count())->toBe(0)
        ->and(AuditLog::where('user_id', $member->id)->count())->toBe(1)
        ->and(DB::table('model_has_roles')->where('model_id', $member->id)->count())->toBe(0);
});

it('refuses self-deletion', function () {
    Livewire::test(TeamManagement::class)
        ->call('deleteUser', $this->admin->id)
        ->assertHasNoErrors();

    expect(User::find($this->admin->id))->not->toBeNull();
});

it('refuses to delete the last administrator', function () {
    $permission = new Permission(['name' => 'team:manage', 'guard_name' => 'web']);
    $permission->id = (string) Str::uuid();
    $permission->save();
    $managerRole = Role::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'manager',
        'guard_name' => 'web',
    ]);
    $managerRole->givePermissionTo('team:manage');

    $manager = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $manager->assignRole('manager');
    $this->actingAs($manager);

    Livewire::test(TeamManagement::class)
        ->call('deleteUser', $this->admin->id)
        ->assertHasNoErrors();

    expect(User::find($this->admin->id))->not->toBeNull();
});

it('forbids permanent deletion for members without team:manage', function () {
    $member = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $member->assignRole('member');

    $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $other->assignRole('member');
    $this->actingAs($other);

    Livewire::test(TeamManagement::class)
        ->call('deleteUser', $member->id)
        ->assertForbidden();

    expect(User::find($member->id))->not->toBeNull();
});
