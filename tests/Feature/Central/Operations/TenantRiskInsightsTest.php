<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Livewire\Dashboard;
use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Operations\Application\Queries\TenantRiskInsights;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Tenancy\Application\Services\QuotaManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function riskTenant(string $slug, string $status = 'active', string $plan = 'free'): Tenant
{
    return Tenant::create([
        'id' => Str::uuid()->toString(), 'slug' => $slug, 'name' => Str::headline($slug),
        'email' => $slug.'@test.com', 'status' => $status, 'plan_id' => $plan,
    ]);
}

function riskPlan(string $slug, int $monthlyCents, array $quotas = [], bool $custom = false): Plan
{
    return Plan::create([
        'slug' => $slug, 'name' => Str::headline($slug),
        'price_monthly' => $monthlyCents, 'price_yearly' => $monthlyCents * 10,
        'currency' => 'USD', 'interval' => 'month',
        'features' => $quotas === [] ? [] : ['quotas' => $quotas],
        'is_active' => true, 'is_custom' => $custom,
    ]);
}

it('scores churn with explainable reasons and bands', function () {
    $risky = riskTenant('risky-co');
    Subscription::create([
        'tenant_id' => $risky->id, 'status' => SubscriptionStatus::PastDue,
        'gateway' => 'clave', 'failed_attempts' => 3,
    ]);
    $healthy = riskTenant('healthy-co');
    $sub = Subscription::create([
        'tenant_id' => $healthy->id, 'status' => SubscriptionStatus::Active, 'gateway' => 'clave',
    ]);
    Payment::factory()->create(['tenant_id' => $healthy->id, 'status' => 'approved']);

    $risks = app(TenantRiskInsights::class)->churnRisks();

    expect(collect($risks)->pluck('slug')->all())->toBe(['risky-co'])
        ->and($risks[0]['score'])->toBe(80)
        ->and($risks[0]['band'])->toBe('high')
        ->and($risks[0]['reasons'])->toHaveCount(3);
});

it('flags watch band below the high threshold', function () {
    $tenant = riskTenant('watch-co');
    Subscription::create([
        'tenant_id' => $tenant->id, 'status' => SubscriptionStatus::PastDue, 'gateway' => 'clave',
    ]);

    $risks = app(TenantRiskInsights::class)->churnRisks();

    // past_due sub (40) + no recent payment (20) = 60 → high; add payment to isolate watch band.
    expect($risks[0]['band'])->toBe('high');

    Payment::factory()->create(['tenant_id' => $tenant->id, 'status' => 'approved']);

    $risks = app(TenantRiskInsights::class)->churnRisks();

    expect($risks[0]['score'])->toBe(40)->and($risks[0]['band'])->toBe('watch');
});

it('suggests upgrades on quota saturation', function () {
    riskPlan('starter', 1000, ['api_keys' => 5]);
    riskPlan('pro', 2000);
    $tenant = riskTenant('growing-co', 'active', 'starter');

    app(QuotaManager::class)->forceIncrement($tenant, 'api_keys', 4);

    $candidates = app(TenantRiskInsights::class)->upgradeCandidates();

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['slug'])->toBe('growing-co')
        ->and($candidates[0]['metric'])->toBe('api_keys')
        ->and($candidates[0]['percentage'])->toBe(80)
        ->and($candidates[0]['suggested_plan'])->toBe('pro');
});

it('skips tenants without a higher plan and custom plans', function () {
    riskPlan('top', 5000, ['api_keys' => 5]);
    riskPlan('bespoke', 99900, ['api_keys' => 5], custom: true);

    $top = riskTenant('top-co', 'active', 'top');
    app(QuotaManager::class)->forceIncrement($top, 'api_keys', 5);

    $custom = riskTenant('custom-co', 'active', 'bespoke');
    app(QuotaManager::class)->forceIncrement($custom, 'api_keys', 5);

    expect(app(TenantRiskInsights::class)->upgradeCandidates())->toBe([]);
});

it('aggregates platform quota saturation per metric', function () {
    riskPlan('starter', 1000, ['api_keys' => 5]);
    riskPlan('pro', 2000);
    $tenant = riskTenant('sat-co', 'active', 'starter');
    app(QuotaManager::class)->forceIncrement($tenant, 'api_keys', 5);
    riskTenant('roomy-co', 'active', 'starter');

    $rows = app(TenantRiskInsights::class)->quotaSaturation();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['metric'])->toBe('api_keys')
        ->and($rows[0]['tenants_near_limit'])->toBe(1)
        ->and($rows[0]['tenants_total'])->toBe(2);
});

it('renders risk and upgrade sections on the dashboard', function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');

    riskPlan('starter', 1000, ['api_keys' => 5]);
    riskPlan('pro', 2000);
    $tenant = riskTenant('risky-dash');
    Subscription::create([
        'tenant_id' => $tenant->id, 'status' => SubscriptionStatus::PastDue, 'gateway' => 'clave',
    ]);
    $growing = riskTenant('growing-dash', 'active', 'starter');
    app(QuotaManager::class)->forceIncrement($growing, 'api_keys', 4);

    Livewire::test(Dashboard::class)
        ->assertSee('EN RIESGO DE CHURN')
        ->assertSee('Risky Dash')
        ->assertSee('CANDIDATOS A UPGRADE')
        ->assertSee('Growing Dash');
});
