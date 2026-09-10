<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentReference;
use App\Modules\Central\Billing\Domain\Models\PaymentWebhook;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\RehydrateTenantContext;
use Illuminate\Support\Str;

function leakTenants(): array
{
    $make = fn (string $slug) => Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => $slug,
        'name' => 'Leak Test',
        'email' => $slug.'@test.com',
        'status' => 'active',
        'billing_gateway' => 'clave',
    ]);

    return [$make('leak-a'), $make('leak-b')];
}

function seedTenantBilling(Tenant $tenant): void
{
    Payment::create([
        'tenant_id' => $tenant->id, 'slug' => 'pay_LEAK-'.$tenant->slug, 'display_id' => 'DSP-LEAK-'.$tenant->slug,
        'amount_cents' => 2900, 'currency' => 'USD', 'status' => PaymentStatus::Approved, 'gateway' => 'clave',
    ]);
    Subscription::create([
        'tenant_id' => $tenant->id, 'status' => 'active', 'gateway' => 'clave',
    ]);
    PaymentWebhook::create([
        'tenant_id' => $tenant->id, 'gateway' => 'clave',
        'gateway_reference' => 'LEAK-'.$tenant->slug, 'display_id' => 'DSP-LEAK-'.$tenant->slug,
        'status' => 'payment.succeeded',
    ]);
    PaymentReference::create([
        'tenant_id' => $tenant->id, 'external_reference' => 'CUST-'.$tenant->slug,
        'context' => PaymentReference::CONTEXT_CUSTOMER,
    ]);
    Invoice::create([
        'tenant_id' => $tenant->id, 'amount_cents' => 2900, 'currency' => 'USD', 'status' => 'paid',
    ]);
}

it('isolates billing rows between tenants through model scopes', function () {
    [$tenantA, $tenantB] = leakTenants();
    seedTenantBilling($tenantA);
    seedTenantBilling($tenantB);

    tenancy()->initialize($tenantB);

    try {
        expect(Payment::count())->toBe(1)
            ->and(Subscription::count())->toBe(1)
            ->and(PaymentWebhook::count())->toBe(1)
            ->and(PaymentReference::count())->toBe(1)
            ->and(Invoice::count())->toBe(1)
            ->and(Payment::first()->tenant_id)->toBe($tenantB->id);
    } finally {
        tenancy()->end();
    }
});

it('does not leak context when workers are reused across tenants', function () {
    [$tenantA, $tenantB] = leakTenants();
    seedTenantBilling($tenantA);

    tenancy()->initialize($tenantA);
    expect(Payment::count())->toBe(1);
    tenancy()->end();

    // Same process, next unit of work (Octane-style reuse).
    tenancy()->initialize($tenantB);

    try {
        expect(tenancy()->tenant->id)->toBe($tenantB->id)
            ->and(Payment::count())->toBe(0);
    } finally {
        tenancy()->end();
    }
});

it('fails loudly when a job carries an unknown tenant', function () {
    $job = new class implements TenantAware
    {
        public function tenantId(): string
        {
            return '00000000-0000-0000-0000-000000000000';
        }
    };

    // RehydrateTenantContext must throw on unknown tenants: never run blind.
    expect(fn () => (new RehydrateTenantContext)->handle(
        $job,
        fn () => throw new RuntimeException('must never execute without tenant')
    ))->toThrow(Exception::class, 'could not be identified');
});
