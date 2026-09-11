<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\Actions\ChargeDirectAction;
use App\Modules\Central\Billing\Application\Actions\IssueInvoiceAction;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Platform\Contracts\Billing\DirectPaymentData;
use App\Modules\Platform\Contracts\Billing\PaymentMethodType;
use App\Modules\Tenant\Access\Domain\Models\User;

use function Pest\Laravel\assertDatabaseHas;

function invoiceTestContext(string $slug): array
{
    $tenant = dlocalTestTenant($slug);
    $domain = $slug.'.'.parse_url(config('app.url'), PHP_URL_HOST);
    $tenant->domains()->create(['domain' => $domain]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'status' => 'active']);

    Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro', 'price_monthly' => 2900, 'price_yearly' => 29000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => ['api_access']],
        'is_active' => true,
    ]);

    return [$tenant, $domain, $user];
}

it('issues exactly one invoice for an approved direct charge', function () {
    Http::fake([
        '*/payments' => Http::response(['id' => 'D-INV-001', 'status' => 'APPROVED', 'card_id' => 'CARD-INV-1']),
    ]);
    [$tenant] = invoiceTestContext('inv-direct');

    $subscription = Subscription::create([
        'tenant_id' => $tenant->id,
        'plan_id' => Plan::where('slug', 'pro')->value('id'),
        'status' => SubscriptionStatus::Active,
        'gateway' => 'dlocal',
    ]);

    app(ChargeDirectAction::class)->execute(new DirectPaymentData(
        tenantId: $tenant->id,
        gateway: 'dlocal',
        orderId: 'DSP-INV-1',
        amountCents: 2900,
        currency: 'USD',
        method: PaymentMethodType::Card,
        paymentToken: 'tok_inv_test',
        subscriptionId: $subscription->id,
        payerDocument: '12345678',
    ));

    $payment = Payment::where('display_id', 'DSP-INV-1')->firstOrFail();

    assertDatabaseHas('invoices', [
        'tenant_id' => $tenant->id,
        'payment_id' => $payment->id,
        'subscription_id' => $subscription->id,
        'status' => 'paid',
        'amount_cents' => 2900,
    ]);
    expect(Invoice::where('payment_id', $payment->id)->count())->toBe(1);
});

it('does not duplicate invoices on repeated issuance', function () {
    [$tenant] = invoiceTestContext('inv-dupe');

    $payment = Payment::create([
        'tenant_id' => $tenant->id,
        'slug' => 'pay_DSP-INV-DUPE',
        'display_id' => 'DSP-INV-DUPE',
        'amount_cents' => 2900,
        'currency' => 'USD',
        'status' => 'approved',
        'gateway' => 'dlocal',
        'gateway_reference' => 'D-INV-DUPE',
    ]);

    $action = app(IssueInvoiceAction::class);
    $action->execute($payment);
    $action->execute($payment);

    expect(Invoice::where('payment_id', $payment->id)->count())->toBe(1);
});

it('downloads the invoice as PDF', function () {
    [$tenant, $domain, $user] = invoiceTestContext('inv-pdf');

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'amount_cents' => 2900,
        'currency' => 'USD',
        'status' => 'paid',
        'issued_at' => now(),
        'paid_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('http://'.$domain."/billing/invoices/{$invoice->id}/pdf");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('returns 404 for another tenant invoice PDF', function () {
    [$tenantA, $domainA, $userA] = invoiceTestContext('inv-a');
    [$tenantB] = invoiceTestContext('inv-b');

    $invoice = Invoice::create([
        'tenant_id' => $tenantB->id,
        'amount_cents' => 2900,
        'currency' => 'USD',
        'status' => 'paid',
        'issued_at' => now(),
        'paid_at' => now(),
    ]);

    $this->actingAs($userA)->get('http://'.$domainA."/billing/invoices/{$invoice->id}/pdf")
        ->assertNotFound();

    expect($invoice->fresh())->not->toBeNull();
});
