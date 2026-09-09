<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Catalog\Interface\Livewire\TenantOverrides;
use App\Modules\Central\Provisioning\Models\Tenant;
use Livewire\Livewire;

it('loads the tenant overrides page without serialization errors', function () {
    $user = CentralUser::factory()->create();

    $tenant = Tenant::create([
        'id' => 'e7fe4716-3b93-4568-b9bc-b67a9712014d',
        'slug' => 'acme',
        'name' => 'Acme Corporation',
        'email' => 'admin@acme.com',
        'plan_id' => 'enterprise',
        'status' => 'active',
    ]);

    $this->actingAs($user, 'central')
        ->get(route('central.tenants.features.overrides', $tenant))
        ->assertOk()
        ->assertSee('Acme Corporation');
});

it('survives livewire hydration roundtrips on the overrides page', function () {
    $user = CentralUser::factory()->create();

    $tenant = Tenant::create([
        'id' => 'e7fe4716-3b93-4568-b9bc-b67a9712014d',
        'slug' => 'acme',
        'name' => 'Acme Corporation',
        'email' => 'admin@acme.com',
        'plan_id' => 'enterprise',
        'status' => 'active',
    ]);

    Livewire::actingAs($user, 'central')
        ->test(TenantOverrides::class, ['tenant' => $tenant])
        ->call('$refresh')
        ->assertOk();
});
