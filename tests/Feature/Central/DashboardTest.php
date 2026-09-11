<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Http\Middleware\ValidateCentralSession;
use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Settings\Infrastructure\Services\CentralBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

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
