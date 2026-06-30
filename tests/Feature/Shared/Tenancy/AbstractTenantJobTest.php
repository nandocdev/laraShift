<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Tenancy\Jobs\AbstractTenantJob;
use App\Modules\Shared\Tenancy\ValueObjects\TenantContext;

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
        'slug' => 'test-tenant',
        'name' => 'Test Tenant',
        'email' => 'test@tenant.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);
});

test('abstract tenant job stores tenant context', function () {
    $context = new TenantContext($this->tenant->id, 'test-tenant');

    $job = new class($context) extends AbstractTenantJob
    {
        public function handle(): void {}
    };

    expect($job->tenantId)->toBe($this->tenant->id);
    expect($job->tenantSlug)->toBe('test-tenant');
    expect($job->tenantContext)->toHaveKey('tenant_id');
    expect($job->tenantContext['tenant_id'])->toBe($this->tenant->id);
});

test('abstract tenant job generates queue name', function () {
    $context = new TenantContext($this->tenant->id, 'test-tenant');

    $job = new class($context) extends AbstractTenantJob
    {
        public function handle(): void {}
    };

    $queueName = $job->queueName();

    expect($queueName)->toMatch('/^tenant\.b[1-5]\.(default|high|low)$/');
});

test('abstract tenant job with high priority uses high queue', function () {
    $context = new TenantContext($this->tenant->id);

    $job = new class($context) extends AbstractTenantJob
    {
        public int $priority = 7;

        public function handle(): void {}
    };

    expect($job->queueName())->toContain('.high');
});

test('abstract tenant job with low priority uses low queue', function () {
    $context = new TenantContext($this->tenant->id);

    $job = new class($context) extends AbstractTenantJob
    {
        public int $priority = 1;

        public function handle(): void {}
    };

    expect($job->queueName())->toContain('.low');
});

test('tenant context can be created from current tenancy', function () {
    if (! function_exists('tenancy') || ! tenancy()->initialized) {
        tenancy()->initialize($this->tenant);
    }

    $context = TenantContext::fromCurrent();

    expect($context)->not->toBeNull();
    expect($context->tenantId())->toBe($this->tenant->id);
});

test('tenant context serializes to array', function () {
    $context = new TenantContext($this->tenant->id, 'test-tenant');

    $data = $context->toArray();

    expect($data['tenant_id'])->toBe($this->tenant->id);
    expect($data['tenant_slug'])->toBe('test-tenant');
});

test('tenant context can be reconstructed from array', function () {
    $original = new TenantContext($this->tenant->id, 'test-tenant');
    $data = $original->toArray();

    $reconstructed = TenantContext::fromArray($data);

    expect($reconstructed->tenantId())->toBe($this->tenant->id);
    expect($reconstructed->tenantSlug())->toBe('test-tenant');
});

test('tenant context equality check', function () {
    $a = new TenantContext($this->tenant->id);
    $b = new TenantContext($this->tenant->id);
    $c = new TenantContext('00000000-0000-0000-0000-000000000002');

    expect($a->equals($b))->toBeTrue();
    expect($a->equals($c))->toBeFalse();
});
