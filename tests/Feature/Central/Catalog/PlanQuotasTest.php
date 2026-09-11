<?php

declare(strict_types=1);

use App\Modules\Central\Catalog\Application\Actions\ResolveTenantFeatures;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Tenancy\Application\Services\QuotaManager;
use Illuminate\Support\Str;

function quotaTestTenant(string $slug, ?string $planSlug): Tenant
{
    return Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => $slug,
        'name' => 'Quota Test',
        'email' => $slug.'@test.com',
        'status' => 'active',
        'billing_gateway' => 'dlocal',
        'plan_id' => $planSlug,
    ]);
}

function quotaTestPlan(string $slug, array $quotas, array $features = []): Plan
{
    return Plan::create([
        'name' => $slug, 'slug' => $slug, 'price_monthly' => 1000, 'price_yearly' => 10000,
        'currency' => 'USD', 'interval' => 'month',
        'features' => ['display_features' => $features, 'gateway_ids' => [], 'quotas' => $quotas],
        'is_active' => true,
    ]);
}

it('enforces plan quota limits through QuotaManager', function () {
    quotaTestPlan('capped', ['api_keys' => 2]);
    $tenant = quotaTestTenant('quota-capped', 'capped');
    tenancy()->initialize($tenant);

    try {
        $quota = app(QuotaManager::class);

        expect($quota->getLimit(tenant(), 'api_keys'))->toBe(2)
            ->and($quota->increment(tenant(), 'api_keys'))->toBeTrue()
            ->and($quota->increment(tenant(), 'api_keys'))->toBeTrue()
            ->and($quota->increment(tenant(), 'api_keys'))->toBeFalse();
    } finally {
        tenancy()->end();
    }
});

it('falls back to unlimited for metrics the plan does not define', function () {
    quotaTestPlan('partial', ['api_keys' => 5]);
    $tenant = quotaTestTenant('quota-partial', 'partial');
    tenancy()->initialize($tenant);

    try {
        expect(app(QuotaManager::class)->getLimit(tenant(), 'invitations'))->toBe(-1);
    } finally {
        tenancy()->end();
    }
});

it('reflects plan edits in feature checks without manual cache clears', function () {
    quotaTestPlan('flex', [], ['api_access']);
    $tenant = quotaTestTenant('quota-flex', 'flex');
    tenancy()->initialize($tenant);

    try {
        $resolver = app(ResolveTenantFeatures::class);

        expect($resolver->hasFeature(tenant(), 'api_access'))->toBeTrue();

        $plan = Plan::where('slug', 'flex')->firstOrFail();
        $features = $plan->features;
        $features['display_features'] = [];
        $plan->update(['features' => $features]);

        expect($resolver->hasFeature(tenant(), 'api_access'))->toBeFalse();
    } finally {
        tenancy()->end();
    }
});
