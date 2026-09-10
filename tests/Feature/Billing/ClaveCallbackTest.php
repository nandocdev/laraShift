<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\get;

it('never mutates state on return callbacks, approved or manipulated', function () {
    $tenant = claveTestTenant('clave-callback');

    get(route('payments.clave.callback', ['PARM_1' => $tenant->id, 'PARM_2' => 'DSP-CB-1', 'Estado' => 'Aprobada']))
        ->assertRedirect();

    get(route('payments.clave.callback', ['PARM_1' => $tenant->id, 'PARM_2' => 'DSP-CB-1', 'Estado' => 'Denegada']))
        ->assertRedirect();

    get(route('payments.clave.callback', ['PARM_1' => 'nonexistent', 'Estado' => 'Aprobada']))
        ->assertRedirect();

    assertDatabaseCount('payments', 0);
    assertDatabaseCount('subscriptions', 0);
    assertDatabaseCount('payment_gateway_events', 0);

    expect(Payment::count())->toBe(0)
        ->and(Subscription::count())->toBe(0);
});
