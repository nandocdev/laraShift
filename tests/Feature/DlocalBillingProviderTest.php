<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Billing\Support\Drivers\DlocalBillingProvider;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DlocalBillingProviderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Plan $plan;
    private DlocalBillingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => Str::uuid()->toString(),
            'slug' => 'dlocal-tenant',
            'name' => 'dLocal Tenant',
            'email' => 'tenant@dlocal.com',
            'plan_id' => 'free',
            'status' => 'active',
        ]);

        $this->plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price_monthly' => 2999,
            'amount' => 29.99,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->provider = app(DlocalBillingProvider::class);
    }

    public function test_create_checkout_session_sends_correct_payload_and_returns_url()
    {
        Http::fake([
            '*/enrollments' => Http::response([
                'id' => 'E-12345',
                'status' => 'PENDING',
                'redirect_url' => 'https://pay.dlocal.com/gmf-apm/payments-redirect/E-12345'
            ], 200)
        ]);

        $redirectUrl = $this->provider->createCheckoutSession($this->tenant, 'pro');

        $this->assertSame('https://pay.dlocal.com/gmf-apm/payments-redirect/E-12345', $redirectUrl);

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);
            return str_contains($request->url(), '/enrollments') &&
                   $request->method() === 'POST' &&
                   $data['type'] === 'MERCHANT_SUBSCRIPTION' &&
                   $data['subscription']['amount']['value'] === 29.99 &&
                   $data['payer']['email'] === 'tenant@dlocal.com';
        });
    }

    public function test_cancel_subscription_calls_gateway_and_updates_db()
    {
        Http::fake([
            '*/enrollments/E-12345/cancel' => Http::response([
                'status' => 'CANCELLED',
                'status_code' => '400'
            ], 200)
        ]);

        // Create subscription in DB
        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'provider_subscription_id' => 'E-12345',
            'status' => 'active',
            'gateway' => 'dlocal',
        ]);

        $this->provider->cancelSubscription($this->tenant, 'E-12345');

        $subscription->refresh();
        $this->assertSame('cancelled', $subscription->status);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/enrollments/E-12345/cancel') &&
                   $request->method() === 'POST';
        });
    }

    public function test_get_subscription_data_returns_mapped_values()
    {
        Http::fake([
            '*/enrollments/E-12345' => Http::response([
                'id' => 'E-12345',
                'status' => 'ACTIVE',
                'subscription' => [
                    'end_date' => '2026-12-31'
                ]
            ], 200)
        ]);

        $data = $this->provider->getSubscriptionData($this->tenant, 'E-12345');

        $this->assertNotNull($data);
        $this->assertSame('ACTIVE', $data['status']);
        $this->assertSame('2026-12-31', $data['current_period_end']);
    }

    public function test_sync_subscription_updates_status_and_period()
    {
        Http::fake([
            '*/enrollments/E-12345' => Http::response([
                'id' => 'E-12345',
                'status' => 'ACTIVE',
                'subscription' => [
                    'end_date' => '2026-12-31'
                ]
            ], 200)
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'provider_subscription_id' => 'E-12345',
            'status' => 'pending',
            'gateway' => 'dlocal',
        ]);

        $this->provider->syncSubscription($this->tenant);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('2026-12-31 00:00:00', $subscription->current_period_end->toDateTimeString());
    }
}
