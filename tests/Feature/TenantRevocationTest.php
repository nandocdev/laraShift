<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Http\Middleware\EnsureUserIsActive;
use App\Modules\Tenant\Identity\Http\Middleware\EnsureUserBelongsToTenant;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('kicks out an inactive user immediately via middleware', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'revocation-test',
        'name' => 'Revocation Test',
        'email' => 'revocation@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($tenant);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Bad User',
        'email' => 'bad@test.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->actingAs($user);

    // Mock a protected route
    Route::get('/test-active', function () {
        return 'success';
    })->middleware(['web', EnsureUserIsActive::class]);

    // 1. Initial request (active)
    $this->get('/test-active')->assertStatus(200);

    // 2. Revoke access
    $user->update(['status' => 'inactive']);

    // 3. Next request (kicked out)
    $response = $this->get('/test-active');
    
    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

it('allows inviting an email that belongs to another tenant', function () {
    $tenantA = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'tenant-a',
        'name' => 'Tenant A',
        'email' => 'a@test.com',
    ]);

    $userA = User::create([
        'tenant_id' => $tenantA->id,
        'name' => 'User A',
        'email' => 'shared@test.com',
        'password' => 'password',
    ]);

    $tenantB = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'tenant-b',
        'name' => 'Tenant B',
        'email' => 'b@test.com',
    ]);

    tenancy()->initialize($tenantB);
    
    // We need roles to exist for the invitation to work
    app(\App\Modules\Tenant\Identity\Actions\EnsureTenantRolesExistAction::class)->execute($tenantB);

    $action = app(\App\Modules\Tenant\Identity\Actions\SendInvitationAction::class);

    // This should NOT throw an exception anymore
    $invitation = $action->execute(new \App\Modules\Tenant\Identity\DTOs\InvitationData(
        email: 'shared@test.com',
        roleName: 'member'
    ), $userA);

    expect($invitation->email)->toBe('shared@test.com');
    expect($invitation->tenant_id)->toBe($tenantB->id);
});

it('returns 404 for cross-tenant access attempts', function () {
    $tenantA = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'tenant-a',
        'name' => 'Tenant A',
        'email' => 'a@test.com',
    ]);
    $domainA = 'a.' . config('tenancy.central_domain');
    $tenantA->domains()->create(['domain' => $domainA]);

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
    $domainB = 'b.' . config('tenancy.central_domain');
    $tenantB->domains()->create(['domain' => $domainB]);

    // Act as User A but try to access Tenant B domain
    $this->actingAs($userA);

    // Mock a route in the tenant group
    Route::get('/api/resource', function () { return 'ok'; })
        ->middleware(['web', EnsureUserBelongsToTenant::class]);

    $this->get("http://{$domainB}/api/resource")
        ->assertStatus(404);
});
