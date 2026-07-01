<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Actions\SyncInvoicesAction;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Shared\Contracts\PaymentGatewayContract;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\Enums\PaymentContext;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\Services\Gateways\ClaveEnvironment;
use App\Modules\Central\Payments\Services\Gateways\ClaveGateway;
use App\Modules\Central\Payments\Services\Gateways\DlocalGateway;
use App\Modules\Central\Payments\Services\PaymentHandlerDispatcher;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'test-payments',
        'name' => 'Test Payments',
        'email' => 'test@payments.com',
        'plan_id' => 'free',
        'billing_gateway' => 'paguelofacil',
    ]);
});

it('resolves the correct gateway based on tenant settings', function () {
    // 1. Default (from config)
    $defaultGateway = config('payments.default', 'clave');
    $gateway = app(PaymentGatewayContract::class);

    if ($defaultGateway === 'dlocal') {
        expect($gateway)->toBeInstanceOf(DlocalGateway::class);
    } else {
        expect($gateway)->toBeInstanceOf(ClaveGateway::class);
    }

    // 2. Force switch
    $this->tenant->update(['billing_gateway' => 'clave']);
    tenancy()->initialize($this->tenant);

    $gateway = app(PaymentGatewayContract::class);
    expect($gateway)->toBeInstanceOf(ClaveGateway::class);
});

it('generates a PagueloFacil hosted checkout URL', function () {
    $plan = Plan::create([
        'slug' => 'pro',
        'name' => 'Pro Plan',
        'price_monthly' => 2999,
        'amount' => 29.99,
    ]);

    Http::fake([
        '*/LinkDeamon.cfm' => Http::response([
            'success' => true,
            'data' => ['url' => 'https://checkout.paguelofacil.com/test'],
        ], 200),
    ]);

    $gateway = new ClaveGateway(ClaveEnvironment::Sandbox);
    $url = $gateway->buildCheckoutUrl(new PaymentData(
        context: PaymentContext::Subscription,
        amount: 29.99,
        description: 'Test',
        displayId: '123',
        email: 'user@test.com',
        tenantId: $this->tenant->id,
        customFieldValues: ['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id]
    ), 'test-key');

    expect($url)->toBe('https://checkout.paguelofacil.com/test');
});

it('syncs invoices from multiple gateways', function () {
    // Mock PagueloFacil transactions (must match SyncInvoicesAction mapping)
    $pfData = [
        'codOper' => 'TX123',
        'totalPay' => '29.99',
        'date' => now()->toDateTimeString(),
        'status' => 1,
    ];

    // Mock dLocal transactions
    $dlData = [
        'payment_id' => 'DL456',
        'amount' => 29.99,
        'status' => 'PAID',
        'created_date' => now()->toDateTimeString(),
    ];

    $action = app(SyncInvoicesAction::class);

    // Test mapping for PF
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('mapAndStore');
    $method->setAccessible(true);

    $method->invoke($action, $this->tenant, $pfData);
    $this->assertDatabaseHas('invoices', ['provider_invoice_id' => 'TX123', 'amount' => 2999]);

    // Test mapping for dLocal
    $method->invoke($action, $this->tenant, $dlData);
    $this->assertDatabaseHas('invoices', ['provider_invoice_id' => 'DL456', 'amount' => 2999]);
});

it('fulfills a subscription when payment is approved', function () {
    $plan = Plan::create([
        'slug' => 'pro',
        'name' => 'Pro Plan',
        'price_monthly' => 2999,
        'amount' => 29.99,
    ]);

    $payment = Payment::create([
        'tenant_id' => $this->tenant->id,
        'display_id' => 'sub_'.$this->tenant->id,
        'slug' => 'test-slug',
        'amount' => 29.99,
        'description' => 'Test',
        'email' => 'test@test.com',
        'status' => 'pending',
        'gateway' => 'paguelofacil',
    ]);

    // Mock the attempt with metadata (SyncInvoicesAction uses this)
    $payment->attempts()->create([
        'tenant_id' => $this->tenant->id,
        'slug' => 'test-slug',
        'status' => 'initiated',
        'payload' => [
            'customFieldValues' => [
                'type' => 'subscription',
                'plan_id' => $plan->id,
                'tenant_id' => $this->tenant->id,
            ],
        ],
    ]);

    $dispatcher = app(PaymentHandlerDispatcher::class);
    $dispatcher->dispatch(
        context: PaymentContext::Subscription,
        tenantId: $this->tenant->id,
        displayId: 'sub_'.$this->tenant->id,
        amount: 29.99,
        success: true,
        metadata: [
            'plan_id' => $plan->id,
            'gateway_reference' => 'TX_SUCCESS',
            'gateway' => 'paguelofacil',
            'currency' => 'USD',
        ]
    );

    // The listener should have executed
    $this->tenant->refresh();
    expect($this->tenant->plan_id)->toBe('pro');
    $this->assertDatabaseHas('subscriptions', [
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);
});
