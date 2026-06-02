<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Identity\Models\Role;
use App\Modules\Tenant\Identity\Models\Invitation;
use App\Modules\Tenant\Identity\Actions\SendInvitationAction;
use App\Modules\Tenant\Identity\Actions\EnsureTenantRolesExistAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('enforces a limit of 10 pending invitations', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'quota-test',
        'name' => 'Quota Test',
        'email' => 'quota@test.com',
    ]);

    tenancy()->initialize($tenant);
    app(EnsureTenantRolesExistAction::class)->execute($tenant);

    $admin = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);

    Notification::fake();
    $action = app(SendInvitationAction::class);

    // Create 10 invitations
    for ($i = 0; $i < 10; $i++) {
        $action->execute("user{$i}@test.com", 'member', $admin);
    }

    expect(Invitation::count())->toBe(10);

    // 11th should fail
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Maximum limit of pending invitations reached (10).');
    
    $action->execute("extra@test.com", 'member', $admin);
});

it('aborts with 410 if invitation is expired', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'expire-test',
        'name' => 'Expire Test',
        'email' => 'expire@test.com',
        'plan_id' => 'free',
    ]);
    $tenant->domains()->create(['domain' => 'expire-test.larashift.test']);

    tenancy()->initialize($tenant);

    $invitation = Invitation::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'email' => 'expired@test.com',
        'token_hash' => hash('sha256', 'expired-token'),
        'expires_at' => now()->subDay(),
    ]);

    // Simulating Livewire mount logic via direct call or testing the route
    $this->get('http://expire-test.larashift.test' . route('tenant.invitations.accept', ['token' => 'expired-token'], false))
        ->assertStatus(410);
});
