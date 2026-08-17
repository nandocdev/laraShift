<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentAttempt;
use App\Modules\Central\Billing\Domain\Models\PaymentWebhook;
use App\Modules\Platform\Tenancy\Domain\Concerns\TenantScope;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\RehydrateTenantContext;

it('provides tenantId and the rehydration middleware from the trait', function () {
    $job = new class('tenant-1')
    {
        use RehydratesTenantContext;

        public function __construct(public string $tenantId) {}
    };

    expect($job->tenantId())->toBe('tenant-1');

    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(RehydrateTenantContext::class);
});

it('applies the TenantScope to billing models via the concern', function () {
    expect((new Payment)->getGlobalScopes())->toHaveKey(TenantScope::class);
    expect((new PaymentAttempt)->getGlobalScopes())->toHaveKey(TenantScope::class);
    expect((new PaymentWebhook)->getGlobalScopes())->toHaveKey(TenantScope::class);
});

it('coexists with extra booted hooks on the same model', function () {
    // PaymentWebhook mixes the scope (trait boot) with its own immutability
    // guard in booted(); both boot without conflict.
    expect((new PaymentWebhook)->getGlobalScopes())->toHaveKey(TenantScope::class);
});
