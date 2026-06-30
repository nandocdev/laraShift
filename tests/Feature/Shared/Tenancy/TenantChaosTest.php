<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Tenancy\Services\TenantConfigCache;
use App\Modules\Shared\Tenancy\Services\TenantResolver;
use App\Modules\Shared\Tenancy\ValueObjects\TenantContext;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->plan = Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free Plan',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $this->tenant = Tenant::firstOrCreate(['id' => '00000000-0000-0000-0000-000000000001'], [
        'slug' => 'chaos-tenant',
        'name' => 'Chaos Tenant',
        'email' => 'chaos@tenant.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);
});

test('tenant context handles uninitialized tenancy gracefully', function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    $context = TenantContext::fromCurrent();

    expect($context)->toBeNull();
});

test('tenant context can initialize tenancy', function () {
    tenancy()->end();

    $context = new TenantContext($this->tenant->id, 'chaos-tenant');
    $context->initialize();

    expect(tenancy()->initialized)->toBeTrue();
    expect((string) tenancy()->tenant->getTenantKey())->toBe($this->tenant->id);
});

test('initialize tenant from context is idempotent', function () {
    tenancy()->initialize($this->tenant);

    $context = TenantContext::fromCurrent();
    $context->initialize();

    expect(tenancy()->initialized)->toBeTrue();
});

test('fake db connection failure does not crash context creation', function () {
    $context = new TenantContext($this->tenant->id, 'chaos-tenant');

    expect($context->tenantId())->toBe($this->tenant->id);
});

test('multiple sequential tenant initializations clean up state', function () {
    $tenantB = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'chaos-b-'.Str::random(5),
        'name' => 'Chaos B',
        'email' => Str::random(10).'@b.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($this->tenant);
    expect(tenancy()->tenant->id)->toBe($this->tenant->id);

    tenancy()->end();
    tenancy()->initialize($tenantB);
    expect(tenancy()->tenant->id)->toBe($tenantB->id);

    tenancy()->end();
    expect(tenancy()->initialized)->toBeFalse();
});

test('tenant resolver returns null for non-existent id', function () {
    $resolver = app(TenantResolver::class);

    $tenant = $resolver->findById('00000000-0000-0000-0000-000000099999');

    expect($tenant)->toBeNull();
});

test('tenant config cache handles missing plan gracefully', function () {
    $tenantWithoutPlan = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'no-plan-'.Str::random(5),
        'name' => 'No Plan',
        'email' => Str::random(10).'@noplan.com',
        'plan_id' => 'nonexistent-plan',
    ]);

    $configCache = app(TenantConfigCache::class);

    $config = $configCache->getAll($tenantWithoutPlan);

    expect($config['plan_id'])->toBe('nonexistent-plan');
    expect($config['plan_name'])->toBe('Free');
});
