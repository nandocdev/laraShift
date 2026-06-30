<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Payments\Contracts\PaymentGateway;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\Enums\PaymentContext;
use App\Modules\Central\Provisioning\Models\Domain;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.default' => 'clave']);

        // 1. Create a tenant and its domain
        $this->tenant = Tenant::create([
            'id' => Str::uuid()->toString(),
            'slug' => 'initech',
            'name' => 'Initech',
            'email' => 'admin@initech.com',
            'plan_id' => 'free',
            'status' => 'active',
        ]);

        Domain::create([
            'domain' => 'initech.localhost',
            'tenant_id' => $this->tenant->id,
        ]);

        // 2. Create a paid plan
        $this->plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price_monthly' => 2999,
            'amount' => 29.99,
            'currency' => 'USD',
            'is_active' => true,
        ]);
    }

    public function test_full_paguelofacil_checkout_and_callback_flow()
    {
        // --- STEP 1: Initiation ---
        Http::fake([
            '*/LinkDeamon.cfm' => Http::response([
                'success' => true,
                'data' => ['url' => 'https://sandbox.paguelofacil.com/checkout/LK-123'],
            ], 200),
        ]);

        $gateway = app(PaymentGateway::class);

        $paymentData = new PaymentData(
            context: PaymentContext::Subscription,
            amount: 29.99,
            description: 'Subscription to Pro',
            displayId: 'sub_'.$this->tenant->id,
            email: $this->tenant->email,
            tenantId: $this->tenant->id,
            customFieldValues: [
                'type' => 'subscription',
                'plan_id' => $this->plan->id,
                'tenant_id' => $this->tenant->id,
            ]
        );

        $checkoutUrl = $gateway->buildCheckoutUrl($paymentData, 'test-api-key');

        $this->assertSame('https://sandbox.paguelofacil.com/checkout/LK-123', $checkoutUrl);

        // --- STEP 2: Handle Callback (Simulate Paguelofacil Redirect) ---

        $callbackParams = [
            'Estado' => 'Aprobada',
            'TotalPagado' => '29.99',
            'Oper' => 'LK-8SNKWCRNBHK5',
            'PARM_1' => $this->tenant->id,
            'PARM_2' => $this->plan->id,
            'Razon' => 'Approved',
        ];

        // The callback route is on the central domain
        $response = $this->get(route('central.billing.paguelofacil.callback', $callbackParams));

        // --- STEP 3: Verify Results ---

        // 1. Verify redirect to tenant success page
        $expectedSuccessUrl = 'http://initech.localhost/billing/success';
        $response->assertRedirect($expectedSuccessUrl);

        // 2. Verify subscription record in DB
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'provider_subscription_id' => 'LK-8SNKWCRNBHK5',
        ]);

        // 3. Verify tenant plan update
        $this->tenant->refresh();
        $this->assertEquals('pro', $this->tenant->plan_id);
    }

    public function test_paguelofacil_denied_payment_redirects_to_cancel()
    {
        $callbackParams = [
            'Estado' => 'Denegada',
            'TotalPagado' => '0',
            'Oper' => 'LK-FAILED',
            'PARM_1' => $this->tenant->id,
            'PARM_2' => $this->plan->id,
            'Razon' => 'Card rejected',
        ];

        $response = $this->get(route('central.billing.paguelofacil.callback', $callbackParams));

        // Verify redirect to tenant cancel page
        $expectedCancelUrl = 'http://initech.localhost/billing/cancel';
        $response->assertRedirect($expectedCancelUrl);

        // Verify NO subscription was created
        $this->assertDatabaseCount('subscriptions', 0);
    }
}
