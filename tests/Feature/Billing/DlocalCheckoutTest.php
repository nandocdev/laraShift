<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\Actions\CreateCheckoutSessionAction;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Platform\Contracts\Billing\PlanRef;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseHas;

function fakeDlocalCreate(array $overrides = []): void
{
    Http::fake([
        '*/payments' => Http::response(array_merge([
            'id' => 'D-TEST-001',
            'order_id' => 'DSP-DL-1',
            'status' => 'PENDING',
            'redirect_url' => 'https://checkout.dlocal.com/pay/TEST456',
        ], $overrides)),
    ]);
}

it('creates a redirect checkout storing the provider reference', function () {
    fakeDlocalCreate();
    $tenant = dlocalTestTenant('dlocal-redirect');

    $session = app(CreateCheckoutSessionAction::class)->execute(
        $tenant,
        new PlanRef(slug: 'pro', amountCents: 2900, currency: 'USD', gatewayIds: []),
        'DSP-DL-1'
    );

    expect($session->provider)->toBe('dlocal')
        ->and($session->url)->toContain('checkout.dlocal.com');

    assertDatabaseHas('payments', ['tenant_id' => $tenant->id, 'display_id' => 'DSP-DL-1', 'gateway' => 'dlocal']);
    assertDatabaseHas('payment_references', [
        'tenant_id' => $tenant->id,
        'external_reference' => 'D-TEST-001',
        'order_id' => 'DSP-DL-1',
        'context' => 'order',
    ]);
    expect(Payment::where('tenant_id', $tenant->id)->count())->toBe(1);
});
