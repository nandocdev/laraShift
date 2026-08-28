<?php

declare(strict_types=1);

use App\Modules\Central\Operations\Infrastructure\Clients\RailwayService;
use App\Modules\Central\Provisioning\Actions\ProvisionTenantPipeline;
use App\Modules\Central\Provisioning\Jobs\ProvisionTenantJob;
use App\Modules\Central\Provisioning\Models\Domain;
use App\Modules\Central\Provisioning\Models\ProvisioningLog;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Domain\Models\Role;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->makeTenant = function (string $slug, string $status = 'provisioning'): Tenant {
        return Tenant::create([
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'name' => Str::headline($slug),
            'email' => $slug.'@test.com',
            'plan_id' => 'free',
            'status' => $status,
        ]);
    };
});

it('provisions a tenant in the background and creates the initial data', function () {
    $tenant = ($this->makeTenant)('pipeline-active');

    ProvisionTenantJob::dispatch($tenant->id, 'admin@acme.com', 'secret', 'Administrator', 'active');

    $tenant->refresh();

    expect($tenant->status)->toBe('active');
    expect($tenant->provisioned_at)->not->toBeNull();

    $tenant->run(function () {
        expect(User::where('email', 'admin@acme.com')->exists())->toBeTrue();
        expect(Role::count())->toBeGreaterThanOrEqual(2);
        expect(TenantSetting::exists())->toBeTrue();
    });

    expect(ProvisioningLog::where('tenant_id', $tenant->id)->pluck('status')->all())
        ->each->toBe('completed');
});

it('finalizes to pending_payment when the plan requires payment', function () {
    $tenant = ($this->makeTenant)('pipeline-paid');

    ProvisionTenantJob::dispatch($tenant->id, 'admin@acme.com', null, 'Administrator', 'pending_payment');

    expect($tenant->fresh()->status)->toBe('pending_payment');
    expect($tenant->fresh()->provisioned_at)->not->toBeNull();
});

it('resumes from completed steps instead of restarting', function () {
    $tenant = ($this->makeTenant)('pipeline-resume');

    ProvisioningLog::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenant->id,
        'step' => 'db_schema',
        'status' => 'completed',
        'executed_at' => now(),
    ]);

    ProvisionTenantJob::dispatch($tenant->id, 'admin@acme.com', 'secret', 'Administrator', 'active');

    $tenant->refresh();
    expect($tenant->status)->toBe('active');

    $tenant->run(function () {
        expect(User::where('email', 'admin@acme.com')->exists())->toBeTrue();
    });

    $schemaLogs = ProvisioningLog::where('tenant_id', $tenant->id)->where('step', 'db_schema')->get();
    expect($schemaLogs)->toHaveCount(1);
    expect($schemaLogs->first()->status)->toBe('completed');
});

it('records a failed step and blocks finalization, then resumes on retry', function () {
    $tenant = ($this->makeTenant)('pipeline-fail');

    Domain::create([
        'domain' => 'pipeline-fail.'.config('tenancy.central_domain'),
        'tenant_id' => $tenant->id,
    ]);

    $railway = Mockery::mock(RailwayService::class);
    $railway->shouldReceive('provisionDomain')->once()->andThrow(new RuntimeException('boom'));
    app()->instance(RailwayService::class, $railway);

    $pipeline = app(ProvisionTenantPipeline::class);

    expect(fn () => $pipeline->execute($tenant->id, 'admin@acme.com', null, 'Administrator', 'active'))
        ->toThrow(RuntimeException::class, 'boom');

    $tenant->refresh();
    expect($tenant->status)->toBe('provisioning');
    expect(ProvisioningLog::where('tenant_id', $tenant->id)->where('step', 'db_schema')->first()->status)->toBe('completed');
    expect(ProvisioningLog::where('tenant_id', $tenant->id)->where('step', 'infrastructure')->first()->status)->toBe('failed');

    // Retry: infrastructure now succeeds, the rest of the pipeline resumes
    $railway = Mockery::mock(RailwayService::class);
    $railway->shouldReceive('provisionDomain')->once()->andReturn(true);
    app()->instance(RailwayService::class, $railway);

    $pipeline = app(ProvisionTenantPipeline::class);
    $pipeline->execute($tenant->id, 'admin@acme.com', null, 'Administrator', 'active');

    $tenant->refresh();
    expect($tenant->status)->toBe('active');

    $tenant->run(function () {
        expect(User::where('email', 'admin@acme.com')->exists())->toBeTrue();
    });

    expect(ProvisioningLog::where('tenant_id', $tenant->id)->pluck('status')->all())
        ->each->toBe('completed');
});

it('rejects provisioning a tenant that is not in a provisionable state', function () {
    $tenant = ($this->makeTenant)('pipeline-maintenance', 'maintenance');

    expect(fn () => app(ProvisionTenantPipeline::class)->execute($tenant->id, 'admin@acme.com', null, 'Administrator', 'active'))
        ->toThrow(RuntimeException::class, 'Cannot provision tenant');
});
