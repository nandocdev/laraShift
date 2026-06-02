<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Infrastructure\Services\QuotaManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);
});

it('enforces limits correctly using Redis counters', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'quota-test',
        'name' => 'Quota Test',
        'email' => 'quota@test.com',
        'plan_id' => 'free', // Has invitations: 5
    ]);

    tenancy()->initialize($tenant);
    
    $manager = app(QuotaManager::class);
    $manager->reset($tenant, 'invitations');

    // 1. Initial usage
    expect($manager->getCurrentUsage($tenant, 'invitations'))->toBe(0);

    // 2. Increment until limit
    for ($i = 0; $i < 5; $i++) {
        expect($manager->increment($tenant, 'invitations'))->toBeTrue();
    }

    expect($manager->getCurrentUsage($tenant, 'invitations'))->toBe(5);

    // 3. Exceed limit
    expect($manager->increment($tenant, 'invitations'))->toBeFalse();
    expect($manager->getCurrentUsage($tenant, 'invitations'))->toBe(5); // Should not have incremented
});

it('allows unlimited usage when limit is -1', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'unlimited-test',
        'name' => 'Unlimited',
        'email' => 'unlimited@test.com',
        'plan_id' => 'enterprise', // Has invitations: -1
    ]);

    tenancy()->initialize($tenant);
    
    $manager = app(QuotaManager::class);
    $manager->reset($tenant, 'invitations');

    for ($i = 0; $i < 100; $i++) {
        expect($manager->increment($tenant, 'invitations'))->toBeTrue();
    }

    expect($manager->getCurrentUsage($tenant, 'invitations'))->toBe(100);
});
