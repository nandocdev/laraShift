<?php

declare(strict_types=1);

use App\Modules\Central\Analytics\Actions\ExportPlatformMetricsAction;
use App\Modules\Central\Analytics\Jobs\RefreshPlatformMetricsJob;
use App\Modules\Central\Analytics\Livewire\AnalyticsDashboard;
use App\Modules\Central\Analytics\Models\PlatformMetric;
use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Services\MrrCalculator;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = CentralUser::create([
        'name' => 'Analytics Admin',
        'email' => 'analytics@admin.com',
        'password' => 'password',
    ]);

    $this->actingAs($this->admin, 'central');
});

it('refreshes platform metrics via job', function () {
    $plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 1000,
        'price_yearly' => 10000,
        'is_active' => true,
        'features' => [],
    ]);

    Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'metric-tenant',
        'name' => 'Metric Tenant',
        'email' => 'metric@test.com',
        'plan_id' => 'pro',
        'status' => 'active',
    ]);

    $job = new RefreshPlatformMetricsJob;
    $job->handle();

    expect(PlatformMetric::where('metric', 'mrr')->exists())->toBeTrue();
    expect(PlatformMetric::where('metric', 'tenants.active')->exists())->toBeTrue();
    expect(PlatformMetric::where('metric', 'tenants.total')->exists())->toBeTrue();
});

it('stores correct values in metric snapshots', function () {
    $pro = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 2000,
        'price_yearly' => 20000,
        'is_active' => true,
        'features' => [],
    ]);

    Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'snapshot-a',
        'name' => 'Snapshot A',
        'email' => 'a@test.com',
        'plan_id' => 'pro',
        'status' => 'active',
    ]);

    Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'snapshot-b',
        'name' => 'Snapshot B',
        'email' => 'b@test.com',
        'plan_id' => 'pro',
        'status' => 'suspended',
    ]);

    $job = new RefreshPlatformMetricsJob;
    $job->handle();

    expect((int) PlatformMetric::where('metric', 'tenants.active')->first()->value)->toBe(1);
    expect((int) PlatformMetric::where('metric', 'tenants.suspended')->first()->value)->toBe(1);
    expect((int) PlatformMetric::where('metric', 'tenants.total')->first()->value)->toBe(2);

    $mrr = PlatformMetric::where('metric', 'mrr')->first()->value;
    expect($mrr)->toBeGreaterThan(0);
});

it('exports platform metrics as CSV', function () {
    Storage::fake('local');

    PlatformMetric::create([
        'id' => Str::uuid()->toString(),
        'metric' => 'mrr',
        'value' => 1500.00,
        'period' => now()->format('Y-m-d'),
        'captured_at' => now(),
    ]);

    $action = app(ExportPlatformMetricsAction::class);

    $filePath = $action->execute(
        dateFrom: now()->subDays(1)->format('Y-m-d'),
        dateTo: now()->addDays(1)->format('Y-m-d'),
        disk: 'local',
    );

    expect($filePath)->toStartWith('exports/analytics/');
    expect(Storage::disk('local')->exists($filePath))->toBeTrue();

    $content = Storage::disk('local')->get($filePath);
    expect($content)->toContain('mrr');
    expect($content)->toContain('1500');
});

it('renders the analytics dashboard', function () {
    Livewire::test(AnalyticsDashboard::class)
        ->assertStatus(200);
});

it('produces correct monthly breakdown from MrrCalculator', function () {
    $plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Starter',
        'slug' => 'starter',
        'price_monthly' => 5000,
        'price_yearly' => 50000,
        'is_active' => true,
        'features' => [],
    ]);

    Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'mrr-tenant',
        'name' => 'MRR Tenant',
        'email' => 'mrr@test.com',
        'plan_id' => 'starter',
        'status' => 'active',
    ]);

    $calculator = app(MrrCalculator::class);

    $mrr = $calculator->calculateMrr();
    expect($mrr)->toBe(50.0);

    $byPlan = $calculator->mrrByPlan();
    expect($byPlan)->toHaveCount(1);
    expect($byPlan[0]['mrr'])->toBe(50.0);
    expect($byPlan[0]['count'])->toBe(1);

    $breakdown = $calculator->monthlyBreakdown(3);
    expect($breakdown)->toHaveCount(3);
});
