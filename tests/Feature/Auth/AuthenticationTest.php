<?php

use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

test('login screen can be rendered', function () {
    $create = app(\App\Modules\Central\Provisioning\Actions\CreateTenantAction::class);
    $data = new \App\Modules\Central\Provisioning\DTOs\CreateTenantData(
        name: 'Acme Test',
        slug: 'acme-test',
        email: 'admin@acme.test',
    );

    $tenant = $create->execute($data);
    $domain = $tenant->domains()->first()->domain;

    $response = $this->get('http://' . $domain . '/login');

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $create = app(\App\Modules\Central\Provisioning\Actions\CreateTenantAction::class);
    $data = new \App\Modules\Central\Provisioning\DTOs\CreateTenantData(
        name: 'Acme Test',
        slug: 'acme-auth',
        email: 'admin@acme.test',
    );

    $tenant = $create->execute($data);
    $domain = $tenant->domains()->first()->domain;

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Test User',
        'email' => 'user@acme.test',
        'password' => Hash::make('password'),
    ]);

    $response = $this->post('http://' . $domain . route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $create = app(\App\Modules\Central\Provisioning\Actions\CreateTenantAction::class);
    $data = new \App\Modules\Central\Provisioning\DTOs\CreateTenantData(
        name: 'Acme Test',
        slug: 'acme-auth-fail',
        email: 'admin@acme.test',
    );

    $tenant = $create->execute($data);
    $domain = $tenant->domains()->first()->domain;

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Test User',
        'email' => 'user2@acme.test',
        'password' => Hash::make('password'),
    ]);

    $response = $this->post('http://' . $domain . route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $create = app(\App\Modules\Central\Provisioning\Actions\CreateTenantAction::class);
    $data = new \App\Modules\Central\Provisioning\DTOs\CreateTenantData(
        name: 'Acme Test',
        slug: 'acme-2fa',
        email: 'admin@acme.test',
    );

    $tenant = $create->execute($data);
    $domain = $tenant->domains()->first()->domain;

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Two Factor User',
        'email' => '2fa@acme.test',
        'password' => Hash::make('password'),
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->post('http://' . $domain . route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $create = app(\App\Modules\Central\Provisioning\Actions\CreateTenantAction::class);
    $data = new \App\Modules\Central\Provisioning\DTOs\CreateTenantData(
        name: 'Acme Test',
        slug: 'acme-logout',
        email: 'admin@acme.test',
    );

    $tenant = $create->execute($data);
    $domain = $tenant->domains()->first()->domain;

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Logout User',
        'email' => 'logout@acme.test',
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->post('http://' . $domain . route('logout'));

    $response->assertRedirect('/');

    $this->assertGuest();
});
