<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Identity\Models\Role;
use App\Modules\Tenant\Identity\Actions\EnsureTenantRolesExistAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('protects system roles from deletion and name change', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'role-test',
        'name' => 'Role Test',
        'email' => 'role@test.com',
    ]);

    tenancy()->initialize($tenant);
    app(EnsureTenantRolesExistAction::class)->execute($tenant);

    $adminRole = Role::where('name', 'admin')->first();

    // 1. Try to delete admin role
    try {
        $adminRole->delete();
        $this->fail('System role should not be deletable.');
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('System roles cannot be deleted.');
    }

    // 2. Try to rename admin role
    try {
        $adminRole->update(['name' => 'super-admin']);
        $this->fail('System role should not be renamable.');
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('System roles cannot be renamed.');
    }
});

it('returns 409 conflict when deleting a role with active users', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'conflict-test',
        'name' => 'Conflict Test',
        'email' => 'conflict@test.com',
        'plan_id' => 'free',
    ]);
    $domain = 'conflict.' . config('tenancy.central_domain');
    $tenant->domains()->create(['domain' => $domain]);

    tenancy()->initialize($tenant);

    $role = Role::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'name' => 'editor',
        'guard_name' => 'web',
    ]);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Editor User',
        'email' => 'editor@test.com',
        'password' => 'password',
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole('editor');

    $this->actingAs($user);

    // Call the livewire component method or simulate the request
    // Since we are using abort(409) in the component, we can test it via Livewire
    Livewire::test(\App\Modules\Tenant\Identity\Livewire\RoleManagement::class)
        ->call('delete', $role->id)
        ->assertStatus(409);
        
    expect(Role::where('id', $role->id)->exists())->toBeTrue();
});
