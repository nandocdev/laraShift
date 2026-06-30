<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Actions\ReserveTenantDomainAction;
use App\Modules\Central\Provisioning\Actions\ValidateProvisioningAction;
use App\Modules\Central\Provisioning\Jobs\ProvisioningJob;
use App\Modules\Central\Provisioning\Models\ProvisioningLog;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Provisioning\Services\ProvisioningStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = Plan::firstOrCreate(['slug' => 'free'], [
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
        'slug' => 'chaos-test-'.Str::random(4),
        'name' => 'Chaos Test',
        'email' => 'chaos@test.com',
        'plan_id' => 'free',
        'status' => 'provisioning',
    ]);

    $this->stateMachine = app(ProvisioningStateMachine::class);
});

test('provisioning has correct step order', function () {
    $steps = ProvisioningStateMachine::allSteps();

    expect($steps)->toBe(['validated', 'db_created', 'migrated', 'dns_configured', 'ssl_issued', 'ready']);
});

test('state machine returns correct next step', function () {
    expect($this->stateMachine->resumeFrom($this->tenant))->toBe('validated');
});

test('pre-provisioning validation passes for valid tenant', function () {
    $action = app(ValidateProvisioningAction::class);
    $errors = $action->execute($this->tenant);

    expect($errors)->toBeEmpty();
});

test('pre-provisioning validation fails for tenant with empty name', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'no-name-'.Str::random(4),
        'name' => '',
        'email' => 'noname@test.com',
        'plan_id' => 'free',
        'status' => 'provisioning',
    ]);

    $action = app(ValidateProvisioningAction::class);
    $errors = $action->execute($tenant);

    expect($errors)->not->toBeEmpty();
});

test('provisioning job executes all steps', function () {
    Event::fake();

    $job = new ProvisioningJob(
        tenantId: $this->tenant->id,
        adminEmail: 'admin@test.com',
        adminPassword: 'password',
        slug: $this->tenant->slug,
    );

    $job->handle(
        validate: app(ValidateProvisioningAction::class),
        stateMachine: $this->stateMachine,
        reserveDomain: app(ReserveTenantDomainAction::class),
    );

    $this->tenant->refresh();
    expect($this->tenant->provisioning_status)->toBe('completed');
    expect($this->tenant->status)->toBe('active');
});

test('completed steps are tracked in provisioning logs', function () {
    Event::fake();

    $job = new ProvisioningJob(
        tenantId: $this->tenant->id,
        adminEmail: 'admin@test.com',
        adminPassword: 'password',
        slug: $this->tenant->slug,
    );

    $job->handle(
        validate: app(ValidateProvisioningAction::class),
        stateMachine: $this->stateMachine,
        reserveDomain: app(ReserveTenantDomainAction::class),
    );

    $completed = ProvisioningLog::where('tenant_id', $this->tenant->id)
        ->where('status', 'completed')
        ->count();

    expect($completed)->toBeGreaterThanOrEqual(2);
});

test('idempotency: duplicate step execution is skipped', function () {
    Event::fake();

    ProvisioningLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'step' => 'db_created',
        'status' => 'completed',
        'executed_at' => now(),
    ]);

    expect($this->stateMachine->isStepCompleted($this->tenant, 'db_created'))->toBeTrue();
});

test('resume returns first incomplete step after some completed', function () {
    ProvisioningLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'step' => 'validated',
        'status' => 'completed',
        'executed_at' => now(),
    ]);

    ProvisioningLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'step' => 'db_created',
        'status' => 'completed',
        'executed_at' => now(),
    ]);

    ProvisioningLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'step' => 'migrated',
        'status' => 'completed',
        'executed_at' => now(),
    ]);

    ProvisioningLog::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'step' => 'dns_configured',
        'status' => 'failed',
        'executed_at' => now(),
    ]);

    $resumeFrom = $this->stateMachine->resumeFrom($this->tenant);
    expect($resumeFrom)->toBe('dns_configured');
});

test('provisioning job handles failure gracefully', function () {
    $job = new ProvisioningJob(
        tenantId: 'non-existent-id',
        adminEmail: 'admin@test.com',
        adminPassword: 'password',
        slug: 'non-existent',
    );

    $this->expectNotToPerformAssertions();

    $job->handle(
        validate: app(ValidateProvisioningAction::class),
        stateMachine: $this->stateMachine,
        reserveDomain: app(ReserveTenantDomainAction::class),
    );
});

test('is complete returns true when all steps done', function () {
    foreach (ProvisioningStateMachine::allSteps() as $step) {
        ProvisioningLog::create([
            'id' => Str::uuid()->toString(),
            'tenant_id' => $this->tenant->id,
            'step' => $step,
            'status' => 'completed',
            'executed_at' => now(),
        ]);
    }

    expect($this->stateMachine->isComplete($this->tenant))->toBeTrue();
});

test('step index is correct', function () {
    expect(ProvisioningStateMachine::indexOf('db_created'))->toBe(1);
    expect(ProvisioningStateMachine::indexOf('ready'))->toBe(5);
});

test('tenant provisioning status is set on completion', function () {
    Event::fake();

    $job = new ProvisioningJob(
        tenantId: $this->tenant->id,
        adminEmail: 'admin@test.com',
        adminPassword: 'password',
        slug: $this->tenant->slug,
    );

    $job->handle(
        validate: app(ValidateProvisioningAction::class),
        stateMachine: $this->stateMachine,
        reserveDomain: app(ReserveTenantDomainAction::class),
    );

    $this->tenant->refresh();
    expect($this->tenant->provisioning_status)->toBe('completed');
    expect($this->tenant->provisioned_at)->not->toBeNull();
});
