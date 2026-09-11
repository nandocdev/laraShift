<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Platform\Contracts\Billing\BillingEventData;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;

it('activates a pending_payment tenant on approved payment', function () {
    Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 2900, 'price_yearly' => 29000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => ['api_access']],
        'is_active' => true,
    ]);

    $tenant = claveTestTenant('clave-lifecycle');
    $tenant->update(['status' => 'pending_payment', 'plan_id' => 'pro']);

    $payment = Payment::create([
        'tenant_id' => $tenant->id,
        'slug' => 'pay_DSP-LIFE-1',
        'display_id' => 'DSP-LIFE-1',
        'amount_cents' => 2900,
        'currency' => 'USD',
        'status' => PaymentStatus::Approved,
        'gateway' => 'clave',
        'gateway_reference' => 'PF-LIFE-1',
        'provider_metadata' => ['plan_slug' => 'pro'],
    ]);

    PaymentApproved::dispatch($payment, new BillingEventData(
        type: 'payment.succeeded',
        gateway: 'clave',
        gatewayEventId: 'PF-LIFE-1',
        displayId: 'DSP-LIFE-1',
        amountCents: 2900,
        currency: 'USD',
    ));

    assertDatabaseHas('subscriptions', [
        'tenant_id' => $tenant->id,
        'gateway' => 'clave',
        'status' => SubscriptionStatus::Active,
    ]);

    expect($tenant->fresh()->status)->toBe('active')
        ->and($tenant->fresh()->plan_id)->toBe('pro')
        ->and(Subscription::where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('expires pending_payment tenants older than 24h via reconcile', function () {
    $stale = claveTestTenant('clave-stale');
    $stale->update(['status' => 'pending_payment']);
    // stancl swallows created_at through model events: time-travel via query.
    DB::table('tenants')->where('id', $stale->id)->update(['created_at' => now()->subHours(25)]);

    $fresh = claveTestTenant('clave-fresh');
    $fresh->update(['status' => 'pending_payment']);

    artisan('provisioning:reconcile')->assertSuccessful();

    expect($stale->fresh()->status)->toBe('expired')
        ->and($fresh->fresh()->status)->toBe('pending_payment');
});
