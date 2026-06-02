<?php

declare(strict_types=1);

use App\Modules\Central\Infrastructure\Services\HorizonQueueResolver;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('resolves dynamic tenant queues for horizon', function () {
    // Create some tenants
    Tenant::create([
        'id' => 'tenant-1',
        'slug' => 'acme',
        'name' => 'Acme Corp',
        'email' => 'acme@test.com',
        'status' => 'active',
        'plan_id' => 'pro'
    ]);

    Tenant::create([
        'id' => 'tenant-2',
        'slug' => 'globex',
        'name' => 'Globex',
        'email' => 'globex@test.com',
        'status' => 'suspended',
        'plan_id' => 'free'
    ]);

    // Force clear cache
    Cache::forget('horizon_tenant_queues');

    $queues = HorizonQueueResolver::resolve();

    expect($queues)->toContain('default');
    expect($queues)->toContain('tenant.acme.default');
    expect($queues)->toContain('tenant.acme.low');
    expect($queues)->toContain('tenant.globex.default');
    expect($queues)->toContain('tenant.globex.low');
});

it('caches the resolved queues', function () {
    Tenant::create([
        'id' => 'tenant-1',
        'slug' => 'acme',
        'name' => 'Acme Corp',
        'email' => 'acme@test.com',
        'status' => 'active',
        'plan_id' => 'pro'
    ]);

    Cache::forget('horizon_tenant_queues');

    $queues1 = HorizonQueueResolver::resolve();
    
    // Delete tenant but don't clear cache
    Tenant::where('slug', 'acme')->delete();
    
    $queues2 = HorizonQueueResolver::resolve();

    expect($queues1)->toBe($queues2);
    expect($queues2)->toContain('tenant.acme.default');
});
