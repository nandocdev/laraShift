<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Audit\Actions\PurgeExpiredAuditLogsAction;
use App\Modules\Tenant\Audit\Models\AuditLog;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Test Plan',
        'slug' => 'test-plan',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'is_active' => true,
        'features' => ['audit_retention_days' => 90],
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'audit-purge',
        'name' => 'Audit Purge Test',
        'email' => 'purge@test.com',
        'plan_id' => 'test-plan',
    ]);

    tenancy()->initialize($this->tenant);

    $this->user = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test User',
        'email' => 'user@test.com',
        'password' => 'password',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('resolves retention days from plan features', function () {
    $action = app(PurgeExpiredAuditLogsAction::class);

    $days = $action->resolveRetentionDays($this->tenant);

    expect($days)->toBe(90);
});

it('returns default retention when plan has no value', function () {
    $this->plan->update(['features' => []]);

    $action = app(PurgeExpiredAuditLogsAction::class);

    $days = $action->resolveRetentionDays($this->tenant);

    expect($days)->toBe(365);
});

it('enforces minimum retention of 30 days', function () {
    $this->plan->update(['features' => ['audit_retention_days' => 15]]);

    $action = app(PurgeExpiredAuditLogsAction::class);

    $days = $action->resolveRetentionDays($this->tenant);

    expect($days)->toBe(30);
});

it('purges audit logs older than retention period', function () {
    $oldLog = new AuditLog([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'action' => 'auth.login',
    ]);
    $oldLog->created_at = now()->subDays(100);
    $oldLog->updated_at = now()->subDays(100);
    $oldLog->save();

    $recentLog = new AuditLog([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'action' => 'auth.login',
    ]);
    $recentLog->created_at = now()->subDays(30);
    $recentLog->updated_at = now()->subDays(30);
    $recentLog->save();

    $action = app(PurgeExpiredAuditLogsAction::class);

    $deleted = $action->execute($this->tenant);

    expect($deleted)->toBe(1);
    expect(AuditLog::count())->toBe(1);
});

it('does not purge logs within retention period', function () {
    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'action' => 'auth.login',
        'created_at' => now()->subDays(30),
        'updated_at' => now()->subDays(30),
    ]);

    $action = app(PurgeExpiredAuditLogsAction::class);

    $deleted = $action->execute($this->tenant);

    expect($deleted)->toBe(0);
    expect(AuditLog::count())->toBe(1);
});
