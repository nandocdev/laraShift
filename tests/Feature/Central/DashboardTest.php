<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Http\Middleware\ValidateCentralSession;
use App\Modules\Central\Auth\Livewire\Dashboard;
use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Settings\Infrastructure\Services\CentralBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('unauthenticated user is redirected from central dashboard to central login', function () {
    $this->get(route('central.dashboard'))
        ->assertRedirect(route('central.login'));
});

test('authenticated central user can access dashboard and app logo renders correctly', function () {
    $this->withoutMiddleware(ValidateCentralSession::class);

    $user = CentralUser::factory()->create();

    CentralBranding::set('platform_name', 'openSaaS Test Suite');

    $this->actingAs($user, 'central')
        ->get(route('central.dashboard'))
        ->assertOk()
        ->assertSee('openSaaS Test Suite');
});

test('central dashboard renders all sections from the wireframe specification', function () {
    $this->withoutMiddleware(ValidateCentralSession::class);

    $user = CentralUser::factory()->create();

    $response = $this->actingAs($user, 'central')
        ->get(route('central.dashboard'));

    $response->assertOk()
        // Header + spec buttons + breakdown
        ->assertSee('Dashboard')
        ->assertSee('Visión general de toda la plataforma')
        ->assertSee('View Tenants')
        ->assertSee('View Health')
        ->assertSee('View Billing Issues')
        ->assertSee('Quarantined')
        // Top Metric Cards
        ->assertSee('ORGANIZACIONES')
        ->assertSee('USUARIOS')
        ->assertSee('ACTIVAS')
        ->assertSee('ALERTAS')
        // Platform Activity & Chart
        ->assertSee('ACTIVIDAD DE LA PLATAFORMA')
        ->assertSee('Nuevos tenants · Últimos 7 días')
        // System Health
        ->assertSee('SALUD DEL SISTEMA')
        ->assertSee('API')
        ->assertSee('Base de datos')
        ->assertSee('Queue')
        ->assertSee('Billing')
        ->assertSee('Queue size')
        ->assertSee('Past due')
        // Recent Activity & Alerts
        ->assertSee('ACTIVIDAD RECIENTE')
        ->assertSee('ALERTAS')
        ->assertSee('Ver todas')
        // Organizations Table
        ->assertSee('Empresa')
        ->assertSee('Usuarios')
        ->assertSee('Estado')
        ->assertSee('Acción')
        ->assertSee('Ver organizaciones');
});

function dashTenant(string $slug, string $status = 'active', string $plan = 'free'): Tenant
{
    return Tenant::create([
        'id' => Str::uuid()->toString(), 'slug' => $slug, 'name' => ucfirst($slug),
        'email' => $slug.'@test.com', 'status' => $status, 'plan_id' => $plan,
    ]);
}

function dashPlan(string $slug, int $monthlyCents): Plan
{
    return Plan::create([
        'slug' => $slug, 'name' => ucfirst($slug),
        'price_monthly' => $monthlyCents, 'price_yearly' => $monthlyCents * 10,
        'currency' => 'USD', 'interval' => 'month',
        'features' => [], 'is_active' => true,
    ]);
}

it('computes MRR from non-canceled subscriptions only', function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');
    $pro = dashPlan('pro', 2900);
    $t1 = dashTenant('mrr-one', 'active', 'pro');
    $t2 = dashTenant('mrr-two', 'active', 'pro');

    Subscription::create(['tenant_id' => $t1->id, 'plan_id' => $pro->id, 'status' => 'active', 'gateway' => 'clave']);
    Subscription::create(['tenant_id' => $t2->id, 'plan_id' => $pro->id, 'status' => 'canceled', 'gateway' => 'clave', 'canceled_at' => now()]);

    $revenue = Livewire::test(Dashboard::class)->instance()->revenue;

    // Only the active subscription counts: 2900, not 5800.
    expect($revenue['mrr'])->toBe(2900)
        ->and($revenue['primary_label'])->toBe('29.00 USD')
        ->and($revenue['primary_subs'])->toBe(1);
});

it('computes 30d churn from canceled subscriptions', function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');
    $pro = dashPlan('pro', 2900);
    $t1 = dashTenant('churn-one', 'active', 'pro');
    $t2 = dashTenant('churn-two', 'active', 'pro');

    Subscription::create(['tenant_id' => $t1->id, 'plan_id' => $pro->id, 'status' => 'active', 'gateway' => 'clave']);
    Subscription::create([
        'tenant_id' => $t2->id, 'plan_id' => $pro->id, 'status' => 'canceled',
        'gateway' => 'clave', 'canceled_at' => now()->subDays(5),
    ]);

    $churn = Livewire::test(Dashboard::class)->instance()->churn;

    expect($churn['canceled_30d'])->toBe(1)
        ->and($churn['rate'])->toBe(50.0);
});

it('totals approved transactions of the last 30d', function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');
    $t1 = dashTenant('txn-one');

    Payment::factory()->create([
        'tenant_id' => $t1->id, 'amount_cents' => 2900, 'currency' => 'USD',
        'status' => 'approved', 'gateway' => 'clave', 'created_at' => now()->subDays(2),
    ]);
    Payment::factory()->create([
        'tenant_id' => $t1->id, 'amount_cents' => 1000, 'currency' => 'USD',
        'status' => 'pending', 'gateway' => 'clave',
    ]);

    $transactions = Livewire::test(Dashboard::class)->instance()->transactions;

    expect($transactions['volume_30d'])->toBe(2900)
        ->and($transactions['count_30d'])->toBe(1)
        ->and($transactions['pending'])->toBe(1);
});

it('fires billing alerts and degrades billing health on real arrears', function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');
    $t1 = dashTenant('alert-one');

    Subscription::create(['tenant_id' => $t1->id, 'status' => 'past_due', 'gateway' => 'clave']);
    Payment::factory()->create([
        'tenant_id' => $t1->id, 'amount_cents' => 500, 'currency' => 'USD',
        'status' => 'pending', 'gateway' => 'clave',
    ]);

    $test = Livewire::test(Dashboard::class);
    $alerts = $test->instance()->alerts;
    $health = $test->instance()->systemHealth;

    expect(collect($alerts)->pluck('title')->join(' '))
        ->toContain('mora')
        ->toContain('pendiente')
        ->and($health['metrics']['past_due'])->toBe(1)
        ->and(collect($health['services'])->firstWhere('name', 'Billing')['status'])->toBe('degraded');
});

it('charts tenants and subscriptions per day without invented figures', function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');
    $t1 = dashTenant('chart-one');
    Subscription::create(['tenant_id' => $t1->id, 'status' => 'active', 'gateway' => 'clave']);

    $chart = Livewire::test(Dashboard::class)->instance()->activityChart;

    expect($chart['days'])->toHaveCount(7)
        ->and(array_sum(array_column($chart['days'], 'value')))->toBe(1)
        ->and(array_sum(array_column($chart['days'], 'subs')))->toBe(1)
        ->and($chart['max'])->toBe(1);
});
