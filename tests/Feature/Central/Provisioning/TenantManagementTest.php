<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Livewire\CreateTenant;
use App\Modules\Central\Provisioning\Livewire\ManageTenant;
use App\Modules\Central\Provisioning\Livewire\TenantList;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Domain\Models\User;
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
    $this->actingAs(CentralUser::factory()->create(['is_global_admin' => true]), 'central');
});

function staffUser(): CentralUser
{
    return CentralUser::factory()->create(['is_global_admin' => false]);
}

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

it('syncs non-canceled subscriptions and rejects inactive plans on changePlan', function () {
    $tenant = makeTenantRow('plan-sync', 'active', 'free');

    Plan::create([
        'slug' => 'pro', 'name' => 'Pro',
        'price_monthly' => 1900, 'price_yearly' => 19000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => [], 'is_active' => true,
    ]);
    Plan::create([
        'slug' => 'legacy', 'name' => 'Legacy',
        'price_monthly' => 900, 'price_yearly' => 9000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => [], 'is_active' => false,
    ]);

    $proId = Plan::where('slug', 'pro')->firstOrFail()->id;

    $active = Subscription::create([
        'tenant_id' => $tenant->id, 'plan_id' => null,
        'status' => 'active', 'gateway' => 'clave',
    ]);
    $canceled = Subscription::create([
        'tenant_id' => $tenant->id, 'plan_id' => null,
        'status' => 'canceled', 'gateway' => 'clave',
    ]);

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->set('plan_id', 'pro')
        ->call('changePlan')
        ->assertHasNoErrors();

    expect($tenant->fresh()->plan_id)->toBe('pro')
        ->and($active->fresh()->plan_id)->toBe($proId)
        ->and($canceled->fresh()->plan_id)->toBeNull();

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->set('plan_id', 'legacy')
        ->call('changePlan')
        ->assertHasErrors(['plan_id']);

    expect($tenant->fresh()->plan_id)->toBe('pro');
});

it('rejects purge when slug confirmation does not match', function () {
    $tenant = makeTenantRow('purge-me', 'suspended');

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->set('purgeConfirmSlug', 'wrong-slug')
        ->call('purge')
        ->assertHasErrors(['purgeConfirmSlug']);

    expect(Tenant::where('slug', 'purge-me')->exists())->toBeTrue();
});

it('lets non-admin staff view the list and detail but forbids lifecycle mutations', function () {
    $tenant = makeTenantRow('viewable', 'active');
    $this->actingAs(staffUser(), 'central');

    Livewire::test(TenantList::class)
        ->assertHasNoErrors()
        ->assertSee('viewable');

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->assertHasNoErrors()
        ->assertSee('Viewable');

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])->call('suspend')->assertForbidden();
    Livewire::test(ManageTenant::class, ['tenant' => $tenant])->call('quarantine')->assertForbidden();
    Livewire::test(ManageTenant::class, ['tenant' => $tenant])->call('reactivate')->assertForbidden();
    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->set('purgeConfirmSlug', 'viewable')
        ->call('purge')
        ->assertForbidden();
    Livewire::test(ManageTenant::class, ['tenant' => $tenant])->call('save')->assertForbidden();

    Livewire::test(TenantList::class)
        ->set('selectedTenantId', $tenant->id)
        ->set('confirmSlug', 'viewable')
        ->call('delete')
        ->assertForbidden();

    Livewire::test(CreateTenant::class)->call('save')->assertForbidden();

    expect($tenant->fresh()->status)->toBe('active');
    expect(Tenant::where('slug', 'viewable')->exists())->toBeTrue();
});

it('never exposes tenant end-user data on the detail view', function () {
    $tenant = makeTenantRow('privacy-co', 'active');

    $tenant->run(fn () => User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'end-client-private@example.com',
    ]));

    Livewire::test(ManageTenant::class, ['tenant' => $tenant])
        ->assertHasNoErrors()
        ->assertSee('Privacy-co')
        ->assertDontSee('end-client-private@example.com');
});
