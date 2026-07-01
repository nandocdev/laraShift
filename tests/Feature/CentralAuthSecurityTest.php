<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Actions\LockoutCentralUserAction;
use App\Modules\Central\Auth\Actions\LoginCentralUserAction;
use App\Modules\Central\Auth\Actions\LogoutCentralUserAction;
use App\Modules\Central\Auth\DTOs\LoginData;
use App\Modules\Central\Auth\Models\CentralSession;
use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Shared\Tenancy\Services\PostLoginResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->password = 'secure-password-123';
    $this->user = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Admin',
        'email' => 'admin@security-test.com',
        'password' => Hash::make($this->password),
    ]);
});

test('login fails with invalid credentials', function () {
    $action = app(LoginCentralUserAction::class);
    $data = new LoginData(
        email: 'admin@security-test.com',
        password: 'wrong-password',
        remember: false,
    );

    $result = $action->execute($data);

    expect($result)->toBe('failed');
});

test('brute force protection locks account after max attempts', function () {
    $lockout = app(LockoutCentralUserAction::class);

    for ($i = 0; $i < 6; $i++) {
        $lockout->recordAttempt('admin@security-test.com');
    }

    expect($lockout->isLocked('admin@security-test.com'))->toBeTrue();
    expect($lockout->remainingAttempts('admin@security-test.com'))->toBe(0);
});

test('lockout clears after successful login', function () {
    Cache::forget('central_login_attempts:admin@security-test.com');

    $lockout = app(LockoutCentralUserAction::class);

    $lockout->recordAttempt('admin@security-test.com');
    $lockout->recordAttempt('admin@security-test.com');
    expect($lockout->isLocked('admin@security-test.com'))->toBeFalse();

    $lockout->clearAttempts('admin@security-test.com');
    expect($lockout->remainingAttempts('admin@security-test.com'))->toBe(5);
});

test('session is invalidated after logout', function () {
    $action = app(LoginCentralUserAction::class);
    $data = new LoginData(
        email: 'admin@security-test.com',
        password: $this->password,
        remember: false,
    );

    $result = $action->execute($data);
    expect($result)->toBe('success');

    $action->recordSession($this->user);

    $sessionId = Session::getId();

    $logoutAction = app(LogoutCentralUserAction::class);
    $logoutAction->execute();

    $session = CentralSession::where('session_id', $sessionId)->first();
    expect($session)->not->toBeNull();
    expect($session->revoked_at)->not->toBeNull();
});

test('session token regenerates on login', function () {
    $action = app(LoginCentralUserAction::class);
    $data = new LoginData(
        email: 'admin@security-test.com',
        password: $this->password,
        remember: false,
    );

    $oldToken = Session::token();

    $action->execute($data);

    expect(Session::token())->not->toBe($oldToken);
});

test('post login resolver generates tenant url from slug', function () {
    config(['tenancy.central_domain' => 'larashift.test']);
    config(['tenancy.central_domains' => ['127.0.0.1', 'localhost', 'larashift.test']]);

    $resolver = app(PostLoginResolver::class);

    expect($resolver->isTenantUrl('http://tenant-one.larashift.test'))->toBeTrue();
    expect($resolver->isTenantUrl('http://larashift.test'))->toBeFalse();
});

test('old sessions are revoked when session limit exceeded', function () {
    $action = app(LoginCentralUserAction::class);
    $data = new LoginData(
        email: 'admin@security-test.com',
        password: $this->password,
        remember: false,
    );

    for ($i = 0; $i < 4; $i++) {
        Session::flush();
        Session::start();
        $action->execute($data);
        $action->recordSession($this->user);
    }

    $activeSessions = CentralSession::where('user_id', $this->user->id)
        ->whereNull('revoked_at')
        ->count();

    expect($activeSessions)->toBeLessThanOrEqual(3);
});

test('lockout action creates audit log on lock', function () {
    $lockout = app(LockoutCentralUserAction::class);

    for ($i = 0; $i < 5; $i++) {
        $lockout->recordAttempt('admin@security-test.com');
    }

    $this->user->refresh();
    expect($this->user->locked_until)->not->toBeNull();
});

test('clear lockout resets user locked_until', function () {
    $lockout = app(LockoutCentralUserAction::class);

    for ($i = 0; $i < 5; $i++) {
        $lockout->recordAttempt('admin@security-test.com');
    }

    $lockout->clearLockout('admin@security-test.com');

    $this->user->refresh();
    expect($this->user->locked_until)->toBeNull();
});

test('remaining attempts decreases after failed login', function () {
    $lockout = app(LockoutCentralUserAction::class);

    $lockout->recordAttempt('admin@security-test.com');
    expect($lockout->remainingAttempts('admin@security-test.com'))->toBe(4);

    $lockout->recordAttempt('admin@security-test.com');
    expect($lockout->remainingAttempts('admin@security-test.com'))->toBe(3);
});

test('validate central session redirects revoked session to login', function () {
    $action = app(LoginCentralUserAction::class);
    $data = new LoginData(
        email: 'admin@security-test.com',
        password: $this->password,
        remember: false,
    );

    $action->execute($data);
    $action->completeLogin($this->user, false);

    $session = CentralSession::where('user_id', $this->user->id)->first();
    $session->revoke('Test revocation');

    $this
        ->withSession(['cart' => 'test'])
        ->get(route('central.dashboard'))
        ->assertRedirect(route('central.login'));
});

test('record session creates a central session entry', function () {
    $action = app(LoginCentralUserAction::class);
    $data = new LoginData(
        email: 'admin@security-test.com',
        password: $this->password,
        remember: false,
    );

    $action->execute($data);
    $action->completeLogin($this->user, false);

    $session = CentralSession::where('user_id', $this->user->id)
        ->whereNull('revoked_at')
        ->first();

    expect($session)->not->toBeNull();
    expect($session->ip)->toBe('127.0.0.1');
});
