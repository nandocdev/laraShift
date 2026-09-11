<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Billing\Interface\Livewire\ManageBilling;
use App\Modules\Central\Billing\Interface\Livewire\UpdatePaymentMethod;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 2900, 'price_yearly' => 29000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => ['api_access']],
        'is_active' => true,
    ]);

    $tenant = dlocalTestTenant('ui-dunning-'.Str::random(6));
    tenancy()->initialize($tenant);
    $this->tenant = $tenant;

    app(EnsureTenantRolesExist::class)->execute($tenant);
    setPermissionsTeamId($tenant->id);

    $this->admin = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
    $this->admin->assignRole('admin');

    $this->viewer = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);
    $this->viewer->assignRole('member');
});

afterEach(function () {
    tenancy()->end();
});

it('forbids subscription cancel for members without settings:manage', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => Plan::where('slug', 'pro')->value('id'),
        'status' => 'past_due',
        'gateway' => 'dlocal',
    ]);

    $this->actingAs($this->viewer);

    Livewire::test(ManageBilling::class)
        ->call('cancel')
        ->assertForbidden();
});

it('refuses to charge when the outstanding amount cannot be resolved', function () {
    Http::fake([
        '*/payments' => Http::response(['id' => 'D-DUN-000', 'status' => 'APPROVED']),
    ]);

    $doomed = Plan::create([
        'slug' => 'doomed', 'name' => 'Doomed', 'price_monthly' => 500, 'price_yearly' => 5000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => [],
        'is_active' => true,
    ]);

    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $doomed->id,
        'status' => 'past_due',
        'gateway' => 'dlocal',
    ]);

    $doomed->forceDelete();

    $this->actingAs($this->admin);

    Livewire::test(UpdatePaymentMethod::class)
        ->set('payerDocument', '12345678')
        ->call('payWithNewCard', 'tok_dunning_zero')
        ->assertSee('Could not determine the outstanding amount')
        ->assertNoRedirect();

    expect(Payment::where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('charges the regularization in the plan currency', function () {
    Http::fake([
        '*/payments' => Http::response(['id' => 'D-DUN-EUR', 'status' => 'APPROVED', 'card_id' => 'CARD-EUR-1']),
    ]);

    $plan = Plan::create([
        'slug' => 'euro', 'name' => 'Euro', 'price_monthly' => 1000, 'price_yearly' => 10000,
        'currency' => 'EUR', 'interval' => 'month',
        'features' => [],
        'is_active' => true,
    ]);

    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'past_due',
        'gateway' => 'dlocal',
        'failed_attempts' => 2,
    ]);

    $this->actingAs($this->admin);

    Livewire::test(UpdatePaymentMethod::class)
        ->set('payerDocument', '87654321')
        ->call('payWithNewCard', 'tok_dunning_eur')
        ->assertRedirect(route('tenant.billing.success'));

    expect(Payment::where('tenant_id', $this->tenant->id)->first())
        ->currency->toBe('EUR')
        ->amount_cents->toBe(1000);
});

it('requires an ID document before retrying the payment', function () {
    Subscription::create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => Plan::where('slug', 'pro')->value('id'),
        'status' => 'past_due',
        'gateway' => 'dlocal',
    ]);

    $this->actingAs($this->admin);

    Livewire::test(UpdatePaymentMethod::class)
        ->call('payWithNewCard', 'tok_dunning_nodoc')
        ->assertHasErrors(['payerDocument'])
        ->assertNoRedirect();

    expect(Payment::where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('rejects tampering with the locked regularization display id', function () {
    $this->actingAs($this->admin);

    expect(fn () => Livewire::test(UpdatePaymentMethod::class)->set('displayId', 'upd_forged'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});
