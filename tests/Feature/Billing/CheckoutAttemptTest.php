<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Interface\Livewire\HostedCheckout;
use App\Modules\Central\Billing\Interface\Livewire\SelectPlan;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use Carbon\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function checkoutAttemptPlans(): void
{
    Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 2900, 'price_yearly' => 29000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => ['api_access']],
        'is_active' => true,
    ]);
    Plan::create([
        'slug' => 'basic', 'name' => 'Basic', 'price_monthly' => 1900, 'price_yearly' => 19000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => ['basic_dashboard']],
        'is_active' => true,
    ]);
}

it('binds each checkout attempt to its own payment row', function () {
    checkoutAttemptPlans();
    fakeClaveLink();
    $tenant = claveTestTenant('ui-attempt-plans');
    tenancy()->initialize($tenant);

    try {
        Livewire::test(SelectPlan::class)
            ->call('checkout', 'pro')
            ->assertRedirect('https://sandbox.paguelofacil.com/pay/TEST123');

        Carbon::setTestNow(now()->addSeconds(2));

        Livewire::test(SelectPlan::class)
            ->call('checkout', 'basic')
            ->assertRedirect('https://sandbox.paguelofacil.com/pay/TEST123');

        $payments = Payment::where('tenant_id', $tenant->id)->orderBy('amount_cents')->get();

        expect($payments)->toHaveCount(2)
            ->and($payments[0]->display_id)->not->toBe($payments[1]->display_id)
            ->and($payments[0]->amount_cents)->toBe(1900)
            ->and($payments[0]->provider_metadata['plan_slug'])->toBe('basic')
            ->and($payments[1]->amount_cents)->toBe(2900)
            ->and($payments[1]->provider_metadata['plan_slug'])->toBe('pro');
    } finally {
        Carbon::setTestNow();
        tenancy()->end();
    }
});

it('collapses a same-plan double submit into a single payment', function () {
    checkoutAttemptPlans();
    fakeClaveLink();
    $tenant = claveTestTenant('ui-attempt-double');
    tenancy()->initialize($tenant);

    try {
        $component = Livewire::test(SelectPlan::class)
            ->call('checkout', 'pro')
            ->assertRedirect('https://sandbox.paguelofacil.com/pay/TEST123');

        $component->call('checkout', 'pro')
            ->assertRedirect('https://sandbox.paguelofacil.com/pay/TEST123');

        expect(Payment::where('tenant_id', $tenant->id)->count())->toBe(1);
    } finally {
        tenancy()->end();
    }
});

it('rejects tampering with locked hosted-checkout properties', function () {
    checkoutAttemptPlans();
    $tenant = dlocalTestTenant('ui-attempt-locked');
    tenancy()->initialize($tenant);

    try {
        expect(fn () => Livewire::test(HostedCheckout::class, ['plan' => 'pro'])->set('planSlug', 'basic'))
            ->toThrow(CannotUpdateLockedPropertyException::class);

        expect(fn () => Livewire::test(HostedCheckout::class, ['plan' => 'pro'])->set('displayId', 'web_forged'))
            ->toThrow(CannotUpdateLockedPropertyException::class);
    } finally {
        tenancy()->end();
    }
});
