<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\Actions\CreateCheckoutSessionAction;
use App\Modules\Central\Billing\Domain\Models\Payment;

it('collapses concurrent checkouts with the same display_id into one payment', function () {
    fakeClaveLink();
    $tenant = claveTestTenant('clave-double');

    $action = app(CreateCheckoutSessionAction::class);

    $first = $action->execute($tenant, clavePlanRef(), 'DSP-DOUBLE-1');
    $second = $action->execute($tenant, clavePlanRef(), 'DSP-DOUBLE-1');

    expect(Payment::where('tenant_id', $tenant->id)->where('display_id', 'DSP-DOUBLE-1')->count())->toBe(1)
        ->and($first->url)->toBe($second->url)
        ->and($first->url)->toContain('paguelofacil.com');
});

it('recovers from a unique race by reusing the existing row', function () {
    fakeClaveLink();
    $tenant = claveTestTenant('clave-race');

    Payment::create([
        'tenant_id' => $tenant->id,
        'slug' => 'pay_DSP-RACE-1',
        'display_id' => 'DSP-RACE-1',
        'amount_cents' => 2900,
        'currency' => 'USD',
        'status' => 'pending',
        'gateway' => 'clave',
    ]);

    $session = app(CreateCheckoutSessionAction::class)->execute($tenant, clavePlanRef(), 'DSP-RACE-1');

    expect(Payment::where('tenant_id', $tenant->id)->where('display_id', 'DSP-RACE-1')->count())->toBe(1)
        ->and($session->id)->toBe('DSP-RACE-1');
});
