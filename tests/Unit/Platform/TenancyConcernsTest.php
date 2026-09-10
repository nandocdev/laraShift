<?php

declare(strict_types=1);

use App\Modules\Platform\Tenancy\Domain\Concerns\TenantScope;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\RehydrateTenantContext;
use App\Modules\Tenant\Access\Domain\Models\User;

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

it('applies the TenantScope to tenant models via the concern', function () {
    expect((new User)->getGlobalScopes())->toHaveKey(TenantScope::class);
});
