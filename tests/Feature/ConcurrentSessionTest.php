<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Actions\LoginCentralUserAction;
use App\Modules\Central\Auth\DTOs\LoginData;
use App\Modules\Central\Auth\Models\CentralSession;
use App\Modules\Central\Auth\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('revokes the oldest session when limit is exceeded', function () {
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

    // Create 3 sessions (default limit)
    for ($i = 0; $i < 3; $i++) {
        Session::flush(); // Simulate different requests
        Session::start();
        $action->execute($data);
        $action->recordSession($user);

        // Manually adjust the last session's issued_at to ensure order
        $session = CentralSession::latest('created_at')->first();
        $session->update(['issued_at' => now()->subMinutes(10 - $i)]);
    }

    expect(CentralSession::where('user_id', $user->id)->whereNull('revoked_at')->count())->toBe(3);

    // Create 4th session
    Session::flush();
    Session::start();
    $action->execute($data);
    $action->recordSession($user);

    // Should still be 3 active sessions, and 1 revoked
    expect(CentralSession::where('user_id', $user->id)->whereNull('revoked_at')->count())->toBe(3);
    expect(CentralSession::where('user_id', $user->id)->whereNotNull('revoked_at')->count())->toBe(1);

    // The oldest should be the revoked one
    $revoked = CentralSession::where('user_id', $user->id)->whereNotNull('revoked_at')->first();
    $oldest = CentralSession::where('user_id', $user->id)->orderBy('issued_at', 'asc')->first();

    expect($revoked->id)->toBe($oldest->id);
});
