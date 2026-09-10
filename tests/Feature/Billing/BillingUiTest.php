<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Billing\Interface\Livewire\HostedCheckout;
use App\Modules\Central\Billing\Interface\Livewire\ManageBilling;
use App\Modules\Central\Billing\Interface\Livewire\SelectPlan;
use App\Modules\Central\Billing\Interface\Livewire\UpdatePaymentMethod;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

function billingUiPlans(): void
{
    Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 2900, 'price_yearly' => 29000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => ['api_access']],
        'is_active' => true,
    ]);
}

it('selects a Clave plan and redirects to the hosted checkout', function () {
    billingUiPlans();
    fakeClaveLink();
    $tenant = claveTestTenant('ui-select-clave');
    tenancy()->initialize($tenant);

    try {
        Livewire::test(SelectPlan::class)
            ->assertSee('Pro')
            ->assertSee('renewal link')
            ->call('checkout', 'pro')
            ->assertRedirect('https://sandbox.paguelofacil.com/pay/TEST123');
    } finally {
        tenancy()->end();
    }
});

it('shows a friendly error when the Clave merchant is not configured', function () {
    billingUiPlans();
    config()->set('clave.merchant_id', '');
    $tenant = claveTestTenant('ui-select-noconfig');
    tenancy()->initialize($tenant);

    try {
        Livewire::test(SelectPlan::class)
            ->call('checkout', 'pro')
            ->assertSee('Could not start the checkout');
    } finally {
        tenancy()->end();
    }
});

it('shows the hosted card flow for dLocal instead of redirect', function () {
    billingUiPlans();
    $tenant = dlocalTestTenant('ui-select-dlocal');
    tenancy()->initialize($tenant);

    try {
        Livewire::test(SelectPlan::class)
            ->assertSee('Pro')
            ->assertSee('renew automatically');
    } finally {
        tenancy()->end();
    }
});

it('manages billing and cancels at period end through actions', function () {
    billingUiPlans();
    $tenant = claveTestTenant('ui-manage');
    tenancy()->initialize($tenant);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'status' => 'active',
        'gateway' => 'clave',
    ]);

    try {
        Livewire::test(ManageBilling::class)
            ->assertSee('active')
            ->set('confirmingCancel', true)
            ->call('cancel')
            ->assertSee('canceled at period end');

        expect(
            Subscription::where('tenant_id', $tenant->id)
                ->first()->cancel_at_period_end
        )->toBeTrue();
    } finally {
        tenancy()->end();
    }
});

it('subscribes with a card token through hosted checkout', function () {
    billingUiPlans();
    Http::fake([
        '*/payments' => Http::response(['id' => 'D-UI-001', 'status' => 'APPROVED', 'card_id' => 'CARD-UI-1']),
    ]);
    $tenant = dlocalTestTenant('ui-hosted');
    tenancy()->initialize($tenant);

    try {
        Livewire::test(HostedCheckout::class, ['plan' => 'pro'])
            ->set('payerDocument', '12345678')
            ->call('charge', 'tok_ui_test_token')
            ->assertRedirect(route('tenant.billing.success'));

        assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'gateway' => 'dlocal',
            'status' => 'active',
            'pm_card_id' => 'CARD-UI-1',
        ]);
    } finally {
        tenancy()->end();
    }
});

it('requires an ID document before charging through hosted checkout', function () {
    billingUiPlans();
    $tenant = dlocalTestTenant('ui-hosted-nodoc');
    tenancy()->initialize($tenant);

    try {
        Livewire::test(HostedCheckout::class, ['plan' => 'pro'])
            ->call('charge', 'tok_ui_test_token')
            ->assertHasErrors(['payerDocument'])
            ->assertNoRedirect();
    } finally {
        tenancy()->end();
    }
});

it('redirects hosted checkout away for non-card gateways', function () {
    billingUiPlans();
    $tenant = claveTestTenant('ui-hosted-clave');
    tenancy()->initialize($tenant);

    try {
        Livewire::test(HostedCheckout::class, ['plan' => 'pro'])
            ->assertRedirect(route('tenant.billing.plans'));
    } finally {
        tenancy()->end();
    }
});

it('regularizes a past_due subscription with a new card', function () {
    billingUiPlans();
    Http::fake([
        '*/payments' => Http::response(['id' => 'D-UI-002', 'status' => 'APPROVED', 'card_id' => 'CARD-UI-2']),
    ]);
    $tenant = dlocalTestTenant('ui-update');
    tenancy()->initialize($tenant);

    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => Plan::where('slug', 'pro')->value('id'),
        'status' => 'past_due',
        'gateway' => 'dlocal',
        'failed_attempts' => 2,
    ]);

    try {
        Livewire::test(UpdatePaymentMethod::class)
            ->call('payWithNewCard', 'tok_ui_new_card')
            ->assertRedirect(route('tenant.billing.success'));

        expect($subscription->fresh()->status->value)->toBe('active')
            ->and($subscription->fresh()->pm_card_id)->toBe('CARD-UI-2');
    } finally {
        tenancy()->end();
    }
});
