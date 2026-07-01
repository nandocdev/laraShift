<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Actions\LoginCentralUserAction;
use App\Modules\Central\Auth\DTOs\LoginData;
use App\Modules\Central\Auth\Livewire\TwoFactorEnrollment;
use App\Modules\Central\Auth\Models\Central2FA;
use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

it('requires 2fa if enabled', function () {
    $password = 'password123';
    $user = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make($password),
    ]);

    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();

    Central2FA::create([
        'id' => Str::uuid()->toString(),
        'user_id' => $user->id,
        'method' => 'totp',
        'secret' => $secret,
        'recovery_codes' => [],
        'enrolled_at' => now(),
    ]);

    $action = app(LoginCentralUserAction::class);
    $data = new LoginData(
        email: 'admin@example.com',
        password: $password,
        remember: false
    );

    $result = $action->execute($data);

    expect($result)->toBe('requires_2fa');
    expect(Session::get('login.id'))->toBe($user->id);
});

it('logs in directly if 2fa is disabled', function () {
    $password = 'password123';
    $user = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make($password),
    ]);

    $action = app(LoginCentralUserAction::class);
    $data = new LoginData(
        email: 'admin@example.com',
        password: $password,
        remember: false
    );

    $result = $action->execute($data);

    expect($result)->toBe('success');
    expect(auth('central')->check())->toBeTrue();
});

it('renders two factor enrollment page when authenticated', function () {
    $password = 'password123';
    $user = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Admin',
        'email' => 'admin@2fa-test.com',
        'password' => Hash::make($password),
    ]);

    $this->actingAs($user, 'central');

    Livewire::test(TwoFactorEnrollment::class)
        ->assertStatus(200)
        ->assertSee(__('Two-Factor Authentication'));
});

it('initiates 2fa enrollment and shows qr code', function () {
    $password = 'password123';
    $user = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Admin',
        'email' => 'admin@2fa-enroll-test.com',
        'password' => Hash::make($password),
    ]);

    $this->actingAs($user, 'central');

    Livewire::test(TwoFactorEnrollment::class)
        ->call('initiate')
        ->assertSet('showingQrCode', true);
});
