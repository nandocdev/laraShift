<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Str;

it('hides another tenant payments behind the Eloquent scope', function () {
    $makeTenant = fn (string $prefix) => tap(Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => $prefix.'-'.Str::random(6),
        'name' => $prefix.' Tenant',
        'email' => $prefix.'-'.Str::random(6).'@test.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]), fn ($t) => $t->domains()->create(['domain' => $t->slug.'.localhost']));

    $tenantA = $makeTenant('scope-a');
    $tenantB = $makeTenant('scope-b');

    $payment = Payment::withoutGlobalScopes()->create([
        'tenant_id' => $tenantA->id,
        'display_id' => 'INV-SCOPE-'.Str::upper(Str::random(6)),
        'slug' => 'pay-'.Str::random(10),
        'amount' => 10.0,
        'description' => 'Scope probe',
        'email' => 'scope@test.com',
        'currency' => 'USD',
        'status' => 'pending',
        'gateway' => 'CLAVE',
    ]);

    tenancy()->initialize($tenantB);

    try {
        expect(Payment::where('id', $payment->id)->exists())->toBeFalse()
            ->and(Payment::withoutGlobalScopes()->where('id', $payment->id)->exists())->toBeTrue();
    } finally {
        tenancy()->end();
    }
});
