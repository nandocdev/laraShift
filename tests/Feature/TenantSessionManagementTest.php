<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Actions\EnsureTenantRolesExistAction;
use App\Modules\Tenant\Identity\Actions\EnsureTenantSessionLimitAction;
use App\Modules\Tenant\Identity\Actions\InvalidateUserSessionsAction;
use App\Modules\Tenant\Identity\Models\TenantSession;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $plan = Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free Plan',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => ['quotas' => ['max_sessions' => 3]],
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'session-test-'.Str::random(4),
        'name' => 'Session Test',
        'email' => 'session@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($this->tenant);
    app(EnsureTenantRolesExistAction::class)->execute($this->tenant);

    $this->user = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test User',
        'email' => 'user@test.com',
        'password' => 'password',
    ]);
});

test('tenant session model stores session data', function () {
    $session = TenantSession::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'session_id' => Session::getId(),
        'ip' => '127.0.0.1',
        'user_agent' => 'Test',
        'issued_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    expect($session->tenant_id)->toBe($this->tenant->id);
    expect($session->isActive())->toBeTrue();
});

test('ensure session limit revokes oldest when exceeded', function () {
    $action = app(EnsureTenantSessionLimitAction::class);

    for ($i = 0; $i < 4; $i++) {
        TenantSession::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'session_id' => Str::random(40),
            'issued_at' => now()->subMinutes(10 - $i),
            'expires_at' => now()->addHour(),
        ]);
    }

    expect(TenantSession::active()->count())->toBe(4);

    $action->execute($this->tenant->id, $this->user->id, 3);

    expect(TenantSession::active()->count())->toBe(3);
});

test('resolve limit reads from plan quotas', function () {
    $action = app(EnsureTenantSessionLimitAction::class);

    $limit = $action->resolveLimit($this->tenant);

    expect($limit)->toBe(3);
});

test('invalidate user sessions revokes all active sessions', function () {
    for ($i = 0; $i < 3; $i++) {
        TenantSession::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'session_id' => Str::random(40),
            'issued_at' => now()->subMinutes($i),
            'expires_at' => now()->addHour(),
        ]);
    }

    $admin = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Admin',
        'email' => 'admin-session@test.com',
        'password' => 'password',
    ]);

    $action = app(InvalidateUserSessionsAction::class);
    $count = $action->execute($this->user, $admin);

    expect($count)->toBe(3);
    expect(TenantSession::active()->count())->toBe(0);
});

test('session revocation logs activity', function () {
    TenantSession::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'session_id' => Str::random(40),
        'issued_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    $admin = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Admin',
        'email' => 'admin-log@test.com',
        'password' => 'password',
    ]);

    $action = app(InvalidateUserSessionsAction::class);
    $action->execute($this->user, $admin);

    $logs = Activity::where('description', 'tenant_user_sessions_revoked')->get();

    expect($logs)->not->toBeEmpty();
});

test('expired sessions are not considered active', function () {
    TenantSession::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'session_id' => Str::random(40),
        'issued_at' => now()->subDays(2),
        'expires_at' => now()->subDay(),
    ]);

    expect(TenantSession::active()->count())->toBe(0);
});
