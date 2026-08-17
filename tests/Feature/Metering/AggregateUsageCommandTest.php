<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Metering\Application\Jobs\AggregateUsageJob;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->makeTenant = fn (string $slug): Tenant => Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => $slug,
        'name' => Str::headline($slug),
        'email' => $slug.'@test.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);
});

it('dispatches an aggregation job for a specific tenant and period', function () {
    Bus::fake();

    $tenant = ($this->makeTenant)('cmd-specific');

    $this->artisan('metering:aggregate', ['--tenant' => $tenant->id, '--period' => '2026-08'])
        ->assertExitCode(0);

    Bus::assertDispatched(AggregateUsageJob::class, function (AggregateUsageJob $job) use ($tenant) {
        return $job->tenantId === $tenant->id && $job->period === '2026-08';
    });
});

it('dispatches aggregation jobs for every active tenant', function () {
    Bus::fake();

    $a = ($this->makeTenant)('cmd-a');
    $b = ($this->makeTenant)('cmd-b');

    $this->artisan('metering:aggregate')->assertExitCode(0);

    Bus::assertDispatched(AggregateUsageJob::class, function (AggregateUsageJob $job) use ($a, $b) {
        return in_array($job->tenantId, [$a->id, $b->id]);
    });
});

it('rejects an invalid period format', function () {
    $this->artisan('metering:aggregate', ['--period' => 'not-a-period'])
        ->assertExitCode(1);
});
