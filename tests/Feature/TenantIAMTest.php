<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Identity\Models\Role;
use App\Modules\Tenant\Identity\Actions\EnsureTenantRolesExistAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('scopes roles and permissions per tenant', function () {
    $tenantA = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'tenant-a',
        'name' => 'Tenant A',
        'email' => 'a@test.com',
    ]);

    $tenantB = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'tenant-b',
        'name' => 'Tenant B',
        'email' => 'b@test.com',
    ]);

    $ensureRoles = app(EnsureTenantRolesExistAction::class);
    $ensureRoles->execute($tenantA);
    $ensureRoles->execute($tenantB);

    // Count roles in DB
    expect(Role::count())->toBe(6); // 3 per tenant

    // Test scoping
    tenancy()->initialize($tenantA);
    expect(Role::count())->toBe(3);
    expect(Role::where('name', 'admin')->first()->tenant_id)->toBe($tenantA->id);

    tenancy()->initialize($tenantB);
    expect(Role::count())->toBe(3);
    expect(Role::where('name', 'admin')->first()->tenant_id)->toBe($tenantB->id);
});

it('prevents cross-tenant authentication', function () {
    $tenantA = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'tenant-a',
        'name' => 'Tenant A',
        'email' => 'a@test.com',
    ]);

    $userA = User::create([
        'tenant_id' => $tenantA->id,
        'name' => 'User A',
        'email' => 'user@test.com',
        'password' => 'password',
    ]);

    $tenantB = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'tenant-b',
        'name' => 'Tenant B',
        'email' => 'b@test.com',
    ]);

    // Try to login into Tenant B with User A
    tenancy()->initialize($tenantB);
    
    $foundUser = User::where('email', 'user@test.com')->first();
    
    expect($foundUser)->toBeNull();
});
