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

beforeEach(function () {
    \App\Modules\Central\Billing\Models\Plan::updateOrCreate(
        ['slug' => 'free'],
        [
            'name' => 'Free',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'features' => ['quotas' => ['invitations' => 5]],
        ]
    );
});

it('enforces a limit of pending invitations based on plan', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'quota-test',
        'name' => 'Quota Test',
        'email' => 'quota@test.com',
        'plan_id' => 'free',
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

    // Create 5 invitations
    for ($i = 0; $i < 5; $i++) {
        $action->execute("user{$i}@test.com", 'member', $admin);
    }

    expect(Invitation::count())->toBe(5);

    // 6th should fail
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Maximum limit of pending invitations reached for your plan.');
    
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
    $domain = 'expire-test.' . config('tenancy.central_domain');
    $tenant->domains()->create(['domain' => $domain]);

    tenancy()->initialize($tenant);

    $invitation = Invitation::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'email' => 'expired@test.com',
        'token_hash' => hash('sha256', 'expired-token'),
        'expires_at' => now()->subDay(),
    ]);

    // Simulating Livewire mount logic via direct call or testing the route
    $this->get("http://{$domain}" . route('tenant.invitations.accept', ['token' => 'expired-token'], false))
        ->assertStatus(410);
});
