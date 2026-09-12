<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Operations\Application\Services\TenantQueueManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\Billing\BillingEventData;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;

function mitSubscription(string $slug, array $overrides = []): Subscription
{
    $tenant = dlocalTestTenant($slug);

    return Subscription::create(array_merge([
        'tenant_id' => $tenant->id,
        'status' => SubscriptionStatus::Active,
        'gateway' => 'dlocal',
        'pm_card_id' => 'CARD-TEST-1',
        'current_period_start' => now()->subMonth(),
        'current_period_end' => now()->subDay(),
        'next_payment_at' => now()->subDay(),
        'failed_attempts' => 0,
    ], $overrides));
}

function fakeDlocalMit(string $status): void
{
    Http::fake([
        '*/payments' => Http::response(['id' => 'D-MIT-'.str()->random(4), 'status' => $status]),
    ]);
}

it('suspends after the 3rd failed MIT attempt (and not before)', function () {
    fakeDlocalMit('DECLINED');
    $subscription = mitSubscription('sched-mit-3', ['failed_attempts' => 2]);

    artisan('billing:process-recurring')->assertSuccessful();

    $subscription = $subscription->fresh();
    $tenant = $subscription->tenant_id;

    expect($subscription->failed_attempts)->toBe(3)
        ->and($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and(Tenant::find($tenant)->status)->toBe('suspended');

    assertDatabaseHas('activity_log', ['log_name' => 'billing', 'description' => 'tenant_suspended_for_non_payment']);
});

it('keeps retrying before the 3rd MIT attempt without suspending', function () {
    fakeDlocalMit('DECLINED');
    $subscription = mitSubscription('sched-mit-1', ['failed_attempts' => 0]);

    artisan('billing:process-recurring')->assertSuccessful();

    expect($subscription->fresh()->failed_attempts)->toBe(1)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and(Tenant::find($subscription->tenant_id)->status)->toBe('active');
});

it('resets MIT failures on approved charge', function () {
    fakeDlocalMit('APPROVED');
    $subscription = mitSubscription('sched-mit-ok', [
        'failed_attempts' => 2,
        'status' => SubscriptionStatus::PastDue,
    ]);

    artisan('billing:process-recurring')->assertSuccessful();

    expect($subscription->fresh()->failed_attempts)->toBe(0)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('issues an invoice for an approved MIT charge', function () {
    fakeDlocalMit('APPROVED');
    $subscription = mitSubscription('sched-mit-inv');

    artisan('billing:process-recurring')->assertSuccessful();

    $payment = Payment::where('tenant_id', $subscription->tenant_id)
        ->where('status', PaymentStatus::Approved)
        ->firstOrFail();

    assertDatabaseHas('invoices', [
        'tenant_id' => $subscription->tenant_id,
        'payment_id' => $payment->id,
        'subscription_id' => $subscription->id,
        'status' => 'paid',
        'amount_cents' => $payment->amount_cents,
    ]);
    expect(Invoice::where('payment_id', $payment->id)->count())->toBe(1);
});

it('moves Clave link renewals to past_due after expiry, suspends 4 days later', function () {
    // Deliberately different numbers than the MIT track (3 attempts):
    // link grace = 3 days, suspend = 3 + 4 days.
    $grace = claveTestTenant('sched-link-grace');
    $late = claveTestTenant('sched-link-late');

    $past = now()->subMonth();
    foreach ([[$grace, now()->subDay()], [$late, now()->subDays(5)]] as [$tenant, $expiredAt]) {
        Subscription::create([
            'tenant_id' => $tenant->id,
            'status' => SubscriptionStatus::Active,
            'gateway' => 'clave',
            'current_period_start' => $past->copy()->subMonth(),
            'current_period_end' => $past,
            'renewal_link_sent_at' => $past->copy()->addDays(20),
            'renewal_link_expires_at' => $expiredAt,
        ]);
    }

    artisan('billing:reconcile')->assertSuccessful();

    expect(Subscription::where('tenant_id', $grace->id)->first()->status)->toBe(SubscriptionStatus::PastDue)
        ->and(Tenant::find($grace->id)->status)->toBe('active')
        ->and(Tenant::find($late->id)->status)->toBe('suspended');
});

it('reactivates on regularized payment regardless of gateway', function () {
    Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 2900, 'price_yearly' => 29000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => []],
        'is_active' => true,
    ]);

    $tenant = dlocalTestTenant('sched-reactivate');
    $tenant->update(['status' => 'suspended']);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'status' => SubscriptionStatus::PastDue,
        'gateway' => 'dlocal',
        'failed_attempts' => 3,
    ]);

    $payment = Payment::create([
        'tenant_id' => $tenant->id,
        'slug' => 'pay_DSP-REACT-1',
        'display_id' => 'DSP-REACT-1',
        'amount_cents' => 2900,
        'currency' => 'USD',
        'status' => PaymentStatus::Approved,
        'gateway' => 'dlocal',
        'gateway_reference' => 'D-REACT-1',
        'provider_metadata' => ['plan_slug' => 'pro'],
    ]);

    PaymentApproved::dispatch($payment, new BillingEventData(
        type: 'payment.succeeded', gateway: 'dlocal', gatewayEventId: 'D-REACT-1',
        displayId: 'DSP-REACT-1', amountCents: 2900, currency: 'USD',
    ));

    expect($tenant->fresh()->status)->toBe('active');
});

it('degrades past_due tenants to the low queue', function () {
    $tenant = dlocalTestTenant('sched-queue');
    $tenant->update(['status' => 'past_due']);

    expect(TenantQueueManager::resolve($tenant, 'high'))->toMatch('/^tenant\.b[1-5]\.low$/');
});
