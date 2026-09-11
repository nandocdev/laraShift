<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Support\Livewire\CentralAuditLog;
use App\Modules\Platform\Observability\Audit\Activity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');
});

it('redirects guests from the audit log to central login', function () {
    auth('central')->logout();

    $this->get(route('central.audit.log'))
        ->assertRedirect(route('central.login'));
});

it('renders actor, action, resource and date columns', function () {
    $this->get(route('central.audit.log'))
        ->assertOk()
        ->assertSee('Audit Log')
        ->assertSee('Actor')
        ->assertSee('Action')
        ->assertSee('Resource')
        ->assertSee('Export');
});

it('lists recorded events and filters by log', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'audited',
        'name' => 'Audited',
        'email' => 'audited@test.com',
        'status' => 'active',
    ]);

    activity('provisioning')->performedOn($tenant)->log('tenant_updated');
    activity('settings')->log('branding_updated');

    Livewire::test(CentralAuditLog::class)
        ->assertSee('Tenant Updated')
        ->assertSee('Branding Updated')
        ->set('logFilter', 'provisioning')
        ->assertSee('Tenant Updated')
        ->assertDontSee('Branding Updated');
});

it('opens an event detail view with properties', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'detail',
        'name' => 'Detail',
        'email' => 'detail@test.com',
        'status' => 'active',
    ]);

    activity('provisioning')
        ->performedOn($tenant)
        ->withProperties(['from' => 'free', 'to' => 'pro'])
        ->log('tenant_plan_changed');

    $id = Activity::firstOrFail()->id;

    Livewire::test(CentralAuditLog::class)
        ->call('view', $id)
        ->assertSet('selectedId', $id)
        ->assertSee('Tenant Plan Changed');
});

it('exports filtered events as csv without a delete action', function () {
    activity('billing')->log('invoice_paid');

    Livewire::test(CentralAuditLog::class)
        ->call('export')
        ->assertFileDownloaded();

    expect(method_exists(CentralAuditLog::class, 'delete'))->toBeFalse();
});
