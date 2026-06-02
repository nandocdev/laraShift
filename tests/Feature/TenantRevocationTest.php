<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Http\Middleware\EnsureUserIsActive;
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
        'is_active' => true,
    ]);

    $this->actingAs($user);

    // Mock a protected route
    Route::get('/test-active', function () {
        return 'success';
    })->middleware(['web', EnsureUserIsActive::class]);

    // 1. Initial request (active)
    $this->get('/test-active')->assertStatus(200);

    // 2. Revoke access
    $user->update(['is_active' => false]);

    // 3. Next request (kicked out)
    $response = $this->get('/test-active');
    
    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

it('prevents inviting an email that belongs to another tenant', function () {
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

    $action = app(\App\Modules\Tenant\Identity\Actions\SendInvitationAction::class);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('This email is already associated with another organization.');

    $action->execute('shared@test.com', 'member', $userA); // UserA is inviter but in TenantB context
});
