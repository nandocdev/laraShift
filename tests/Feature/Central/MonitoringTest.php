<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Monitoring\Actions\CheckCriticalAlertsAction;
use App\Modules\Central\Monitoring\Actions\RunTenantHealthCheckAction;
use App\Modules\Central\Monitoring\Livewire\LogViewer;
use App\Modules\Central\Monitoring\Livewire\MonitoringDashboard;
use App\Modules\Central\Monitoring\Models\TenantHealthCheck;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Monitor Admin',
        'email' => 'mon@admin.com',
        'password' => 'password',
        'is_global_admin' => true,
    ]);

    $this->actingAs($this->admin, 'central');

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'monitor-tenant',
        'name' => 'Monitor Tenant',
        'email' => 'mon@tenant.com',
        'plan_id' => 'free',
    ]);
});

it('runs health check for a tenant', function () {
    $action = app(RunTenantHealthCheckAction::class);
    $result = $action->execute($this->tenant);

    expect($result)->toBeInstanceOf(TenantHealthCheck::class);
    expect($result->check_type)->toBe('tenant_availability');
    expect($result->status)->toBeIn(['pass', 'fail']);
    expect($result->tenant_id)->toBe($this->tenant->id);
});

it('records health check result in database', function () {
    $action = app(RunTenantHealthCheckAction::class);
    $action->execute($this->tenant);

    expect(TenantHealthCheck::count())->toBe(1);

    $record = TenantHealthCheck::first();
    expect($record->tenant_id)->toBe($this->tenant->id);
    expect($record->details)->toHaveKey('tenant_initialized');
});

it('detects critical alerts for failed health checks', function () {
    TenantHealthCheck::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'check_type' => 'tenant_availability',
        'status' => 'fail',
        'message' => 'Test failure',
        'created_at' => now()->subMinutes(30),
    ]);

    $action = app(CheckCriticalAlertsAction::class);
    $alerts = $action->execute();

    $healthAlerts = array_filter($alerts, fn ($a) => $a['type'] === 'health_check_failures');

    expect($healthAlerts)->not->toBeEmpty();
    expect($healthAlerts[0]['severity'])->toBe('critical');
});

it('renders monitoring dashboard', function () {
    Livewire::test(MonitoringDashboard::class)
        ->assertStatus(200);
});

it('renders log viewer', function () {
    Livewire::test(LogViewer::class)
        ->assertStatus(200);
});
