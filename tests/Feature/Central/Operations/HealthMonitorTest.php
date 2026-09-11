<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Operations\Interface\Livewire\HealthMonitor;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');
});

it('redirects guests from the health monitor to central login', function () {
    auth('central')->logout();

    $this->get(route('central.health.monitor'))
        ->assertRedirect(route('central.login'));
});

it('renders platform, tenants and incidents sections', function () {
    $this->get(route('central.health.monitor'))
        ->assertOk()
        ->assertSee('Health Monitor')
        ->assertSee('Platform')
        ->assertSee('Tenants')
        ->assertSee('Incidents');
});

it('exposes quarantine as a critical incident and acknowledges it', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'risky',
        'name' => 'Risky',
        'email' => 'risky@test.com',
        'status' => 'quarantine',
    ]);
    $tenant->domains()->create(['domain' => 'risky.localhost']);

    Livewire::test(HealthMonitor::class)
        ->assertSee('Tenants en cuarentena')
        ->call('acknowledge', 'tenants-quarantine')
        ->assertDontSee('Tenants en cuarentena');
});

it('keeps the json health endpoint untouched for probes and widgets', function () {
    config(['database.redis.client' => 'predis']);

    Redis::shouldReceive('connection->ping')->andReturn(true);
    Queue::shouldReceive('size')->andReturn(0);

    $this->get(route('central.health'))
        ->assertOk()
        ->assertJsonPath('status', 'healthy');
});
