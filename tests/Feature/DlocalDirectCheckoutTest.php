<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\DTO\PaymentData;
use App\Modules\Central\Billing\Application\DTO\PaymentResultData;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Domain\Events\PaymentDeclined;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Billing\Infrastructure\Gateways\CheckoutManager;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'direct-checkout',
        'name' => 'Direct Checkout',
        'email' => 'direct@test.com',
        'plan_id' => 'pro',
        'status' => 'active',
        'billing_gateway' => 'dlocal',
    ]);

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

    tenancy()->initialize($this->tenant);
});

function dlocalPaymentData(Tenant $tenant, Plan $plan, string $token): PaymentData
{
    return new PaymentData(
        amount: 29.99,
        description: 'Subscription to Pro',
        displayId: 'sub_'.$tenant->id,
        email: $tenant->email,
        tenantId: $tenant->id,
        customFieldValues: [
            'type' => 'subscription',
            'plan_id' => $plan->id,
            'tenant_id' => $tenant->id,
        ],
        token: $token,
        payerName: 'John Doe',
    );
}

it('processes a DIRECT payment with the Smart Fields token and saves the card', function () {
    $sent = [];
    Http::fake([
        'sandbox.dlocal.com/*' => function ($request) use (&$sent) {
            $sent = $request->data();

            return Http::response([
                'id' => 'P-DIRECT-1',
                'order_id' => $sent['order_id'],
                'status' => 'PAID',
                'status_detail' => null,
                'amount' => '29.99',
                'currency' => 'USD',
                'card_id' => 'CARD-NEW-1',
            ]);
        },
    ]);

    $gateway = app(DlocalGateway::class);
    $result = $gateway->processDirectPayment(dlocalPaymentData($this->tenant, $this->plan, 'CV-TOKEN-1'), '');

    expect($result)->toBeInstanceOf(PaymentResultData::class);
    expect($result->status)->toBe(PaymentStatus::Approved);
    expect($result->raw['card_id'])->toBe('CARD-NEW-1');

    expect($sent['payment_method_flow'])->toBe('DIRECT');
    expect($sent['token'])->toBe('CV-TOKEN-1');
    expect($sent['save'])->toBeTrue();
    expect($sent['stored_credential_type'])->toBe('SUBSCRIPTION');
    expect($sent)->not->toHaveKey('callback_url');
});

it('maps a rejected DIRECT payment to declined', function () {
    Http::fake([
        'sandbox.dlocal.com/*' => Http::response([
            'id' => 'P-DIRECT-2',
            'order_id' => 'sub_'.$this->tenant->id,
            'status' => 'REJECTED',
            'status_detail' => 'insufficient_funds',
            'amount' => '29.99',
            'currency' => 'USD',
        ]),
    ]);

    $gateway = app(DlocalGateway::class);
    $result = $gateway->processDirectPayment(dlocalPaymentData($this->tenant, $this->plan, 'CV-TOKEN-2'), '');

    expect($result->status)->toBe(PaymentStatus::Declined);
    expect($result->errorMessage)->toBe('insufficient_funds');
});

it('requires a token for direct payments', function () {
    $gateway = app(DlocalGateway::class);

    expect(fn () => $gateway->processDirectPayment(
        new PaymentData(
            amount: 29.99,
            description: 'Sub',
            displayId: 'sub_x',
            email: $this->tenant->email,
            tenantId: $this->tenant->id,
        ),
        ''
    ))->toThrow(InvalidArgumentException::class);
});

it('creates a payment, charges directly and dispatches PaymentApproved', function () {
    Event::fake([PaymentApproved::class, PaymentDeclined::class]);

    Http::fake([
        'sandbox.dlocal.com/*' => Http::response([
            'id' => 'P-DIRECT-3',
            'order_id' => 'sub_'.$this->tenant->id,
            'status' => 'PAID',
            'status_detail' => null,
            'amount' => '29.99',
            'currency' => 'USD',
            'card_id' => 'CARD-NEW-3',
        ]),
    ]);

    $session = (new CheckoutManager(app(DlocalGateway::class)))->initiate(
        dlocalPaymentData($this->tenant, $this->plan, 'CV-TOKEN-3'),
        $this->tenant->id,
        '',
    );

    expect($session->checkoutUrl)->toBeNull();
    expect($session->result->status)->toBe(PaymentStatus::Approved);

    $payment = Payment::where('tenant_id', $this->tenant->id)->first();
    expect($payment->status)->toBe('approved');
    expect($payment->gateway)->toBe('dlocal');

    Event::assertDispatched(PaymentApproved::class, fn ($event) => $event->payment->id === $payment->id);
    Event::assertNotDispatched(PaymentDeclined::class);
});

it('fulfills the subscription and captures the card reference on approval', function () {
    Http::fake([
        'sandbox.dlocal.com/*' => Http::response([
            'id' => 'P-DIRECT-4',
            'order_id' => 'sub_'.$this->tenant->id,
            'status' => 'PAID',
            'status_detail' => null,
            'amount' => '29.99',
            'currency' => 'USD',
            'card_id' => 'CARD-NEW-4',
        ]),
    ]);

    (new CheckoutManager(app(DlocalGateway::class)))->initiate(
        dlocalPaymentData($this->tenant, $this->plan, 'CV-TOKEN-4'),
        $this->tenant->id,
        '',
    );

    $subscription = Subscription::where('tenant_id', $this->tenant->id)->first();

    expect($subscription)->not->toBeNull();
    expect($subscription->status)->toBe('active');
    expect($subscription->gateway)->toBe('dlocal');
    expect($subscription->pm_card_id)->toBe('CARD-NEW-4');
    expect($subscription->next_payment_at)->not->toBeNull();
});

it('dispatches PaymentDeclined when the direct charge is rejected', function () {
    Event::fake([PaymentApproved::class, PaymentDeclined::class]);

    Http::fake([
        'sandbox.dlocal.com/*' => Http::response([
            'id' => 'P-DIRECT-5',
            'order_id' => 'sub_'.$this->tenant->id,
            'status' => 'REJECTED',
            'status_detail' => 'card_declined',
            'amount' => '29.99',
            'currency' => 'USD',
        ]),
    ]);

    $session = (new CheckoutManager(app(DlocalGateway::class)))->initiate(
        dlocalPaymentData($this->tenant, $this->plan, 'CV-TOKEN-5'),
        $this->tenant->id,
        '',
    );

    expect($session->result->status)->toBe(PaymentStatus::Declined);

    $payment = Payment::where('tenant_id', $this->tenant->id)->first();
    expect($payment->status)->toBe('declined');

    Event::assertDispatched(PaymentDeclined::class);
    Event::assertNotDispatched(PaymentApproved::class);
});
