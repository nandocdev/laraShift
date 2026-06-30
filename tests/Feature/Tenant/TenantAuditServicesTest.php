<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Audit\Actions\PurgeAuditLogsAction;
use App\Modules\Tenant\Audit\Actions\SearchAuditLogsAction;
use App\Modules\Tenant\Audit\Actions\SupportVisibilityAction;
use App\Modules\Tenant\Audit\Enums\AuditEventCatalog;
use App\Modules\Tenant\Audit\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'audit-svc-' . Str::random(4),
        'name' => 'Audit Test',
        'email' => 'audit-svc@test.com',
        'plan_id' => 'free',
    ]);
});

test('search audit logs returns paginated results', function () {
    tenancy()->initialize($this->tenant);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'action' => 'auth.login',
        'resource' => 'user',
        'ip' => '127.0.0.1',
    ]);

    $action = app(SearchAuditLogsAction::class);
    $results = $action->execute([]);

    expect($results->total())->toBe(1);
});

test('search audit logs filters by action', function () {
    tenancy()->initialize($this->tenant);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'action' => 'auth.login',
        'resource' => 'user',
        'ip' => '127.0.0.1',
    ]);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'action' => 'settings.updated',
        'resource' => 'settings',
        'ip' => '127.0.0.1',
    ]);

    $action = app(SearchAuditLogsAction::class);
    $results = $action->execute(['action' => 'auth.login']);

    expect($results->total())->toBe(1);
});

test('search audit logs filters by date range', function () {
    tenancy()->initialize($this->tenant);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'action' => 'auth.login',
        'resource' => 'user',
        'created_at' => now()->subDays(5),
        'ip' => '127.0.0.1',
    ]);

    $action = app(SearchAuditLogsAction::class);
    $results = $action->execute(['date_from' => now()->subDays(10)->format('Y-m-d')]);

    expect($results->total())->toBe(1);
});

test('purge deletes old logs beyond retention', function () {
    tenancy()->initialize($this->tenant);

    $log = AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'action' => 'auth.login',
        'resource' => 'user',
        'ip' => '127.0.0.1',
    ]);

    AuditLog::withoutGlobalScopes()->where('id', $log->id)->update(['created_at' => now()->subDays(60)]);
    $log->refresh();

    $this->assertTrue($log->created_at->lt(now()->subDays(29)), 'Log should be older than 30 days');

    $action = app(PurgeAuditLogsAction::class);
    $deleted = $action->execute($this->tenant);

    expect($deleted)->toBe(1);
});

test('purge keeps recent logs within retention', function () {
    tenancy()->initialize($this->tenant);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'action' => 'auth.login',
        'resource' => 'user',
        'created_at' => now()->subDays(5),
        'ip' => '127.0.0.1',
    ]);

    $action = app(PurgeAuditLogsAction::class);
    $deleted = $action->execute($this->tenant);

    expect($deleted)->toBe(0);
});

test('support visibility filters allowed resources', function () {
    tenancy()->initialize($this->tenant);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'action' => 'settings.updated',
        'resource' => 'settings',
        'metadata' => ['timezone' => 'UTC'],
        'ip' => '127.0.0.1',
    ]);

    $action = app(SupportVisibilityAction::class);
    $logs = $action->visibleLogs($this->tenant->id);

    expect($logs)->not->toBeEmpty();
});

test('support visibility redacts sensitive fields', function () {
    tenancy()->initialize($this->tenant);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'action' => 'settings.updated',
        'resource' => 'settings',
        'metadata' => ['smtp_password' => 'secret123', 'timezone' => 'UTC'],
        'ip' => '127.0.0.1',
    ]);

    $action = app(SupportVisibilityAction::class);
    $logs = $action->visibleLogs($this->tenant->id);

    expect($logs[0]['metadata']['smtp_password'])->toBe('[REDACTED]');
    expect($logs[0]['metadata']['timezone'])->toBe('UTC');
});

test('audit event catalog has all events', function () {
    $catalog = AuditEventCatalog::all();

    expect(count($catalog))->toBeGreaterThanOrEqual(13);
});

test('audit event catalog has severity levels', function () {
    expect(AuditEventCatalog::severity('auth.login'))->toBe('info');
    expect(AuditEventCatalog::severity('user.revoked'))->toBe('warning');
});

test('support visibility returns allowed resources list', function () {
    $resources = SupportVisibilityAction::allowedResources();

    expect($resources)->toContain('settings', 'user');
});
