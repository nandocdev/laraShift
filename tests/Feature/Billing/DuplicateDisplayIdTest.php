<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\DTO\PaymentData;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentAttempt;
use App\Modules\Central\Billing\Infrastructure\Gateways\CheckoutManager;
use App\Modules\Central\Provisioning\Models\Domain;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('reuses the same payment on concurrent initiate with the same display_id', function () {
    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'dup-'.Str::random(6),
        'name' => 'Dup Tenant',
        'email' => 'dup-'.Str::random(6).'@test.com',
        'plan_id' => 'free',
        'status' => 'active',
        'billing_gateway' => 'clave',
    ]);

    Domain::create([
        'domain' => $tenant->slug.'.localhost',
        'tenant_id' => $tenant->id,
    ]);

    Http::fake([
        '*/LinkDeamon.cfm' => Http::response([
            'success' => true,
            'data' => ['url' => 'https://sandbox.paguelofacil.com/checkout/LK-123'],
        ], 200),
    ]);

    $data = new PaymentData(
        amount: 29.99,
        description: 'Subscription to Pro',
        displayId: 'INV-DUP-'.Str::upper(Str::random(6)),
        email: $tenant->email,
        tenantId: (string) $tenant->id,
    );

    $manager = app(CheckoutManager::class);

    $manager->initiate($data, (string) $tenant->id, 'test-key');
    $manager->initiate($data, (string) $tenant->id, 'test-key');

    expect(Payment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('display_id', $data->displayId)->count())->toBe(1)
        ->and(PaymentAttempt::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(2);
});
