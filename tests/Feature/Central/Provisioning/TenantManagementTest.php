<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Livewire\ManageTenant;
use App\Modules\Central\Provisioning\Livewire\TenantList;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeTenantRow(string $slug, string $status = 'active', string $plan = 'free'): Tenant
{
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => $slug,
        'name' => ucfirst($slug),
        'email' => $slug.'@test.com',
        'status' => $status,
        'plan_id' => $plan,
    ]);
    $tenant->domains()->create(['domain' => $slug.'.localhost']);

    return $tenant;
}

beforeEach(function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');
});

it('filters tenants by search, status, plan and health', function () {
    makeTenantRow('acme-one', 'active', 'free');
    makeTenantRow('acme-two', 'suspended', 'pro');
    makeTenantRow('other-corp', 'quarantine', 'free');

    Livewire::test(TenantList::class)
        ->set('search', 'acme')
        ->assertSee('acme-one')
        ->assertSee('acme-two')
        ->assertDontSee('other-corp');

    Livewire::test(TenantList::class)
        ->set('statusFilter', 'suspended')
        ->assertSee('acme-two')
        ->assertDontSee('acme-one');

    Livewire::test(TenantList::class)
        ->set('planFilter', 'pro')
        ->assertSee('acme-two')
        ->assertDontSee('acme-one');

    Livewire::test(TenantList::class)
        ->set('healthFilter', 'critical')
        ->assertSee('other-corp')
        ->assertDontSee('acme-one');
});

it('suspends, quarantines and reactivates a tenant from the detail', function () {
    $tenant = makeTenantRow('lifecycle', 'active');

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->call('suspend')
        ->assertHasNoErrors();

    expect($tenant->fresh()->status)->toBe('suspended');

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->call('quarantine')
        ->assertHasNoErrors();

    $tenant->refresh();
    expect($tenant->status)->toBe('quarantine')
        ->and($tenant->read_only)->toBeTrue();

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->call('reactivate')
        ->assertHasNoErrors();

    expect($tenant->fresh()->status)->toBe('active');
});

it('changes plan only to an existing plan slug', function () {
    $tenant = makeTenantRow('planful', 'active', 'free');

    Plan::create([
        'slug' => 'pro',
        'name' => 'Pro',
        'price_monthly' => 1900,
        'price_yearly' => 19000,
        'currency' => 'USD',
        'interval' => 'month',
        'features' => [],
        'is_active' => true,
    ]);

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->set('plan_id', 'pro')
        ->call('changePlan')
        ->assertHasNoErrors();

    expect($tenant->fresh()->plan_id)->toBe('pro');

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->set('plan_id', 'ghost-plan')
        ->call('changePlan')
        ->assertHasErrors(['plan_id']);
});

it('rejects purge when slug confirmation does not match', function () {
    $tenant = makeTenantRow('purge-me', 'suspended');

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->set('purgeConfirmSlug', 'wrong-slug')
        ->call('purge')
        ->assertHasErrors(['purgeConfirmSlug']);

    expect(Tenant::where('slug', 'purge-me')->exists())->toBeTrue();
});
