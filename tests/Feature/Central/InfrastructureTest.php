<?php

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Infrastructure\Services\TenantQueueManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\Redis;

describe('Infrastructure Health Check', function () {
    it('requires authentication for central health check', function () {
        $this->get(route('central.health'))
            ->assertRedirect(route('central.login'));
    });

    it('returns 200 for authenticated central users', function () {
        $user = CentralUser::factory()->create();
        
        // Mock Config to use predis to bypass phpredis check
        config(['database.redis.client' => 'predis']);
        
        // Mock Redis and Queue to ensure health check passes
        Redis::shouldReceive('connection->ping')->andReturn(true);
        \Illuminate\Support\Facades\Queue::shouldReceive('size')->andReturn(0);

        $this->actingAs($user, 'central')
            ->get(route('central.health'))
            ->assertOk()
            ->assertJsonPath('status', 'healthy');
    });
});

describe('Tenant Queue Management', function () {
    it('resolves to shared bucket queues instead of slug-based queues', function () {
        $tenant = Tenant::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'slug' => 'acme',
            'name' => 'Acme Corp',
            'email' => 'admin@acme.com',
            'status' => 'active'
        ]);
        
        $resolved = TenantQueueManager::resolve($tenant, 'default');
        expect($resolved)->toMatch('/^tenant\.b[1-5]\.default$/');
        
        $resolvedHigh = TenantQueueManager::resolve($tenant, 'high');
        expect($resolvedHigh)->toMatch('/^tenant\.b[1-5]\.high$/');
    });

    it('forces low priority for suspended tenants', function () {
        $tenant = Tenant::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'slug' => 'late-payer',
            'name' => 'Late Payer',
            'email' => 'admin@late.com',
            'status' => 'suspended'
        ]);
        
        $resolved = TenantQueueManager::resolve($tenant, 'high');
        expect($resolved)->toMatch('/^tenant\.b[1-5]\.low$/');
    });
});
