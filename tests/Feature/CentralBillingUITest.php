<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Billing\Livewire\ReportsView;
use App\Modules\Central\Billing\Livewire\SubscriptionDetail;
use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Billing Admin',
        'email' => 'billing-admin@test.com',
        'password' => Hash::make('password'),
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'billing-ui-test',
        'name' => 'Billing UI Test Corp',
        'email' => 'billing-ui@test.com',
        'plan_id' => 'pro',
        'status' => 'active',
    ]);

    $this->plan = Plan::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Pro Plan',
        'slug' => 'pro',
        'price_monthly' => 2999,
        'price_yearly' => 29990,
        'currency' => 'USD',
        'interval' => 'month',
        'is_active' => true,
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

// ─── SubscriptionDetail ───────────────────────────────────────────

it('renders subscription detail page', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'provider_subscription_id' => 'sub_'.Str::random(10),
        'status' => 'active',
        'gateway' => 'stripe',
        'current_period_end' => now()->addMonth(),
        'created_at' => now(),
    ]);

    $this->actingAs($this->admin, 'central');

    Livewire::test(SubscriptionDetail::class, ['tenant' => $this->tenant])
        ->assertStatus(200)
        ->assertSee($this->tenant->name)
        ->assertSee(__('Active'));
});

it('shows empty state when no subscription exists', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(SubscriptionDetail::class, ['tenant' => $this->tenant])
        ->assertStatus(200)
        ->assertSee(__('No active subscription for this tenant.'));
});

it('displays invoice history', function () {
    $subscription = Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'provider_subscription_id' => 'sub_'.Str::random(10),
        'status' => 'active',
        'gateway' => 'stripe',
        'current_period_end' => now()->addMonth(),
    ]);

    Invoice::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'subscription_id' => $subscription->id,
        'provider_invoice_id' => 'inv_'.Str::random(10),
        'amount' => 2999,
        'currency' => 'USD',
        'status' => 'paid',
        'issued_at' => now(),
    ]);

    $this->actingAs($this->admin, 'central');

    Livewire::test(SubscriptionDetail::class, ['tenant' => $this->tenant])
        ->assertStatus(200)
        ->assertSee('PAID');
});

it('shows empty invoice state', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'provider_subscription_id' => 'sub_'.Str::random(10),
        'status' => 'active',
        'gateway' => 'stripe',
    ]);

    $this->actingAs($this->admin, 'central');

    Livewire::test(SubscriptionDetail::class, ['tenant' => $this->tenant])
        ->assertStatus(200)
        ->assertSee(__('No invoices found for this tenant.'));
});

it('redirects unauthenticated users', function () {
    $this->get(route('central.billing.subscriptions.detail', $this->tenant))
        ->assertRedirect(route('central.login'));
});

// ─── ReportsView ──────────────────────────────────────────────────

it('renders reports page', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(ReportsView::class)
        ->assertStatus(200)
        ->assertSee(__('Financial Reports'))
        ->assertSee(__('MRR'))
        ->assertSee(__('ARR'));
});

it('shows monthly breakdown chart', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(ReportsView::class)
        ->assertStatus(200)
        ->assertSee(__('Monthly MRR Trend'));
});

it('shows mrr by plan breakdown', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(ReportsView::class)
        ->assertStatus(200)
        ->assertSee(__('MRR by Plan'));
});

it('changes period filter', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(ReportsView::class)
        ->set('period', 'last_3_months')
        ->assertStatus(200);
});

it('renders reports for unauthenticated redirect', function () {
    $this->get(route('central.billing.reports'))
        ->assertRedirect(route('central.login'));
});
