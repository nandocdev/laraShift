<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\Actions\ChargeSubscriptionAction;
use App\Modules\Central\Billing\Application\DTO\PaymentResultData;
use App\Modules\Central\Billing\Application\Jobs\ChargeSubscriptionJob;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentAttempt;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Events\TenantSuspendedByDunning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = Plan::create([
        'id' => (string) Str::uuid(),
        'name' => 'Pro',
        'slug' => 'pro',
        'price_monthly' => 2999,
        'amount' => 29.99,
        'currency' => 'USD',
        'interval' => 'month',
        'interval_count' => 1,
        'is_active' => true,
    ]);

    $this->makeTenant = function (string $slug): Tenant {
        return Tenant::create([
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'name' => Str::headline($slug),
            'email' => $slug.'@test.com',
            'plan_id' => 'pro',
            'status' => 'active',
            'billing_gateway' => 'dlocal',
        ]);
    };

    $this->makeSubscription = function (Tenant $tenant, array $overrides = []): Subscription {
        return Subscription::create(array_merge([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'plan_id' => $this->plan->id,
            'provider_subscription_id' => 'P-'.Str::random(6),
            'status' => 'active',
            'gateway' => 'dlocal',
            'current_period_end' => now()->subDay(),
            'next_payment_at' => now()->subDay(),
            'pm_card_id' => 'CARD-SAVED-1',
            'failed_attempts' => 0,
        ], $overrides));
    };
});

it('charges a due subscription via dLocal and rolls the period forward', function () {
    $tenant = ($this->makeTenant)('recurring-success');
    $subscription = ($this->makeSubscription)($tenant);

    $sent = [];
    Http::fake([
        'sandbox.dlocal.com/*' => function ($request) use (&$sent) {
            $sent = $request->data();

            return Http::response([
                'id' => 'P-200',
                'order_id' => $sent['order_id'],
                'status' => 'PAID',
                'status_detail' => null,
                'amount' => '29.99',
                'currency' => 'USD',
            ]);
        },
    ]);

    app(ChargeSubscriptionAction::class)->execute($subscription->id);

    expect($sent['card_id'])->toBe('CARD-SAVED-1');
    expect($sent['stored_credential_type'])->toBe('SUBSCRIPTION');
    expect($sent['stored_credential_usage'])->toBe('USED');
    expect($sent['payment_method_flow'])->toBe('DIRECT');

    $subscription->refresh();
    expect($subscription->status)->toBe('active');
    expect($subscription->next_payment_at->gt(now()))->toBeTrue();
    expect($subscription->current_period_end->gt(now()))->toBeTrue();
    expect($subscription->failed_attempts)->toBe(0);

    expect(Payment::where('tenant_id', $tenant->id)->where('status', 'approved')->count())->toBe(1);
    expect(Invoice::where('tenant_id', $tenant->id)->where('status', 'paid')->count())->toBe(1);
});

it('does not double-charge an already approved period', function () {
    $tenant = ($this->makeTenant)('recurring-idempotent');
    $subscription = ($this->makeSubscription)($tenant);

    Http::fake([
        'sandbox.dlocal.com/*' => Http::response([
            'id' => 'P-201',
            'order_id' => 'sub_'.$subscription->id.'_'.now()->format('Y-m'),
            'status' => 'PAID',
            'status_detail' => null,
            'amount' => '29.99',
            'currency' => 'USD',
        ]),
    ]);

    $action = app(ChargeSubscriptionAction::class);
    $action->execute($subscription->id);
    $action->execute($subscription->id);

    expect(Payment::where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('records declines, schedules a retry and suspends after the threshold', function () {
    Event::fake([TenantSuspendedByDunning::class]);

    $tenant = ($this->makeTenant)('recurring-decline');
    $subscription = ($this->makeSubscription)($tenant);

    Http::fake([
        'sandbox.dlocal.com/*' => Http::response([
            'id' => 'P-300',
            'order_id' => 'sub_'.$subscription->id.'_'.now()->format('Y-m'),
            'status' => 'REJECTED',
            'status_detail' => 'insufficient_funds',
            'amount' => '29.99',
            'currency' => 'USD',
        ]),
    ]);

    $action = app(ChargeSubscriptionAction::class);

    $action->execute($subscription->id);
    $action->execute($subscription->id);
    $action->execute($subscription->id);

    $subscription->refresh();
    expect($subscription->failed_attempts)->toBe(3);
    expect($subscription->next_payment_at->gt(now()))->toBeTrue();

    $tenant->refresh();
    expect($tenant->status)->toBe('suspended');

    Event::assertDispatched(TenantSuspendedByDunning::class, fn ($event) => $event->tenantId === $tenant->id);

    expect(Payment::where('tenant_id', $tenant->id)->where('status', 'declined')->count())->toBe(3);
});

it('skips gateway-managed subscriptions (clave)', function () {
    $tenant = ($this->makeTenant)('recurring-clave');
    $subscription = ($this->makeSubscription)($tenant, ['gateway' => 'clave', 'pm_card_id' => null]);

    Http::fake(['sandbox.dlocal.com/*' => Http::response([], 500)]);

    app(ChargeSubscriptionAction::class)->execute($subscription->id);

    expect(Payment::count())->toBe(0);
});

it('dispatches recurring charges for due subscriptions only', function () {
    Bus::fake();

    $tenant = ($this->makeTenant)('recurring-command');
    $due = ($this->makeSubscription)($tenant);
    ($this->makeSubscription)($tenant, [
        'provider_subscription_id' => 'P-FUTURE',
        'next_payment_at' => now()->addMonth(),
        'current_period_end' => now()->addMonth(),
    ]);

    $this->artisan('billing:process-recurring')->assertExitCode(0);

    Bus::assertDispatched(ChargeSubscriptionJob::class, function ($job) use ($due) {
        return $job->subscriptionId === $due->id && $job->tenantId === $due->tenant_id;
    });
});

it('fulfills a subscription and captures the saved card reference', function () {
    $tenant = ($this->makeTenant)('recurring-fulfill');

    $payment = Payment::create([
        'tenant_id' => $tenant->id,
        'display_id' => 'pay-123',
        'slug' => 'pay-123',
        'amount' => 29.99,
        'description' => 'Subscription to Pro',
        'email' => $tenant->email,
        'currency' => 'USD',
        'status' => 'approved',
        'gateway' => 'dlocal',
    ]);

    PaymentAttempt::create([
        'tenant_id' => $tenant->id,
        'payment_id' => $payment->id,
        'slug' => 'pay-123',
        'status' => 'approved',
        'payload' => [
            'customFieldValues' => [
                'type' => 'subscription',
                'tenant_id' => $tenant->id,
                'plan_id' => $this->plan->id,
            ],
        ],
    ]);

    $result = new PaymentResultData(
        gatewayReference: 'P-500',
        displayId: 'pay-123',
        status: PaymentStatus::Approved,
        amount: 29.99,
        gatewayCode: 'DLOCAL',
        authorizationCode: null,
        errorCode: null,
        errorMessage: null,
        raw: ['card_id' => 'CARD-NEW-1'],
    );

    PaymentApproved::dispatch($payment, $result);

    $subscription = Subscription::where('tenant_id', $tenant->id)->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->pm_card_id)->toBe('CARD-NEW-1');
    expect($subscription->gateway)->toBe('dlocal');
    expect($subscription->status)->toBe('active');
    expect($subscription->next_payment_at->gt(now()))->toBeTrue();
    expect($tenant->fresh()->plan_id)->toBe('pro');
});
