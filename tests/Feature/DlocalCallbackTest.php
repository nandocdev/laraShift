<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\DTO\PaymentData;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Integrations\Dlocal\Models\PaymentReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'dlocal-cb',
        'name' => 'dLocal Callback',
        'email' => 'cb@test.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);

    $this->tenant->domains()->create(['domain' => 'dlocal-cb.'.config('tenancy.central_domain')]);
    tenancy()->initialize($this->tenant);

    $this->makeReference = function (string $paymentId, string $tenantId): PaymentReference {
        return PaymentReference::create([
            'external_reference' => $paymentId,
            'order_id' => 'sub_'.$tenantId,
            'context' => 'central',
            'tenant_id' => $tenantId,
        ]);
    };
});

it('sends callback_url (not success/back urls) in the redirect flow', function () {
    $sent = [];
    Http::fake([
        'sandbox.dlocal.com/*' => function ($request) use (&$sent) {
            $sent = $request->data();

            return Http::response([
                'id' => 'P-CB-1',
                'order_id' => $sent['order_id'],
                'status' => 'PENDING',
                'status_detail' => null,
                'amount' => '29.99',
                'currency' => 'USD',
                'redirect_url' => 'https://sandbox.dlocal.com/pay/P-CB-1',
            ]);
        },
    ]);

    $url = app(DlocalGateway::class)->buildCheckoutUrl(new PaymentData(
        amount: 29.99,
        description: 'Subscription to Pro',
        displayId: 'sub_'.$this->tenant->id,
        email: $this->tenant->email,
        tenantId: $this->tenant->id,
        customFieldValues: ['type' => 'subscription', 'tenant_id' => $this->tenant->id],
    ), '');

    expect($url)->toBe('https://sandbox.dlocal.com/pay/P-CB-1');
    expect($sent)->toHaveKey('callback_url');
    expect($sent['callback_url'])->toBe(route('central.billing.dlocal.callback'));
    expect($sent)->not->toHaveKeys(['success_url', 'back_url']);
});

it('redirects to the tenant success page when the payment is approved', function () {
    ($this->makeReference)('P-APPROVED', $this->tenant->id);

    $response = $this->get(route('central.billing.dlocal.callback', [
        'paymentId' => 'P-APPROVED',
        'status' => 'APPROVED',
    ]));

    $response->assertRedirect('http://dlocal-cb.'.config('tenancy.central_domain').'/billing/success');
});

it('redirects to the tenant cancel page when the payment is rejected', function () {
    ($this->makeReference)('P-REJECTED', $this->tenant->id);

    $response = $this->get(route('central.billing.dlocal.callback', [
        'paymentId' => 'P-REJECTED',
        'status' => 'REJECTED',
    ]));

    $response->assertRedirect('http://dlocal-cb.'.config('tenancy.central_domain').'/billing/cancel');
});

it('returns 404 for an unknown payment reference', function () {
    $this->get(route('central.billing.dlocal.callback', [
        'paymentId' => 'P-UNKNOWN-'.Str::random(6),
        'status' => 'APPROVED',
    ]))->assertStatus(404);
});
