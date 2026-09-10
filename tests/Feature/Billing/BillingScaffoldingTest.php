<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentGatewayEvent;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;

it('creates payments and subscriptions via factories', function () {
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'billing-scaffold',
        'name' => 'Billing Scaffold',
        'email' => 'billing-scaffold@test.com',
        'status' => 'active',
    ]);

    $payment = Payment::factory()->create(['tenant_id' => $tenant->id]);
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id]);

    assertDatabaseHas('payments', ['id' => $payment->id, 'tenant_id' => $tenant->id]);
    assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'tenant_id' => $tenant->id]);

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);
});

it('keeps gateway events central without tenant scope', function () {
    $event = PaymentGatewayEvent::create([
        'gateway' => 'clave',
        'gateway_event_id' => 'evt_test_123',
        'event_type' => 'payment.succeeded',
    ]);

    assertDatabaseHas('payment_gateway_events', ['id' => $event->id]);

    expect($event->getTable())->toBe('payment_gateway_events');
});
