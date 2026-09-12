<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Billing\Interface\Livewire\SubscriptionDetail;
use App\Modules\Central\Billing\Interface\Livewire\SubscriptionList;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(CentralUser::factory()->create(), 'central');
});

function mgmtPlan(string $slug): Plan
{
    return Plan::create([
        'slug' => $slug, 'name' => ucfirst($slug),
        'price_monthly' => 2900, 'price_yearly' => 29000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => [], 'is_active' => true,
    ]);
}

it('lists subscriptions and filters by status', function () {
    $t1 = claveTestTenant('sub-mgmt-1');
    $t2 = claveTestTenant('sub-mgmt-2');
    $t1->update(['name' => 'Acme One']);
    $t2->update(['name' => 'Acme Two']);

    Subscription::create(['tenant_id' => $t1->id, 'status' => 'active', 'gateway' => 'clave']);
    Subscription::create(['tenant_id' => $t2->id, 'status' => 'past_due', 'gateway' => 'clave']);

    Livewire::test(SubscriptionList::class)
        ->assertSee('Acme One')
        ->assertSee('Acme Two')
        ->set('search', 'Two')
        ->assertSee('Acme Two')
        ->assertDontSee('Acme One')
        ->set('search', '')
        ->set('statusFilter', 'past_due')
        ->assertSee('Acme Two')
        ->assertDontSee('Acme One');
});

it('cancels at period end and reactivates from the list', function () {
    $tenant = claveTestTenant('sub-mgmt-cancel');
    $sub = Subscription::create(['tenant_id' => $tenant->id, 'status' => 'active', 'gateway' => 'clave']);

    Livewire::test(SubscriptionList::class)
        ->call('cancel', $sub->id)
        ->assertHasNoErrors();

    expect($sub->fresh()->cancel_at_period_end)->toBeTrue();

    Livewire::test(SubscriptionList::class)
        ->call('reactivate', $sub->id)
        ->assertHasNoErrors();

    expect($sub->fresh()->cancel_at_period_end)->toBeFalse();
});

it('creates a subscription for a tenant slug and plan', function () {
    mgmtPlan('pro');
    claveTestTenant('sub-mgmt-new');

    Livewire::test(SubscriptionList::class)
        ->set('newTenantSlug', 'sub-mgmt-new')
        ->set('newPlanSlug', 'pro')
        ->call('create')
        ->assertHasNoErrors();

    expect(Subscription::where('tenant_id', Tenant::where('slug', 'sub-mgmt-new')->firstOrFail()->id)->exists())->toBeTrue();
});

it('shows the detail and changes plan', function () {
    mgmtPlan('pro');
    mgmtPlan('enterprise');

    $tenant = claveTestTenant('sub-mgmt-detail');
    $plan = Plan::where('slug', 'pro')->firstOrFail();
    $sub = Subscription::create([
        'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
        'status' => 'active', 'gateway' => 'clave',
    ]);

    Livewire::test(SubscriptionDetail::class, ['subscription' => $sub])
        ->assertSee('Detail')
        ->assertSee('Billing History')
        ->set('planSlug', 'enterprise')
        ->call('changePlan')
        ->assertHasNoErrors();

    expect($sub->fresh()->plan_id)->toBe(Plan::where('slug', 'enterprise')->firstOrFail()->id)
        ->and($tenant->fresh()->plan_id)->toBe('enterprise');
});

it('rejects inactive plans from the subscription detail', function () {
    mgmtPlan('pro');
    Plan::create([
        'slug' => 'legacy', 'name' => 'Legacy',
        'price_monthly' => 900, 'price_yearly' => 9000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => [], 'is_active' => false,
    ]);

    $tenant = claveTestTenant('sub-mgmt-inactive');
    $plan = Plan::where('slug', 'pro')->firstOrFail();
    $sub = Subscription::create([
        'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
        'status' => 'active', 'gateway' => 'clave',
    ]);

    Livewire::test(SubscriptionDetail::class, ['subscription' => $sub])
        ->set('planSlug', 'legacy')
        ->call('changePlan')
        ->assertHasErrors(['planSlug']);

    expect($sub->fresh()->plan_id)->toBe($plan->id);
});
