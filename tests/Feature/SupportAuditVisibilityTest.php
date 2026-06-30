<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Actions\QueryTenantAuditLogsAction;
use App\Modules\Tenant\Audit\Enums\AuditAction;
use App\Modules\Tenant\Audit\Models\AuditLog;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'support-visibility',
        'name' => 'Support Visibility Test',
        'email' => 'support-vis@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($this->tenant);

    $this->user = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Jane Doe',
        'email' => 'jane@test.com',
        'password' => 'password',
    ]);

    // Create audit logs for each severity level
    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'action' => AuditAction::AUTH_LOGIN->value,
        'resource' => 'users',
        'resource_id' => $this->user->id,
    ]);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'action' => AuditAction::ROLE_CREATED->value,
        'resource' => 'roles',
    ]);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'action' => AuditAction::SETTINGS_UPDATED->value,
        'resource' => 'settings',
    ]);

    AuditLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'action' => AuditAction::EXPORT_STARTED->value,
        'resource' => 'exports',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('only returns CRITICAL and HIGH severity actions for support', function () {
    $action = app(QueryTenantAuditLogsAction::class);

    $results = $action->query($this->tenant);

    $actions = array_map(fn ($entry) => $entry->action, $results);

    expect($actions)->toContain(AuditAction::AUTH_LOGIN->value);
    expect($actions)->toContain(AuditAction::ROLE_CREATED->value);
    expect($actions)->not->toContain(AuditAction::SETTINGS_UPDATED->value);
    expect($actions)->not->toContain(AuditAction::EXPORT_STARTED->value);
});

it('maps user id to user name without exposing email', function () {
    $action = app(QueryTenantAuditLogsAction::class);

    $results = $action->query($this->tenant);

    foreach ($results as $entry) {
        expect($entry->userName)->toBe('Jane Doe');
        expect($entry)->not->toHaveProperty('email');
    }
});

it('includes severity level in each entry', function () {
    $action = app(QueryTenantAuditLogsAction::class);

    $results = $action->query($this->tenant);

    foreach ($results as $entry) {
        expect($entry->severity)->toBeIn(['CRITICAL', 'HIGH']);
    }
});

it('does not expose IP address or metadata', function () {
    $action = app(QueryTenantAuditLogsAction::class);

    $results = $action->query($this->tenant);

    foreach ($results as $entry) {
        expect($entry)->not->toHaveProperty('ip');
        expect($entry)->not->toHaveProperty('metadata');
    }
});

it('filters by date range', function () {
    $action = app(QueryTenantAuditLogsAction::class);

    AuditLog::where('action', AuditAction::AUTH_LOGIN->value)->update([
        'created_at' => now()->subDays(60),
    ]);

    $results = $action->query($this->tenant, [
        'date_from' => now()->subDays(30)->format('Y-m-d'),
        'date_to' => now()->format('Y-m-d'),
    ]);

    $loginEntries = array_filter($results, fn ($e) => $e->action === AuditAction::AUTH_LOGIN->value);

    expect($loginEntries)->toBeEmpty();
});

it('enforces maximum range of 90 days', function () {
    $action = app(QueryTenantAuditLogsAction::class);

    $results = $action->query($this->tenant, [
        'date_from' => now()->subDays(200)->format('Y-m-d'),
        'date_to' => now()->format('Y-m-d'),
    ]);

    expect($results)->toBeArray();
});

it('returns empty array for tenant with no audit logs', function () {
    AuditLog::query()->delete();

    $action = app(QueryTenantAuditLogsAction::class);

    $results = $action->query($this->tenant);

    expect($results)->toBe([]);
});

it('enforces result limit', function () {
    for ($i = 0; $i < 10; $i++) {
        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => AuditAction::AUTH_LOGIN->value,
            'resource' => 'users',
        ]);
    }

    $action = app(QueryTenantAuditLogsAction::class);

    $results = $action->query($this->tenant, ['limit' => 3]);

    expect(count($results))->toBeLessThanOrEqual(3);
});

it('initializes and cleans up tenancy during query', function () {
    $action = app(QueryTenantAuditLogsAction::class);

    $action->query($this->tenant);

    expect(tenancy()->initialized)->toBeFalse();
});
